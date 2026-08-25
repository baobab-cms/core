<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscription front (spec 05 §8, décision 3 : ouverte par défaut).
 *
 * Ligne unique, patron exact `reading_settings` — volontairement pas le
 * framework générique de réglages par module (spec-admin §5.1), toujours
 * différé.
 *
 * **Pourquoi une table pour un seul booléen** (suivi n° 214) : nom du site,
 * URL et fuseau horaire vivent dans `.env`, où Laravel les possède déjà et où
 * l'installateur écrit de toute façon ; les dupliquer en base créerait deux
 * vérités sur le nom du site. L'inscription, elle, doit être basculable depuis
 * l'admin sans éditer un fichier — ce que `.env` ne permet pas à un
 * administrateur qui n'a ni shell ni FTP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('open')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_settings');
    }
};
