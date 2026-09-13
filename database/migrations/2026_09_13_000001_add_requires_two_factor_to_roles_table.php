<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglage associé de la Fiche rôle (spec 05 §5, M9 point 2 Pass 3.A, suivi
 * n° 297). Stockage et affichage seuls à ce stade — l'application réelle au
 * login (spec 04 §9, « imposable par rôle ») reste différée, cf. suivi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baobab_roles', function (Blueprint $table): void {
            $table->boolean('requires_two_factor')->default(false)->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('baobab_roles', function (Blueprint $table): void {
            $table->dropColumn('requires_two_factor');
        });
    }
};
