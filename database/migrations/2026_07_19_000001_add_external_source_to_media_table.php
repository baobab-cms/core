<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Média externe (spec 06 §7.1, M4 point 4) : un média peut référencer une URL
 * tierce (YouTube, Vimeo...) résolue par oEmbed. `source` distingue les
 * fichiers locaux ('local') des références externes ('external') ;
 * `external_url` porte l'URL d'origine. Le payload oEmbed (titre, html du
 * lecteur, vignette) vit dans `meta`, extensible par nature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('source', 16)->default('local')->after('mime_type');
            $table->string('external_url', 2048)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn(['source', 'external_url']);
        });
    }
};
