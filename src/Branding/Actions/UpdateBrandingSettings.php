<?php

declare(strict_types=1);

namespace Baobab\Branding\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Facades\Hook;

/**
 * Met à jour la ligne unique de réglages de marque (spec-admin.md §11.1,
 * étendue spec 18 §8). Validation (couleur hex, médias existants, vocabulaire
 * de tokens) faite par l'appelant — cette Action persiste, recompile
 * l'artefact CSS et audite, comme le reste de la couche d'actions.
 *
 * Point d'écriture unique pour `primary_color` (spec 18 §2.4) : la colonne
 * reste l'alias historique consommé par les e-mails (qui ne peuvent pas lire
 * une variable CSS), toujours synchronisée avec `tokens.colors.primary` ici,
 * jamais ailleurs — aucune divergence possible entre les deux.
 */
final class UpdateBrandingSettings
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CompileDesignTokens $compile,
    ) {}

    /**
     * @param  array{logo_media_id?: int|null, favicon_media_id?: int|null, primary_color?: string|null, tokens?: array<string, array<string, string>>}  $data
     */
    public function __invoke(array $data): BrandingSetting
    {
        $setting = BrandingSetting::current();

        if (array_key_exists('primary_color', $data) && $data['primary_color'] !== null) {
            $data['tokens'] ??= $setting->tokens ?? [];
            $data['tokens']['colors']['primary'] = $data['primary_color'];
        }

        $setting->fill($data);
        $setting->save();

        $this->audit->record('branding.updated', $setting, $data);

        Hook::action('baobab.branding.updated', $setting);
        Hook::action('baobab.branding.tokens.saved', $setting);

        ($this->compile)();

        return $setting;
    }
}
