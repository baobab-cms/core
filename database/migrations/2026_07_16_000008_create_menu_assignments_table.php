<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assignation menu ↔ emplacement de thème (spec 10 §2.1). `location_key`
 * unique : un emplacement pointe vers au plus un menu à la fois ; un même
 * menu peut en revanche occuper plusieurs emplacements (§2.3 « le même
 * menu en header et footer »). Conservées à la désactivation d'un thème
 * (spec 03 §7) — jamais purgées ici, seul `ThemeMenuLocation.is_active`
 * (point 2) marque un emplacement orphelin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->string('location_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_assignments');
    }
};
