<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design tokens (spec 18, M8 point 8 Pass A) : `tokens` porte uniquement les
 * écarts admin par rapport au niveau inférieur de la cascade (§3.3), jamais
 * le vocabulaire complet — réinitialiser un token supprime sa clé. `brand_profile`
 * est prêt pour Pass B (profils de marque) mais n'est consommé par rien encore.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branding_settings', function (Blueprint $table): void {
            $table->json('tokens')->nullable()->after('primary_color');
            $table->string('brand_profile')->nullable()->after('tokens');
        });
    }

    public function down(): void
    {
        Schema::table('branding_settings', function (Blueprint $table): void {
            $table->dropColumn(['tokens', 'brand_profile']);
        });
    }
};
