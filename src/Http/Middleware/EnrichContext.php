<?php

namespace Abdulsalam\LaravelContextFlow\Http\Middleware;

use Abdulsalam\LaravelContextFlow\Contracts\ContextEnricher;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnrichContext
{
    public function __construct(private Container $container, private array $enrichers = [])
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->enrichers as $enricherClass) {
            $enricher = $this->container->make($enricherClass);
            if ($enricher instanceof ContextEnricher) {
                Context::add(array_filter($enricher->enrich(), static fn ($value) => $value !== null));
            }
        }

        return $next($request);
    }
}
