<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi d'un export de contenu (spec 12 §5.2, cadrage Pass F1, suivi n° 326,
 * §12 décision 9) : exécution en job asynchrone (queue `baobab-low`) à
 * **état grossier** — `pending`/`running`/`completed`/`failed`, pas de
 * pourcentage fin (aucun précédent de progression détaillée dans le Core).
 * `uuid` est l'identifiant public (route model binding, patron `FailedJob`),
 * jamais `id`. `content_type_keys` porte la sélection demandée, `file_path`
 * n'est renseigné qu'à `completed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status')->default('pending');
            $table->json('content_type_keys');
            $table->string('file_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
