<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personnalisation admin des templates d'e-mails (spec 13 §3.2). La table ne
 * porte QUE les personnalisations : un template non personnalisé n'a pas de
 * ligne, et le défaut du code reste la source — c'est ce qui rend « Restaurer
 * le défaut » gratuit (supprimer la ligne) et le défaut toujours disponible.
 *
 * `default_snapshot` est le défaut du code **tel qu'il était au moment de
 * l'enregistrement**, pas le défaut courant : c'est la base de comparaison qui
 * permet de détecter qu'une mise à jour de module a changé le défaut d'un
 * template personnalisé, et d'en afficher le diff sans jamais écraser la
 * version de l'admin (§3.2).
 *
 * Une seule locale pour l'instant — pas de colonne `locale` (§3.5 différé
 * depuis le M5, suivi n° 45) : une colonne inutilisée serait de la
 * spéculation, et la locale s'ajoutera par une migration incrémentale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('subject');
            $table->text('body');
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->json('default_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
