<?php

namespace Abdulsalam\LaravelContextFlow\Console;

use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Abdulsalam\LaravelContextFlow\Enums\BoundaryType;
use Abdulsalam\LaravelContextFlow\Support\ContextKeys;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Context;

final readonly class ConsoleContextBridge
{
    public function __construct(private ContextFlowManager $flow, private Config $config)
    {
    }

    public function starting(CommandStarting $event): void
    {
        if (! (bool) $this->config->get('context-flow.console.enabled', true)) {
            return;
        }

        $this->flow->ensureRoot(BoundaryType::Console);
        Context::add(ContextKeys::COMMAND, $event->command);
    }

    public function finished(CommandFinished $event): void
    {
        Context::forget(ContextKeys::COMMAND);
    }
}
