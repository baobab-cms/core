<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages API (spec 08 §4.3, M7 point 2 Pass B) : ligne unique (id=1),
 * patron exact `reading_settings`/`branding_settings`. `rest_enabled`
 * (interrupteur global), `rate_limit_per_minute` (défaut global — pas
 * d'override par-token pour cette version, décidé en Pass A),
 * `allowed_origins` (une origine par ligne, patron `seo_settings.social_profiles`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('rest_enabled')->default(true);
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->text('allowed_origins')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_settings');
    }
};
