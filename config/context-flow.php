<?php

use Abdulsalam\LaravelContextFlow\Support\UuidV7Generator;

return [
    'enabled' => env('CONTEXT_FLOW_ENABLED', true),

    'service' => env('CONTEXT_FLOW_SERVICE', env('APP_NAME', 'laravel')),
    'environment' => env('CONTEXT_FLOW_ENVIRONMENT', env('APP_ENV', 'production')),

    'ids' => [
        'generator' => UuidV7Generator::class,
    ],

    'headers' => [
        'correlation_id' => 'X-Correlation-ID',
        'causation_id' => 'X-Causation-ID',
        'request_id' => 'X-Request-ID',
        'baggage' => 'baggage',
        'signature' => 'X-Context-Signature',
        'timestamp' => 'X-Context-Timestamp',
    ],

    'http' => [
        'incoming' => true,
        'outgoing' => true,
        'response_headers' => true,
        'accept_public_correlation_id' => true,
        'trusted_hosts' => [
            '*.internal',
            '*.svc.cluster.local',
        ],
        'partner_hosts' => [],
    ],

    'queue' => [
        'enabled' => true,
        'rotate_execution_id' => true,
        'include_job_metadata' => true,
        // Preserve unknown Laravel Context keys for compatibility with other packages.
        // Registered keys still obey this package's explicit target policy.
        'unregistered_keys' => 'preserve', // preserve|drop
    ],

    'console' => [
        'enabled' => true,
    ],

    'scheduler' => [
        'enabled' => true,
    ],

    'security' => [
        'default_remote_policy' => 'deny',
        'sign_internal_context' => env('CONTEXT_FLOW_SIGN_INTERNAL', false),
        'signing_key' => env('CONTEXT_FLOW_SIGNING_KEY'),
        'timestamp_tolerance_seconds' => 60,
        'sensitive_key_patterns' => [
            '/password/i',
            '/passwd/i',
            '/secret/i',
            '/token/i',
            '/api[_-]?key/i',
            '/authorization/i',
            '/cookie/i',
            '/session/i',
            '/cvv/i',
            '/private[_-]?key/i',
        ],
    ],

    'limits' => [
        'max_keys' => 32,
        'key_bytes' => 128,
        'value_bytes' => 1024,
        'http_total_bytes' => 4096,
        'overflow' => env('CONTEXT_FLOW_OVERFLOW', env('APP_ENV', 'production') === 'production' ? 'drop_low_priority' : 'throw'),
    ],

    // Application-defined keys. Nothing is propagated over the network unless
    // it is explicitly registered here.
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

    'enrichers' => [],
    'debug' => env('CONTEXT_FLOW_DEBUG', false),
];
