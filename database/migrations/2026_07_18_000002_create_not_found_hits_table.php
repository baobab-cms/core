<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des 404 publics (spec 07 §4) : une ligne par chemin, compteur de
 * hits incrémenté à chaque nouvelle occurrence — pas une ligne par requête
 * (volume non borné sinon). Purgé automatiquement au-delà de
 * `baobab.redirects.not_found_retention_days` (`seo:purge-404-log`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('not_found_hits', function (Blueprint $table): void {
            $table->id();
            $table->string('path')->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->string('referer')->nullable();
            $table->timestamp('last_hit_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_hits');
    }
};
