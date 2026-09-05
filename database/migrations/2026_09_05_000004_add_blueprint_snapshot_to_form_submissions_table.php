<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complète une promesse de la Pass A restée sans mécanisme : `form_version`
 * fige le numéro de version, mais rien ne conservait les champs eux-mêmes tels
 * qu'ils étaient à la soumission (spec 14 §6.1 — « les libellés d'époque sont
 * résolus depuis la version archivée du blueprint »). Sans cette colonne, un
 * champ renommé ou supprimé après coup rendait la soumission illisible.
 * Relevé en construisant l'écran de consultation (M8 point 6, Pass B5), qui
 * est le premier consommateur réel de cette promesse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->json('blueprint_snapshot')->nullable()->after('form_version');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropColumn('blueprint_snapshot');
        });
    }
};
