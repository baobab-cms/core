<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `focal_x`/`focal_y` (0.0–1.0, `null` = centre) pilotent le recadrage des
 * presets `fit: crop` (M4 point 2a) ; l'écran pour les régler arrive au point
 * 2b, mais le moteur de conversion doit déjà pouvoir les lire — même
 * raisonnement que `folder_id` au point 1a : éviter une migration
 * incrémentale de plus pour deux colonnes que la génération de variantes
 * utilise de toute façon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->float('focal_x')->nullable()->after('meta');
            $table->float('focal_y')->nullable()->after('focal_x');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn(['focal_x', 'focal_y']);
        });
    }
};
