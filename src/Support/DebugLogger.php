<?php

namespace Abdulsalam\LaravelContextFlow\Support;

use Abdulsalam\LaravelContextFlow\Events\ContextBoundaryStarted;
use Abdulsalam\LaravelContextFlow\Events\ContextPropagated;
use Abdulsalam\LaravelContextFlow\Events\ContextRejected;
use Illuminate\Log\LogManager;

final readonly class DebugLogger
{
    public function __construct(private LogManager $log)
    {
    }

    public function boundary(ContextBoundaryStarted $event): void
    {
        $this->log->debug('context-flow boundary started', [
            'boundary' => $event->context->boundary->value,
            'correlation_id' => $event->context->correlationId,
            'execution_id' => $event->context->executionId,
            'causation_id' => $event->context->causationId,
        ]);
    }

    public function propagated(ContextPropagated $event): void
    {
        $this->log->debug('context-flow propagated', [
            'target' => $event->target->value,
            'destination' => $event->destination,
            'header_names' => $event->headerNames,
        ]);
    }

    public function rejected(ContextRejected $event): void
    {
        $this->log->debug('context-flow rejected context', [
            'reason' => $event->reason,
            'key' => $event->key,
        ]);
    }
}
