<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de marque (spec-admin.md §11.1, « version simple en v1 ») : ligne
 * unique (id=1), consommée par la topbar admin et par le layout e-mail (spec 13
 * §3.4). Volontairement pas le framework générique de réglages par module
 * (spec-admin §5.1) — un écran dédié, à portée unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branding_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('favicon_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('primary_color', 7)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_settings');
    }
};
