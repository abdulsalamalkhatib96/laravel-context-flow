<?php

namespace Abdulsalam\LaravelContextFlow\Http;

use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Abdulsalam\LaravelContextFlow\Contracts\TrustResolver;
use Abdulsalam\LaravelContextFlow\Enums\PropagationTarget;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;
use Abdulsalam\LaravelContextFlow\Events\ContextPropagated;
use Abdulsalam\LaravelContextFlow\Events\ContextPropagating;
use Abdulsalam\LaravelContextFlow\Security\ContextSigner;
use Abdulsalam\LaravelContextFlow\Support\ContextKeys;
use Abdulsalam\LaravelContextFlow\Support\ValueNormalizer;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Http\Message\RequestInterface;

final readonly class HttpContextInjector
{
    public function __construct(
        private ContextFlowManager $flow,
        private TrustResolver $trustResolver,
        private BaggageCodec $baggage,
        private ValueNormalizer $normalizer,
        private ContextSigner $signer,
        private Config $config,
        private Dispatcher $events,
    ) {
    }

    public function __invoke(RequestInterface $request): RequestInterface
    {
        if (! (bool) $this->config->get('context-flow.enabled', true)
            || ! (bool) $this->config->get('context-flow.http.outgoing', true)) {
            return $request;
        }

        $destination = (string) $request->getUri();
        $trust = $this->trustResolver->resolve($destination);
        $target = match ($trust) {
            TrustLevel::Internal => PropagationTarget::InternalHttp,
            TrustLevel::Partner => PropagationTarget::PartnerHttp,
            default => PropagationTarget::ExternalHttp,
        };

        if ($target === PropagationTarget::ExternalHttp
            && (string) $this->config->get('context-flow.security.default_remote_policy', 'deny') === 'deny') {
            return $request;
        }

        $snapshot = $this->flow->snapshot($target, $trust);
        if ($snapshot->values === []) {
            return $request;
        }

        $this->events->dispatch(new ContextPropagating($snapshot, $target, $destination));
        $headerNames = (array) $this->config->get('context-flow.headers', []);
        $headers = [];

        if ($snapshot->correlationId !== null) {
            $headers[$headerNames['correlation_id'] ?? 'X-Correlation-ID'] = $snapshot->correlationId;
        }

        // The caller's current execution becomes the callee's causation id.
        if ($snapshot->executionId !== null) {
            $headers[$headerNames['causation_id'] ?? 'X-Causation-ID'] = $snapshot->executionId;
        }

        $business = [];
        foreach ($snapshot->values as $key => $value) {
            if (str_starts_with($key, 'context_flow.')) {
                continue;
            }

            $normalized = $this->normalizer->toTransportString($value);
            if ($normalized !== null) {
                $business[$key] = $normalized;
            }
        }

        $baggage = $this->baggage->encode($business);
        if ($baggage !== '') {
            $headers[$headerNames['baggage'] ?? 'baggage'] = $baggage;
        }

        if ($trust === TrustLevel::Internal
            && (bool) $this->config->get('context-flow.security.sign_internal_context', false)
            && $this->signer->available()) {
            $timestamp = time();
            $signature = $this->signer->sign(
                $snapshot->correlationId ?? '',
                $snapshot->executionId ?? '',
                $baggage,
                $timestamp,
            );

            if ($signature !== null) {
                $headers[$headerNames['timestamp'] ?? 'X-Context-Timestamp'] = (string) $timestamp;
                $headers[$headerNames['signature'] ?? 'X-Context-Signature'] = $signature;
            }
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->events->dispatch(new ContextPropagated($snapshot, $target, $destination, array_keys($headers)));

        return $request;
    }
}
