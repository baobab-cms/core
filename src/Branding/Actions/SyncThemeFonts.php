<?php

declare(strict_types=1);

namespace Baobab\Branding\Actions;

use Baobab\Branding\Models\Font;
use Baobab\Branding\Support\PublishFontAssets;
use Baobab\Modules\Models\Module;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Enregistre les polices embarquées par un thème (spec 18 §5.3, §6.1,
 * `theme.json` bloc `fonts`) — patron exact `SyncThemeLocations`. Les
 * fichiers, déclarés relatifs au thème, sont copiés vers le stockage central
 * du registre (`storage/app/baobab/fonts/{slug}/`) plutôt que servis depuis
 * le chemin public propre du thème (`PublishThemeAssets`) : le registre
 * reste l'unique source servie par `CompileDesignTokens`, indépendamment du
 * cycle de vie du thème qui les a déclarées (une désactivation ne supprime
 * jamais les lignes, cf. §5.3 dernière phrase).
 */
final class SyncThemeFonts
{
    public function __construct(private readonly PublishFontAssets $publish) {}

    public function __invoke(Module $theme): void
    {
        /** @var list<array{family: string, files: array<string, string>, license?: string}> $declared */
        $declared = $theme->manifest['fonts'] ?? [];

        if ($declared === []) {
            return;
        }

        foreach ($declared as $declaration) {
            $this->syncOne($theme, $declaration);
        }

        ($this->publish)();
    }

    /**
     * @param  array{family: string, files: array<string, string>, license?: string}  $declaration
     */
    private function syncOne(Module $theme, array $declaration): void
    {
        $family = $declaration['family'];
        $slug = Str::slug($family);
        $files = $declaration['files'];

        $destination = storage_path("app/baobab/fonts/{$slug}");
        File::ensureDirectoryExists($destination);

        $storedFiles = [];

        foreach ($files as $key => $relativePath) {
            $source = rtrim(str_replace('\\', '/', $theme->path), '/')."/{$relativePath}";

            if (! is_file($source)) {
                continue;
            }

            $fileName = basename($relativePath);
            File::copy($source, "{$destination}/{$fileName}");
            $storedFiles[$key] = $fileName;
        }

        Font::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'family' => $family,
                'source' => Font::SOURCE_THEME,
                'is_variable' => array_key_exists('variable', $storedFiles),
                'axes' => null,
                'files' => $storedFiles,
                'license' => $declaration['license'] ?? null,
                'license_file' => null,
                'license_attested' => true,
                'theme_module_id' => $theme->id,
                'created_by' => null,
            ],
        );
    }
}
