# Laravel Context Flow

Distributed context propagation for Laravel applications, built **on top of Laravel Context** instead of replacing it.

The package preserves one logical workflow across HTTP requests, queued jobs, scheduled work and service-to-service HTTP calls without passing correlation or tenant metadata through dozens of constructors.

```text
POST /withdraw        CreateWithdrawalJob       payment-api          WebhookJob
C1 / E1          ->   C1 / E2              ->  C1 / E3        ->   C1 / E4
                         caused by E1              caused by E2       caused by E3
```

## Design rules

1. `Illuminate\Support\Facades\Context` is the source of truth.
2. `correlation_id` identifies the complete workflow.
3. `execution_id` identifies one execution boundary.
4. `causation_id` points to the execution that caused the current one.
5. `origin` remains the root boundary that started the workflow; `boundary` is the current execution boundary.
6. `request_id` is local to one HTTP request and is never reused by downstream services.
7. Network propagation is explicit and trust-aware. Application metadata is never sent just because it exists in `Context`.
8. Context is metadata, **not authorization**. Never authorize a tenant, role or user from propagated context alone.
9. The package does not generate or overwrite `traceparent` / `tracestate`. OpenTelemetry instrumentation remains the owner of distributed tracing.

## Requirements

- PHP 8.2+
- Laravel 12 or 13

Laravel 13 itself requires PHP 8.3+.

## Installation

```bash
composer require abdulsalam/laravel-context-flow
```

Publish the configuration when you need custom keys or trust policies:

```bash
php artisan vendor:publish --tag=context-flow-config
```

The package auto-registers its service provider through Laravel package discovery.

## Zero-config behavior

With the default configuration the package automatically:

- creates a correlation ID, execution ID and request ID for incoming HTTP requests;
- exposes the correlation and request IDs on HTTP responses;
- adds those IDs to Laravel Context, therefore Laravel logging receives them naturally;
- uses Laravel's native Context dehydration / hydration for queues;
- rotates the execution ID when a queued job starts and stores the previous execution as the causation ID;
- creates root contexts for Artisan commands and scheduled callbacks;
- adds queue metadata such as job ID, class, queue, connection and attempt;
- blocks business context from external HTTP destinations by default.

No `HasContext` job trait and no custom queue payload format are used.

## Basic usage

Use Laravel Context normally:

```php
use Illuminate\Support\Facades\Context;

Context::add('tenant_id', $tenant->id);
Context::add('actor_id', auth()->id());
Context::add('locale', app()->getLocale());

CreateWithdrawalJob::dispatch($withdrawal);
```

Inside the job:

```php
Context::get('tenant_id');
Context::get('actor_id');
```

The package's own facade is intentionally small:

```php
use Abdulsalam\LaravelContextFlow\Facades\ContextFlow;

ContextFlow::correlationId();
ContextFlow::executionId();
ContextFlow::causationId();
ContextFlow::requestId();
```

It does **not** provide `put/get/forget` wrappers because Laravel Context already does that.

## Registering application context keys

Only registered application keys are eligible for package-managed network propagation:

```php
// config/context-flow.php
'keys' => [
    'tenant_id' => [
        'targets' => ['queue', 'http.internal'],
        'accept_from' => ['internal'],
        'priority' => 500,
        'max_bytes' => 128,
    ],

    'actor_id' => [
        'targets' => ['queue', 'http.internal'],
        'accept_from' => ['internal'],
        'priority' => 500,
        'max_bytes' => 128,
    ],

    'locale' => [
        'targets' => ['queue', 'http.internal'],
        'accept_from' => ['internal', 'public'],
        'priority' => 200,
        'max_bytes' => 32,
    ],
],
```

Available targets:

```text
queue
http.internal
http.partner
http.external
process
message_bus
```

`process` and `message_bus` are extension targets in the policy model; the built-in v1 runtime automatically integrates HTTP and Laravel queues.

## Internal HTTP propagation

Declare internal destinations:

```php
'http' => [
    'trusted_hosts' => [
        '*.internal.example.com',
        '*.svc.cluster.local',
    ],
],
```

Then ordinary Laravel HTTP calls are enriched automatically:

```php
Http::post('https://wallet.internal.example.com/withdrawals', [
    'amount' => 100,
]);
```

Typical headers:

