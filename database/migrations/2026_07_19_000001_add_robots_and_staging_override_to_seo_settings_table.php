<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO Pass C (spec 07 §8) : `robots_txt` (contenu personnalisé — la
 * protection staging le court-circuite entièrement quand elle est active,
 * jamais combinée) et `force_index_on_staging` (dérogation explicite,
 * spec : « réglage de dérogation si vraiment nécessaire »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_settings', function (Blueprint $table): void {
            $table->text('robots_txt')->nullable();
            $table->boolean('force_index_on_staging')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('seo_settings', function (Blueprint $table): void {
            $table->dropColumn(['robots_txt', 'force_index_on_staging']);
        });
    }
};
