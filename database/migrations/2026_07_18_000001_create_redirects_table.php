<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redirections (spec 07 §3-4) : historique de slug (301 automatique au
 * renommage d'un contenu publié, `source_kind: 'auto'`) et gestionnaire
 * manuel (`source_kind: 'manual'`) partagent cette même table — même
 * mécanisme de résolution (`ResolveRedirectTarget`), seul l'affichage
 * distingue les deux dans l'admin. `source` porte directement un `*` pour
 * un motif joker (`/ancien-blog/*`) — pas de colonne booléenne séparée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('source')->unique();
            $table->string('target');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->string('source_kind')->default('manual');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
