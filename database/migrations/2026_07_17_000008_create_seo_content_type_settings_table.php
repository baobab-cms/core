<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gabarit de titre SEO par Content Type adressable (spec 07 §2.2), ex.
 * `{title} — {site_name}` — une ligne par type, créée à la volée
 * (`firstOrNew`) plutôt qu'écrite au blueprint (le blueprint est régénéré
 * par le pipeline de Content Types, spec 02, pas un endroit où cet écran
 * admin doit écrire). Pas de colonne d'exclusion sitemap ici — ajoutée
 * quand le sitemap existera (Pass C du module SEO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_content_type_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_type_id')->unique()->constrained('content_types')->cascadeOnDelete();
            $table->string('title_template')->nullable();
            $table->timestamps();
            // SEO Pass C (spec 07 §5) : exclusion d'un Content Type entier du sitemap.
            $table->boolean('exclude_from_sitemap')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_content_type_settings');
    }
};
