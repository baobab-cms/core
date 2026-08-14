<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instantané du blueprint tel qu'il a été généré la dernière fois (suivi
 * n° 111). `blueprint` porte le brouillon **en cours de saisie**, réécrit à
 * chaque étape du wizard ; il ne dit donc rien de ce qui est réellement sur
 * disque et en base.
 *
 * Cet instantané ne sert pas de terme de comparaison pour le diff — c'est le
 * schéma réel de la base qui joue ce rôle — mais de **frontière de propriété** :
 * il dit quelles colonnes le générateur a écrites, donc lesquelles il peut
 * légitimement supprimer. Une colonne ajoutée à la main par un développeur dans
 * sa propre migration n'y figure pas et n'est jamais touchée (spec 01 §5.4).
 *
 * Nullable, et sans reprise des lignes existantes : un module généré avant cette
 * migration n'a pas d'instantané, et lui en fabriquer un depuis `blueprint`
 * enregistrerait comme « déjà généré » un brouillon qui a pu être édité depuis.
 * L'absence d'instantané dégrade proprement — le module gagne ses colonnes
 * manquantes et ses nullabilités corrigées, mais rien n'y est jamais supprimé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_blueprints', function (Blueprint $table): void {
            $table->json('generated_blueprint')->nullable()->after('blueprint');
        });
    }

    public function down(): void
    {
        Schema::table('module_blueprints', function (Blueprint $table): void {
            $table->dropColumn('generated_blueprint');
        });
    }
};