```http
X-Correlation-ID: 0199...
X-Causation-ID: 0199...
baggage: actor_id=99,tenant_id=71
```

`X-Causation-ID` is the caller's current `execution_id`. The receiving service creates its **own** execution and request IDs.

## External HTTP is default-deny

By default:

```php
Http::post('https://api.stripe.com/...');
```

receives **no Context Flow headers**.

This is controlled by:

```php
'security' => [
    'default_remote_policy' => 'deny',
],
```

If you deliberately switch that policy away from `deny`, only keys explicitly targeting `http.external` become eligible. Do not put credentials, session material or PII in propagated context.

## Signed internal context

A host allowlist controls what this service considers an internal **destination**. It does not prove that an inbound request really came from an internal service.

For inbound restricted baggage (`tenant_id`, `actor_id`, etc.), enable signing on all cooperating services:

```env
CONTEXT_FLOW_SIGN_INTERNAL=true
CONTEXT_FLOW_SIGNING_KEY="use-at-least-32-random-bytes-here"
```

Outgoing internal calls receive:

```http
X-Context-Timestamp: 1789124211
X-Context-Signature: sha256=...
```

The signature covers the correlation ID, causation ID, canonical baggage and timestamp. Inbound requests are treated as `internal` only when the signature is valid and within the configured timestamp tolerance.

**Important:** a valid signature still does not turn context into an authorization source. Re-resolve the authenticated principal and tenant using your normal security model.

## Public inbound correlation IDs

By default a syntactically safe public `X-Correlation-ID` is accepted so clients can correlate support requests. Disable that if you want only server-generated IDs:

```php
'http' => [
    'accept_public_correlation_id' => false,
],
```

Incoming request IDs are never trusted; the package always creates a new local request ID.

## Authentication / tenancy enrichment

The capture middleware is prepended globally, intentionally before authentication. If actor or tenant values only become available after auth/tenancy middleware, create an enricher:

```php
use Abdulsalam\LaravelContextFlow\Contracts\ContextEnricher;

final class AuthContextEnricher implements ContextEnricher
{
    public function enrich(): array
    {
        return [
            'actor_id' => auth()->id(),
        ];
    }
}
```

Register it:

```php
'enrichers' => [
    App\Context\AuthContextEnricher::class,
],
```

Then place the middleware after authentication on the routes/groups that need it:

```php
Route::middleware(['auth:sanctum', 'context-flow.enrich'])->group(function () {
    // ...
});
```

## Queue propagation

Laravel already serializes Context into queued jobs. This package hooks into Laravel's native dehydration/hydration lifecycle instead of adding properties to jobs.

When dispatching:

```text
correlation_id = C1
execution_id   = E1
```

The job starts as:

```text
correlation_id = C1
execution_id   = E2
causation_id   = E1
```

A retry receives another execution ID while retaining the correlation lineage.

### Queue compatibility policy

Laravel and third-party packages may store their own values in Context. Removing every unknown key would silently break them. Therefore the default is:

```php
'queue' => [
    'unregistered_keys' => 'preserve',
],
```

Registered Context Flow keys still obey their configured targets and sensitive-looking keys are removed.

For a strict application in which you fully own every Context key:

```php
'unregistered_keys' => 'drop',
```

## Local-only values

Register a key with no propagation targets:

```php
'debug_payload' => [
    'targets' => [],
    'accept_from' => [],
],
```

Do not confuse Laravel hidden context with non-propagating context. Hidden context means it is hidden from logging; Laravel may still serialize it for queues. Context Flow applies propagation policy independently.

## Scoped suppression

Disable propagation for a specific operation:

```php
ContextFlow::withoutPropagation(function () {
    Http::post('https://example.com');
    SensitiveJob::dispatch();
});
```

Or restrict application keys for a scope:

```php
ContextFlow::only(['locale'], function () {
    SomeJob::dispatch();
});
```

The package uses Laravel Context's scoped hidden data for these flags, so it does not store request-specific mutable state in package singletons.

## Scheduler and Artisan

Artisan commands receive a root context when none exists.

Scheduled tasks receive a fresh root context per execution, which prevents one iteration of `schedule:work` from leaking registered Context Flow values into the next task.

## Logging and exceptions

Laravel already injects Laravel Context into logs. This package does not add another Monolog processor.

