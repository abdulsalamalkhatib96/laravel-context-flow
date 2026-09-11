<?php

namespace Abdulsalam\LaravelContextFlow\Console;

use Abdulsalam\LaravelContextFlow\Context\ContextRegistry;
use Abdulsalam\LaravelContextFlow\ContextFlowManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Context;

final class InspectContextCommand extends Command
{
    protected $signature = 'context-flow:inspect';
    protected $description = 'Inspect the current Laravel Context Flow state and registered propagation keys';

    public function handle(ContextFlowManager $flow, ContextRegistry $registry): int
    {
        $this->components->info('Laravel Context Flow');

        $this->table(['Field', 'Value'], [
            ['Correlation ID', $flow->correlationId() ?? '—'],
            ['Execution ID', $flow->executionId() ?? '—'],
            ['Causation ID', $flow->causationId() ?? '—'],
            ['Request ID', $flow->requestId() ?? '—'],
            ['Boundary', $flow->boundary()->value],
            ['Trust', $flow->trustLevel()->value],
        ]);

        $rows = [];
        foreach ($registry->all() as $definition) {
            $rows[] = [
                $definition->name,
                implode(', ', array_map(static fn ($target) => $target->value, $definition->targets)) ?: 'local only',
                implode(', ', array_map(static fn ($trust) => $trust->value, $definition->acceptFrom)) ?: 'none',
                $definition->priority,
                Context::has($definition->name) ? 'present' : 'absent',
            ];
        }

        $this->newLine();
        $this->table(['Key', 'Targets', 'Accept from', 'Priority', 'Current'], $rows);

        return self::SUCCESS;
    }
}
