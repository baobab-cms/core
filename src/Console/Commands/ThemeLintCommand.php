<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Illuminate\Console\Command;

/**
 * `php artisan baobab:theme:lint {name}` (spec 17 §6.1/§8) — alias de
 * `baobab:theme:validate` (spec 03 §10.3, même validateur : « le même
 * linter est exposé en `php artisan baobab:theme:lint {slug}` »). Conflit de
 * nommage entre les deux specs signalé et tranché avec l'utilisateur :
 * garder les deux noms plutôt que renommer la commande existante, aucune
 * spec amendée (suivi n° 88). Délègue intégralement, aucune logique dupliquée.
 */
final class ThemeLintCommand extends Command
{
    protected $signature = 'baobab:theme:lint {name : The theme name (vendor/slug)}';

    protected $description = 'Validate a theme against the structural and static-analysis rules (alias of baobab:theme:validate, spec 17 §6.1/§8).';

    public function handle(): int
    {
        /** @var int $exitCode */
        $exitCode = $this->call('baobab:theme:validate', ['name' => $this->argument('name')]);

        return $exitCode;
    }
}
