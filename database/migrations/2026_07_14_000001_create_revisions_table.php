<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des révisions de contenu (spec 09 §6, M5 point 2) : table
 * commune polymorphique, patron `audit_log`/`media_usages` — un snapshot
 * JSON complet des attributs à chaque sauvegarde effective, transition de
 * publication, ou juste avant restauration. `type` distingue manual/
 * autosave/working_draft/pre_restore ; pas de contrainte d'unicité en base
 * (« un seul autosave par utilisateur », « un seul working draft ») —
 * gérée au niveau des Actions, les deux règles n'ayant pas la même clé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('revisionable');
            $table->string('type');
            $table->json('snapshot');
            $table->string('summary')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['revisionable_type', 'revisionable_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
