<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Branding\Models\Font;
use Illuminate\Console\Command;

/**
 * Liste le registre de polices (spec 18 §5, §8) — patron `baobab:mail:templates`.
 */
final class FontsListCommand extends Command
{
    protected $signature = 'baobab:fonts:list';

    protected $description = 'Liste les polices du registre (bundled, theme, uploaded).';

    public function handle(): int
    {
        $fonts = Font::query()->orderBy('source')->orderBy('family')->get();

        if ($fonts->isEmpty()) {
            $this->line('Aucune police enregistrée.');

            return self::SUCCESS;
        }

        $rows = $fonts->map(fn (Font $font): array => [
            $font->family,
            $font->source,
            $font->is_variable ? 'oui' : 'non',
            $font->license ?? '—',
        ])->all();

        $this->table(['Famille', 'Source', 'Variable', 'Licence'], $rows);

        return self::SUCCESS;
    }
}
