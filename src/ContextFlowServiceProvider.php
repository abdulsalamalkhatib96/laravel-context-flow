<?php

namespace Abdulsalam\LaravelContextFlow;

use Abdulsalam\LaravelContextFlow\Console\ConsoleContextBridge;
use Abdulsalam\LaravelContextFlow\Console\DoctorCommand;
use Abdulsalam\LaravelContextFlow\Console\InspectContextCommand;
use Abdulsalam\LaravelContextFlow\Console\SchedulerContextBridge;
use Abdulsalam\LaravelContextFlow\Context\ContextRegistry;
use Abdulsalam\LaravelContextFlow\Contracts\IdGenerator;
use Abdulsalam\LaravelContextFlow\Contracts\PropagationPolicy;
use Abdulsalam\LaravelContextFlow\Contracts\TelemetryBridge;
use Abdulsalam\LaravelContextFlow\Contracts\TrustResolver;
use Abdulsalam\LaravelContextFlow\Http\BaggageCodec;
use Abdulsalam\LaravelContextFlow\Http\HttpContextInjector;
use Abdulsalam\LaravelContextFlow\Http\Middleware\CaptureIncomingContext;
use Abdulsalam\LaravelContextFlow\Http\Middleware\EnrichContext;
use Abdulsalam\LaravelContextFlow\OpenTelemetry\NullTelemetryBridge;
use Abdulsalam\LaravelContextFlow\Policy\DefaultPropagationPolicy;
use Abdulsalam\LaravelContextFlow\Policy\HostTrustResolver;
use Abdulsalam\LaravelContextFlow\Queue\QueueContextBridge;
use Abdulsalam\LaravelContextFlow\Security\ContextSigner;
use Abdulsalam\LaravelContextFlow\Security\SensitiveKeyDetector;
use Abdulsalam\LaravelContextFlow\Support\DebugLogger;
use Abdulsalam\LaravelContextFlow\Support\ValueNormalizer;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

final class ContextFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/context-flow.php', 'context-flow');

        $this->app->singleton(ContextRegistry::class, fn ($app) => new ContextRegistry(
            (array) $app['config']->get('context-flow.keys', []),
        ));

        $this->app->singleton(IdGenerator::class, function ($app) {
            $class = (string) $app['config']->get('context-flow.ids.generator');
            return $app->make($class);
        });

        $this->app->singleton(PropagationPolicy::class, DefaultPropagationPolicy::class);
        $this->app->singleton(TrustResolver::class, fn ($app) => new HostTrustResolver(
            (array) $app['config']->get('context-flow.http.trusted_hosts', []),
            (array) $app['config']->get('context-flow.http.partner_hosts', []),
        ));
        $this->app->singleton(SensitiveKeyDetector::class, fn ($app) => new SensitiveKeyDetector(
            (array) $app['config']->get('context-flow.security.sensitive_key_patterns', []),
        ));
        $this->app->singleton(ContextSigner::class, fn ($app) => new ContextSigner(
            $app['config']->get('context-flow.security.signing_key'),
        ));
        $this->app->singleton(ValueNormalizer::class);
        $this->app->singleton(BaggageCodec::class);
        $this->app->singleton(TelemetryBridge::class, NullTelemetryBridge::class);

        $this->app->singleton(ContextFlowManager::class, fn ($app) => new ContextFlowManager(
            ids: $app->make(IdGenerator::class),
            registry: $app->make(ContextRegistry::class),
            policy: $app->make(PropagationPolicy::class),
            sensitive: $app->make(SensitiveKeyDetector::class),
            normalizer: $app->make(ValueNormalizer::class),
            events: $app['events'],
            config: (array) $app['config']->get('context-flow', []),
        ));

        $this->app->singleton(EnrichContext::class, fn ($app) => new EnrichContext(
            $app,
            (array) $app['config']->get('context-flow.enrichers', []),
        ));
    }

    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__.'/../config/context-flow.php' => config_path('context-flow.php'),
        ], 'context-flow-config');

        $router->aliasMiddleware('context-flow.capture', CaptureIncomingContext::class);
        $router->aliasMiddleware('context-flow.enrich', EnrichContext::class);

        if ($this->app->runningInConsole()) {
            $this->commands([InspectContextCommand::class, DoctorCommand::class]);
        }

        if (! (bool) config('context-flow.enabled', true)) {
            return;
        }

        $this->bootHttp();
        $this->bootQueue();
        $this->bootConsole();
        $this->bootDebug();
    }

    private function bootHttp(): void
    {
        if ((bool) config('context-flow.http.incoming', true) && $this->app->bound(HttpKernel::class)) {
            $kernel = $this->app->make(HttpKernel::class);
            if (method_exists($kernel, 'prependMiddleware')) {
                $kernel->prependMiddleware(CaptureIncomingContext::class);
            }
        }

        if ((bool) config('context-flow.http.outgoing', true)) {
            Http::globalRequestMiddleware($this->app->make(HttpContextInjector::class));
        }
    }

    private function bootQueue(): void
    {
        if (! (bool) config('context-flow.queue.enabled', true)) {
            return;
        }

        $bridge = $this->app->make(QueueContextBridge::class);
        Context::dehydrating(fn ($context) => $bridge->filterForDehydration($context));
        Context::hydrated(fn ($context) => $bridge->onHydrated($context));

        Event::listen(JobProcessing::class, [$bridge, 'onJobProcessing']);
        Event::listen(JobProcessed::class, [$bridge, 'onJobFinished']);
        Event::listen(JobExceptionOccurred::class, [$bridge, 'onJobFinished']);
        Event::listen(JobFailed::class, [$bridge, 'onJobFinished']);
    }


    private function bootDebug(): void
    {
        if (! (bool) config('context-flow.debug', false)) {
            return;
        }

        $logger = $this->app->make(DebugLogger::class);
        Event::listen(\Abdulsalam\LaravelContextFlow\Events\ContextBoundaryStarted::class, [$logger, 'boundary']);
        Event::listen(\Abdulsalam\LaravelContextFlow\Events\ContextPropagated::class, [$logger, 'propagated']);
        Event::listen(\Abdulsalam\LaravelContextFlow\Events\ContextRejected::class, [$logger, 'rejected']);
    }

    private function bootConsole(): void
    {
        $console = $this->app->make(ConsoleContextBridge::class);
        Event::listen(CommandStarting::class, [$console, 'starting']);
        Event::listen(CommandFinished::class, [$console, 'finished']);

        $scheduler = $this->app->make(SchedulerContextBridge::class);
        Event::listen(ScheduledTaskStarting::class, [$scheduler, 'starting']);
        Event::listen(ScheduledTaskFinished::class, [$scheduler, 'finished']);
        Event::listen(ScheduledTaskFailed::class, [$scheduler, 'finished']);
    }
}
