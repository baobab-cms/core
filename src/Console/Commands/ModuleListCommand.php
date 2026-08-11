<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Modules\ModuleInventory;
use Baobab\Modules\ModuleInventoryEntry;
use Illuminate\Console\Command;

final class ModuleListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List all installed modules and discovered (not yet installed) ones.';

    public function handle(ModuleInventory $inventory): int
    {
        $entries = $inventory->all();

        if ($entries === []) {
            $this->info('No modules found.');

            return self::SUCCESS;
        }

        $rows = array_map(fn (ModuleInventoryEntry $entry): array => [
            $entry->name,
            $entry->title,
            $entry->version,
            $entry->type,
            $entry->status === 'discovered' ? '<comment>discovered</comment>' : $entry->status,
        ], $entries);

        $this->table(['Name', 'Title', 'Version', 'Type', 'Status'], $rows);

        return self::SUCCESS;
    }
}
