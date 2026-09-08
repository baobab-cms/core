<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les 3 polices d'identité Core (spec 18 §13.5.1, §5.3 source `bundled`) :
 * fichiers vendored sous `resources/fonts/{slug}/` (extraits des paquets
 * `@fontsource-*` en développement, jamais une dépendance runtime — décision
 * D6, §12), publiés à l'exécution par `PublishFontAssets`. `license_attested`
 * à `true` : Core-authored, aucune attestation utilisateur à recueillir pour
 * ses propres assets.
 */
return new class extends Migration
{
    private const string SLUG_BRICOLAGE = 'bricolage-grotesque';

    private const string SLUG_FIGTREE = 'figtree';

    private const string SLUG_JETBRAINS_MONO = 'jetbrains-mono';

    public function up(): void
    {
        $now = now();

        DB::table('fonts')->insert([
            [
                'family' => 'Bricolage Grotesque Variable',
                'slug' => self::SLUG_BRICOLAGE,
                'source' => 'bundled',
                'is_variable' => true,
                'axes' => json_encode(['wght' => '200..800']),
                'files' => json_encode(['variable' => 'bricolage-grotesque-variable.woff2']),
                'license' => 'OFL-1.1',
                'license_file' => 'LICENSE',
                'license_attested' => true,
                'theme_module_id' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'family' => 'Figtree Variable',
                'slug' => self::SLUG_FIGTREE,
                'source' => 'bundled',
                'is_variable' => true,
                'axes' => json_encode(['wght' => '300..900']),
                'files' => json_encode(['variable' => 'figtree-variable.woff2']),
                'license' => 'OFL-1.1',
                'license_file' => 'LICENSE',
                'license_attested' => true,
                'theme_module_id' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'family' => 'JetBrains Mono',
                'slug' => self::SLUG_JETBRAINS_MONO,
                'source' => 'bundled',
                'is_variable' => false,
                'axes' => null,
                'files' => json_encode(['400' => 'jetbrains-mono-400.woff2', '700' => 'jetbrains-mono-700.woff2']),
                'license' => 'OFL-1.1',
                'license_file' => 'LICENSE',
                'license_attested' => true,
                'theme_module_id' => null,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('fonts')->whereIn('slug', [
            self::SLUG_BRICOLAGE,
            self::SLUG_FIGTREE,
            self::SLUG_JETBRAINS_MONO,
        ])->delete();
    }
};
