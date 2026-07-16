<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des emplacements de menus déclarés par le thème actif (spec 03
 * §7, M6 point 2) — le slot, pas son contenu : le constructeur de menus
 * (point 4) branchera l'assignation sur `key`. `is_active` distingue les
 * clés déclarées par le thème actuellement actif des clés orphelines
 * (déclarées par un thème précédent, conservées pour réassignation future
 * plutôt que supprimées).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_menu_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_menu_locations');
    }
};
