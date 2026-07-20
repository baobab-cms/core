<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `docs_enabled` (spec 08 §7, M7 point 4b Pass B) — interrupteur dédié pour
 * l'écran `/api/docs`, patron exact `graphql_enabled`/`graphql_introspection_enabled` :
 * défaut colonne `true`, le vrai défaut env-dépendant est posé par
 * `ApiSetting::current()` (« activée en local, sous permission en production »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_settings', function (Blueprint $table): void {
            $table->boolean('docs_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('api_settings', function (Blueprint $table): void {
            $table->dropColumn('docs_enabled');
        });
    }
};
