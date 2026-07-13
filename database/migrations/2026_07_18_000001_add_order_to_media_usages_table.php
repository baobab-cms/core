<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonne `order` (M4 point 4b-ii, spec 06 §6) — position d'un média dans un
 * champ `gallery` (sélection ordonnée). Un usage produit par le scanner
 * richtext n'a pas d'ordre significatif : il reste à la valeur par défaut 0,
 * comportement inchangé pour ces usages-là.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_usages', function (Blueprint $table): void {
            $table->unsignedInteger('order')->default(0)->after('field_key');
        });
    }

    public function down(): void
    {
        Schema::table('media_usages', function (Blueprint $table): void {
            $table->dropColumn('order');
        });
    }
};
