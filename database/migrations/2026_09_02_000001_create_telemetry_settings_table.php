<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consentement à la télémétrie (spec 15 §8 point 2).
 *
 * Ligne unique, patron exact `registration_settings`.
 *
 * **Le défaut est `false`, et ce n'est pas un détail d'implémentation** : le
 * §8 point 2 veut un opt-in explicite, décoché par défaut. Une base où
 * personne n'a jamais répondu à la question doit donc valoir « non » — pas
 * « on ne sait pas », pas « oui tant qu'on n'a rien dit ».
 *
 * **Pourquoi la base et non `.env`** (suivi n° 229, arbitrage A4) : un
 * consentement se retire. `.env` ne se modifie pas sans accès aux fichiers,
 * ce que le public visé n'a précisément pas, et `config:cache` le figerait
 * en plus. La spec 16 (RGPD) exigera de toute façon qu'il soit révocable
 * depuis l'administration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_settings');
    }
};
