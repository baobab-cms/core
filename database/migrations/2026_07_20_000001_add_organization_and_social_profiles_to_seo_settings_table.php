<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO Pass D (spec 07 §7) : identité `Organization`/`Person` du bloc JSON-LD
 * global — `organization_type` (défaut `Organization`) et `social_profiles`
 * (une URL par ligne, mappées vers `sameAs`). Le logo n'a pas de colonne
 * dédiée : réutilise `BrandingSetting::current()->logo`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_settings', function (Blueprint $table): void {
            $table->string('organization_type')->default('Organization');
            $table->text('social_profiles')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seo_settings', function (Blueprint $table): void {
            $table->dropColumn(['organization_type', 'social_profiles']);
        });
    }
};
