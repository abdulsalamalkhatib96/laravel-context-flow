<?php

namespace Abdulsalam\LaravelContextFlow\Contracts;

interface TelemetryBridge
{
    public function inject(Carrier $carrier): void;

    public function extract(Carrier $carrier): void;
}
