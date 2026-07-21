<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages SEO globaux (spec 07 §2.2 dernière phrase) : ligne unique
 * (id=1), patron exact `branding_settings`. `site_name` vide retombe sur
 * `config('app.name')` (ComposeSeoMeta) — jamais stocké en dur ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('site_name')->nullable();
            $table->string('title_separator')->default('—');
            $table->foreignId('default_share_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->text('default_meta_description')->nullable();
            $table->timestamps();
            // SEO Pass C (spec 07 §8) : robots_txt personnalisé — la protection
            // staging le court-circuite entièrement quand elle est active, jamais
            // combinée ; force_index_on_staging est une dérogation explicite.
            $table->text('robots_txt')->nullable();
            $table->boolean('force_index_on_staging')->default(false);
            // SEO Pass D (spec 07 §7) : identité Organization/Person du bloc
            // JSON-LD global. Le logo n'a pas de colonne dédiée : réutilise
            // BrandingSetting::current()->logo.
            $table->string('organization_type')->default('Organization');
            $table->text('social_profiles')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_settings');
    }
};
