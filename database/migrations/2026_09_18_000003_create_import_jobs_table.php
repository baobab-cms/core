<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi de l'exécution réelle d'un import de contenu (spec 12 §5.3, cadrage
 * Pass F2, suivi n° 328, §12 décision 11) — le dry-run, lui, reste synchrone
 * et n'a pas de ligne ici (aucun état à suivre, la requête porte directement
 * le rapport). `archive_path` est toujours un chemin absolu réel du disque
 * local — l'admin y dépose l'archive téléversée lors du dry-run (réutilisée
 * telle quelle à l'exécution), le CLI y résout le chemin donné en argument ;
 * aucune abstraction Storage nécessaire pour ce point précis, `ZipArchive`
 * exige de toute façon un chemin réel. `report` porte le résultat une fois
 * le job terminé (patron `ImportReport` sérialisé), pour affichage sans
 * rejouer l'archive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status')->default('pending');
            $table->string('archive_path');
            $table->string('strategy');
            $table->json('report')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
