<?php

namespace Abdulsalam\LaravelContextFlow\OpenTelemetry;

use Abdulsalam\LaravelContextFlow\Contracts\Carrier;
use Abdulsalam\LaravelContextFlow\Contracts\TelemetryBridge;

final class NullTelemetryBridge implements TelemetryBridge
{
    public function inject(Carrier $carrier): void
    {
        // Intentionally empty. OpenTelemetry instrumentation should own traceparent/tracestate.
    }

    public function extract(Carrier $carrier): void
    {
        // Intentionally empty.
    }
}
