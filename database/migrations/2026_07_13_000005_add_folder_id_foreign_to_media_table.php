<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `media.folder_id` existe depuis M4 point 1a (sans contrainte : media_folders
 * n'existait pas encore, décision validée pour éviter une migration
 * incrémentale pour une seule colonne). La table existe maintenant (M4 point
 * 1b) : on ajoute la FK. `nullOnDelete` — supprimer un dossier orpheline ses
 * médias vers la racine plutôt que de les supprimer (spec 06 §2 : un dossier
 * est une étiquette d'organisation, pas un conteneur possessif).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->foreign('folder_id')->references('id')->on('media_folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropForeign(['folder_id']);
        });
    }
};