```php
Log::info('withdrawal created');
```

will naturally include the Context Flow IDs through Laravel's Context logging integration.

Exception trackers can implement:

```php
Abdulsalam\LaravelContextFlow\Contracts\ExceptionContextReporter
```

The package deliberately does not force Sentry/Bugsnag/Rollbar dependencies.

## OpenTelemetry

Context Flow owns **application correlation metadata**, not tracing.

It never overwrites:

```text
traceparent
tracestate
```

A `TelemetryBridge` contract and a no-op implementation are included so an application-specific bridge can be bound without coupling the package to one OpenTelemetry SDK version.

## Baggage format

Application metadata is transported using a conservative W3C-baggage-compatible key/value representation:

```http
baggage: locale=en-US,tenant_id=71
```

The built-in codec intentionally supports the common key/value subset. It does not attempt to interpret vendor-specific baggage properties.

## Limits and overflow

Defaults:

```php
'limits' => [
    'max_keys' => 32,
    'key_bytes' => 128,
    'value_bytes' => 1024,
    'http_total_bytes' => 4096,
    'overflow' => 'drop_low_priority',
],
```

In non-production environments the published config defaults to `throw`, which catches oversized contexts early. Production defaults to dropping lower-priority application keys before transport metadata.

## Sensitive key protection

Common credential names are denied even if accidentally configured:

```text
password
secret
token
api_key
authorization
cookie
session
cvv
private_key
```

This is a secondary safety net, not a replacement for explicit key registration.

## Diagnostics

Inspect the current state:

```bash
php artisan context-flow:inspect
```

Validate configuration:

```bash
php artisan context-flow:doctor
```

The doctor checks signing configuration, risky public keys, sensitive-looking propagated keys, host configuration and header size limits.

Enable debug diagnostics:

```env
CONTEXT_FLOW_DEBUG=true
```

Debug logging records boundary transitions, rejection reasons and propagated **header names**, but not baggage values.

## Testing helpers

Use the provided trait in package/application tests:

```php
use Abdulsalam\LaravelContextFlow\Testing\InteractsWithContextFlow;

class WithdrawalTest extends TestCase
{
    use InteractsWithContextFlow;

    public function test_context(): void
    {
        // ...
        $this->assertCorrelationIdExists();
        $this->assertContextValue('tenant_id', 12);
    }
}
```

The repository includes regression tests for UUID generation, baggage parsing, signing, trust resolution, snapshot filtering, execution rotation, queue filtering and HTTP injection.

## Octane / long-running workers

No per-request values are stored in package singletons. Execution state lives in Laravel Context. At each HTTP/scheduled root boundary, package-managed and registered keys are reset before new IDs are created. Queue jobs rely on Laravel's hydration lifecycle, which flushes and hydrates Context per job.

If another package stores unscoped mutable state outside Laravel Context, that package must still handle its own worker reset lifecycle.

## Extension points

Replace bindings in your application service provider when needed:

```php
$this->app->bind(
    \Abdulsalam\LaravelContextFlow\Contracts\PropagationPolicy::class,
    App\Context\StrictPropagationPolicy::class,
);

$this->app->bind(
    \Abdulsalam\LaravelContextFlow\Contracts\TrustResolver::class,
    App\Context\ServiceDiscoveryTrustResolver::class,
);
```

Other extension contracts include `IdGenerator`, `ContextEnricher`, `Carrier`, `TelemetryBridge` and `ExceptionContextReporter`.

## HTTP value semantics

Context values propagated through HTTP baggage are transport metadata and are reconstructed as strings on the receiving service. Keep cross-service context scalar, small, and type-agnostic; if your domain requires an integer or enum, validate and cast it at the application boundary. Queue propagation uses Laravel Context natively and therefore preserves serializable PHP value types.

## Security model

Context Flow guarantees policy enforcement around its own registered keys and transports. It does **not** guarantee authenticity of unsigned headers, does not replace authentication/authorization, and cannot stop application code from manually sending sensitive data in unrelated HTTP headers.

See [SECURITY.md](SECURITY.md) for the threat model.

## Development

```bash
composer install
composer test
php artisan context-flow:doctor
```

The GitHub Actions matrix tests Laravel 12/13 across compatible PHP versions.

## License

MIT.
