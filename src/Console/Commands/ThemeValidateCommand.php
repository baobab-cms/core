<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Modules\ModuleDiscovery;
use Baobab\Themes\Validation\ThemeValidator;
use Illuminate\Console\Command;

/**
 * `php artisan baobab:theme:validate {name}` (spec 03 §10.3) — utilisable en
 * CI par les développeurs de thèmes : résout via ModuleDiscovery, pas besoin
 * d'installation préalable en base (contrairement au branchement dans
 * InstallModule, qui couvre l'installation réelle).
 */
final class ThemeValidateCommand extends Command
{
    protected $signature = 'baobab:theme:validate {name : The theme name (vendor/slug)}';

    protected $description = 'Validate a theme against the structural and static-analysis rules (spec 03 §10.3).';

    public function handle(ModuleDiscovery $discovery, ThemeValidator $validator): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        $discovered = $discovery->scan()->get($name);

        if ($discovered === null) {
            $this->error("Theme not found: {$name}.");

            return self::FAILURE;
        }

        if ($discovered->manifest->type() !== 'theme') {
            $this->error("Not a theme: {$name}.");

            return self::FAILURE;
        }

        $violations = $validator->validate($discovered->manifest, $discovered->path);

        if ($violations === []) {
            $this->info("Theme [{$name}] is valid.");

            return self::SUCCESS;
        }

        $this->table(
            ['File', 'Line', 'Severity', 'Message'],
            collect($violations)->map(fn ($v) => [
                $v->file,
                $v->line ?? '—',
                $v->blocking ? '<error>blocking</error>' : '<comment>warning</comment>',
                $v->message,
            ])->all(),
        );

        $blockingCount = collect($violations)->filter(fn ($v) => $v->blocking)->count();

        if ($blockingCount > 0) {
            $this->error("{$blockingCount} blocking violation(s).");

            return self::FAILURE;
        }

        $this->info('No blocking violation — warnings only.');

        return self::SUCCESS;
    }
}
