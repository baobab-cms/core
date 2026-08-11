<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration jouée **après** l'installation d'un module, pour reproduire la
 * situation réelle : les migrations du module ne sont plus dans le dernier lot.
 * Volontairement hors de `tests/Fixtures/migrations` (chargé au démarrage par
 * TestCase) — elle ne doit s'exécuter que là où un test le demande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unrelated_after_module', function (Blueprint $table): void {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unrelated_after_module');
    }
};
