<?php

namespace Abdulsalam\LaravelContextFlow\Support;

final class ContextKeys
{
    public const CORRELATION_ID = 'context_flow.correlation_id';
    public const EXECUTION_ID = 'context_flow.execution_id';
    public const CAUSATION_ID = 'context_flow.causation_id';
    public const REQUEST_ID = 'context_flow.request_id';
    public const ORIGIN = 'context_flow.origin';
    public const BOUNDARY = 'context_flow.boundary';
    public const SERVICE = 'context_flow.service';
    public const ENVIRONMENT = 'context_flow.environment';
    public const TRUST = 'context_flow.trust';
    public const COMMAND = 'context_flow.command';
    public const SCHEDULE = 'context_flow.schedule';
    public const JOB_ID = 'context_flow.job.id';
    public const JOB_NAME = 'context_flow.job.name';
    public const JOB_QUEUE = 'context_flow.job.queue';
    public const JOB_CONNECTION = 'context_flow.job.connection';
    public const JOB_ATTEMPT = 'context_flow.job.attempt';

    public const PROPAGATION_DISABLED = 'context_flow.internal.propagation_disabled';
    public const ONLY_KEYS = 'context_flow.internal.only_keys';

    public static function transportKeys(): array
    {
        return [
            self::CORRELATION_ID,
            self::EXECUTION_ID,
            self::CAUSATION_ID,
            self::ORIGIN,
            self::SERVICE,
            self::ENVIRONMENT,
        ];
    }

    public static function allManaged(): array
    {
        return [
            ...self::transportKeys(),
            self::REQUEST_ID,
            self::BOUNDARY,
            self::TRUST,
            self::COMMAND,
            self::SCHEDULE,
            self::JOB_ID,
            self::JOB_NAME,
            self::JOB_QUEUE,
            self::JOB_CONNECTION,
            self::JOB_ATTEMPT,
        ];
    }
}
