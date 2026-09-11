<?php

namespace Abdulsalam\LaravelContextFlow\Http\Middleware;

use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Abdulsalam\LaravelContextFlow\Enums\BoundaryType;
use Abdulsalam\LaravelContextFlow\Enums\TrustLevel;
use Abdulsalam\LaravelContextFlow\Events\ContextExtracted;
use Abdulsalam\LaravelContextFlow\Events\ContextRejected;
use Abdulsalam\LaravelContextFlow\Http\BaggageCodec;
use Abdulsalam\LaravelContextFlow\Security\ContextSigner;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

final readonly class CaptureIncomingContext
{
    public function __construct(
        private ContextFlowManager $flow,
        private BaggageCodec $baggage,
        private ContextSigner $signer,
        private Config $config,
        private Dispatcher $events,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $this->config->get('context-flow.enabled', true)
            || ! (bool) $this->config->get('context-flow.http.incoming', true)) {
            return $next($request);
        }

        $headers = (array) $this->config->get('context-flow.headers', []);
        $correlation = $this->header($request, $headers['correlation_id'] ?? 'X-Correlation-ID');
        $incomingCausation = $this->header($request, $headers['causation_id'] ?? 'X-Causation-ID');
        $baggageHeader = $this->header($request, $headers['baggage'] ?? 'baggage') ?? '';
        $maxTransportBytes = (int) $this->config->get('context-flow.limits.http_total_bytes', 4096);
        if (strlen($baggageHeader) > $maxTransportBytes) {
            $this->events->dispatch(new ContextRejected('incoming_baggage_too_large'));
            $baggageHeader = '';
        }

        $canonicalBaggage = $this->baggage->encode($this->baggage->decode($baggageHeader));
        $trust = $this->resolveTrust($request, $correlation ?? '', $incomingCausation ?? '', $canonicalBaggage, $headers);

        $acceptPublic = (bool) $this->config->get('context-flow.http.accept_public_correlation_id', true);
        if (! $this->flow->isValidId($correlation) || ($trust === TrustLevel::Public && ! $acceptPublic)) {
            if ($correlation !== null) {
                $this->events->dispatch(new ContextRejected('invalid_or_untrusted_correlation_id', 'correlation_id'));
            }
            $correlation = null;
        }

        $causation = $trust === TrustLevel::Internal && $this->flow->isValidId($incomingCausation)
            ? $incomingCausation
            : null;

        $this->flow->startRoot(BoundaryType::Http, $correlation, $causation, $trust);

        $accepted = [];
        $incoming = $this->baggage->decode($canonicalBaggage);
        $maxKeys = (int) $this->config->get('context-flow.limits.max_keys', 32);
        if (count($incoming) > $maxKeys) {
            $this->events->dispatch(new ContextRejected('too_many_incoming_context_keys'));
            $incoming = array_slice($incoming, 0, $maxKeys, true);
        }

        foreach ($incoming as $key => $value) {
            if ($this->flow->acceptsIncomingKey($key, $trust, $value)) {
                Context::add($key, $value);
                $accepted[$key] = $value;
            }
        }
        $this->events->dispatch(new ContextExtracted($accepted, $trust));

        $response = $next($request);

        if ((bool) $this->config->get('context-flow.http.response_headers', true)) {
            $response->headers->set($headers['correlation_id'] ?? 'X-Correlation-ID', (string) $this->flow->correlationId());
            $response->headers->set($headers['request_id'] ?? 'X-Request-ID', (string) $this->flow->requestId());
        }

        return $response;
    }

    private function resolveTrust(Request $request, string $correlationId, string $causationId, string $baggage, array $headers): TrustLevel
    {
        $signature = $this->header($request, $headers['signature'] ?? 'X-Context-Signature');
        $timestamp = $this->header($request, $headers['timestamp'] ?? 'X-Context-Timestamp');

        if ($signature === null || $timestamp === null || ! ctype_digit($timestamp) || ! $this->signer->available()) {
            return TrustLevel::Public;
        }

        $time = (int) $timestamp;
        $tolerance = (int) $this->config->get('context-flow.security.timestamp_tolerance_seconds', 60);
        if (abs(time() - $time) > $tolerance) {
            return TrustLevel::Public;
        }

        return $this->signer->verify($signature, $correlationId, $causationId, $baggage, $time)
            ? TrustLevel::Internal
            : TrustLevel::Public;
    }

    private function header(Request $request, string $name): ?string
    {
        $value = $request->headers->get($name);
        return is_string($value) && $value !== '' ? $value : null;
    }
}
