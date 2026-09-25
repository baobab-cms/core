<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demande de changement de nom ou d'e-mail en attente de confirmation
 * (spec 05 §5, décision 5 f-g et j). Une seule ligne par compte : une
 * nouvelle demande remplace la précédente, dont le jeton cesse de valoir.
 *
 * Le jeton n'est jamais stocké en clair (`token_hash`, SHA-256) et change à
 * chaque étape : le lien envoyé à l'ancienne adresse ne sert pas à confirmer
 * la nouvelle. Table neuve — aucune donnée existante n'est touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_profile_changes')) {
            return;
        }

        Schema::create('user_profile_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('new_name')->nullable();
            $table->string('new_email')->nullable();
            $table->string('stage', 16);
            $table->boolean('forced')->default(false);
            $table->text('justification')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profile_changes');
    }
};
