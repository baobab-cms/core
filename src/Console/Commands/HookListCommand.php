<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Hooks\HookRegistry;
use Illuminate\Console\Command;

final class HookListCommand extends Command
{
    protected $signature = 'hook:list';

    protected $description = 'List all registered hook listeners (actions and filters).';

    public function handle(HookRegistry $registry): int
    {
        $rows = [];

        foreach ($registry->actions() as $name => $entries) {
            foreach ($entries as $entry) {
                $rows[] = [$name, 'action', $entry['priority'], $entry['listener']];
            }
        }

        foreach ($registry->filters() as $name => $entries) {
            foreach ($entries as $entry) {
                $rows[] = [$name, 'filter', $entry['priority'], $entry['listener']];
            }
        }

        if (empty($rows)) {
            $this->info('No hooks registered.');

            return self::SUCCESS;
        }

        usort($rows, fn (array $a, array $b) => [$a[0], $a[2]] <=> [$b[0], $b[2]]);

        $this->table(['Hook', 'Type', 'Priority', 'Listener'], $rows);

        return self::SUCCESS;
    }
}
