<?php

namespace Abdulsalam\LaravelContextFlow\Console;

use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Abdulsalam\LaravelContextFlow\Enums\BoundaryType;
use Abdulsalam\LaravelContextFlow\Support\ContextKeys;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Context;

final readonly class SchedulerContextBridge
{
    public function __construct(private ContextFlowManager $flow, private Config $config)
    {
    }

    public function starting(ScheduledTaskStarting $event): void
    {
        if (! (bool) $this->config->get('context-flow.scheduler.enabled', true)) {
            return;
        }

        $this->flow->startRoot(BoundaryType::Scheduler);
        Context::add(ContextKeys::SCHEDULE, $this->description($event->task));
    }

    public function finished(ScheduledTaskFinished|ScheduledTaskFailed $event): void
    {
        $this->flow->resetManagedContext();
    }

    private function description(object $task): string
    {
        if (property_exists($task, 'description') && is_string($task->description) && $task->description !== '') {
            return $task->description;
        }

        if (method_exists($task, 'getSummaryForDisplay')) {
            return (string) $task->getSummaryForDisplay();
        }

        return $task::class;
    }
}
