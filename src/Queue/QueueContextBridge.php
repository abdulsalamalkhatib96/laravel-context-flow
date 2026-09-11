<?php

namespace Abdulsalam\LaravelContextFlow\Queue;

use Abdulsalam\LaravelContextFlow\Context\ContextRegistry;
use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Abdulsalam\LaravelContextFlow\Contracts\PropagationPolicy;
use Abdulsalam\LaravelContextFlow\Enums\BoundaryType;
use Abdulsalam\LaravelContextFlow\Enums\PropagationTarget;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;
use Abdulsalam\LaravelContextFlow\Security\SensitiveKeyDetector;
use Abdulsalam\LaravelContextFlow\Support\ContextKeys;
use Abdulsalam\LaravelContextFlow\Support\ValueNormalizer;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Log\Context\Repository;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;

final readonly class QueueContextBridge
{
    public function __construct(
        private ContextFlowManager $flow,
        private ContextRegistry $registry,
        private PropagationPolicy $policy,
        private SensitiveKeyDetector $sensitive,
        private ValueNormalizer $normalizer,
        private Config $config,
    ) {
    }

    public function filterForDehydration(Repository $context): void
    {
        if (! (bool) $this->config->get('context-flow.queue.enabled', true)) {
            return;
        }

        if ((bool) $context->getHidden(ContextKeys::PROPAGATION_DISABLED, false)) {
            $context->flush();
            return;
        }

        $only = $context->getHidden(ContextKeys::ONLY_KEYS, null);
        $only = is_array($only) ? array_flip($only) : null;
        $transport = array_flip(ContextKeys::transportKeys());
        $runtime = array_flip(array_diff(ContextKeys::allManaged(), ContextKeys::transportKeys()));
        $unregisteredMode = (string) $this->config->get('context-flow.queue.unregistered_keys', 'preserve');

        foreach ([false, true] as $hidden) {
            $values = $hidden ? $context->allHidden() : $context->all();

            foreach ($values as $key => $value) {
                $keep = true;

                if ($key === ContextKeys::PROPAGATION_DISABLED || $key === ContextKeys::ONLY_KEYS || isset($runtime[$key])) {
                    $keep = false;
                } elseif (isset($transport[$key])) {
                    $keep = true;
                } elseif ($this->sensitive->isSensitive($key)) {
                    $keep = false;
                } elseif (($definition = $this->registry->get($key)) !== null) {
                    $normalized = $this->normalizer->toTransportString($value);
                    $max = min(
                        $definition->maxBytes,
                        (int) $this->config->get('context-flow.limits.value_bytes', 1024),
                    );

                    $keep = ($only === null || isset($only[$key]))
                        && $this->policy->allows($definition, PropagationTarget::Queue, TrustLevel::Local)
                        && $normalized !== null
                        && strlen($normalized) <= $max;
                } elseif ($only !== null) {
                    $keep = isset($only[$key]);
                } else {
                    $keep = $unregisteredMode === 'preserve';
                }

                if (! $keep) {
                    $hidden ? $context->forgetHidden($key) : $context->forget($key);
                }
            }
        }
    }

    public function onHydrated(Repository $context): void
    {
        if (! (bool) $this->config->get('context-flow.queue.enabled', true)) {
            return;
        }

        if ((bool) $this->config->get('context-flow.queue.rotate_execution_id', true)) {
            $this->flow->rotateExecution(BoundaryType::Queue);
        } else {
            $this->flow->ensureRoot(BoundaryType::Queue);
        }
    }

    public function onJobProcessing(JobProcessing $event): void
    {
        $this->flow->ensureRoot(BoundaryType::Queue);

        if (! (bool) $this->config->get('context-flow.queue.include_job_metadata', true)) {
            return;
        }

        $job = $event->job;
        Context::add(array_filter([
            ContextKeys::JOB_ID => method_exists($job, 'uuid') ? $job->uuid() : null,
            ContextKeys::JOB_NAME => method_exists($job, 'resolveName') ? $job->resolveName() : $job->getName(),
            ContextKeys::JOB_QUEUE => method_exists($job, 'getQueue') ? $job->getQueue() : null,
            ContextKeys::JOB_CONNECTION => $event->connectionName,
            ContextKeys::JOB_ATTEMPT => method_exists($job, 'attempts') ? $job->attempts() : null,
        ], static fn ($value) => $value !== null));
    }

    public function onJobFinished(JobProcessed|JobExceptionOccurred|JobFailed $event): void
    {
        Context::forget([
            ContextKeys::JOB_ID,
            ContextKeys::JOB_NAME,
            ContextKeys::JOB_QUEUE,
            ContextKeys::JOB_CONNECTION,
            ContextKeys::JOB_ATTEMPT,
        ]);
    }
}
