<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verrouillage d'édition (spec 09 §7, M5 point 2) : un verrou par contenu
 * (unique sur `lockable_type`/`lockable_id`), entretenu par heartbeat
 * (`updated_at` touché toutes les 30 s) — expiré silencieusement si aucun
 * heartbeat depuis `baobab.content.lock_expiry_seconds`. Ligne supprimée à
 * la libération ou à la prise de main, jamais soft-deleted : c'est un état
 * éphémère, pas un historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_locks', function (Blueprint $table): void {
            $table->id();
            $table->morphs('lockable');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lockable_type', 'lockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_locks');
    }
};
