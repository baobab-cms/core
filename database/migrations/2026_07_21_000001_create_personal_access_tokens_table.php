<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table dédiée `baobab_personal_access_tokens` (pas `personal_access_tokens`,
 * table par défaut de Sanctum) — même rationale que le renommage des tables
 * Spatie (`registerSpatieConfig()`) : un package installé comme dépendance
 * ne doit jamais risquer de collisionner avec l'usage propre de Sanctum de
 * l'application hôte. Copiée depuis
 * `vendor/laravel/sanctum/database/migrations/..._create_personal_access_tokens_table.php`
 * plutôt que publiée dans l'app hôte (spec 08 §4.1, M7 point 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baobab_personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baobab_personal_access_tokens');
    }
};
