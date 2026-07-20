<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages GraphQL (spec 08 §3.3, M7 point 3 Pass B), additifs sur
 * `api_settings` (patron des colonnes `rest_enabled`/`rate_limit_per_minute`
 * déjà en place) : `graphql_enabled` (interrupteur dédié, distinct de
 * `rest_enabled` — les deux familles REST/GraphQL sont indépendantes),
 * `graphql_introspection_enabled` (activée hors production par défaut —
 * cohérent avec le défaut calculé par `ApiSetting::current()`, cette colonne
 * ne sert en pratique que si la ligne est créée hors du flux applicatif
 * habituel, ex. seed/import direct).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_settings', function (Blueprint $table): void {
            $table->boolean('graphql_enabled')->default(true);
            $table->boolean('graphql_introspection_enabled')->default(! app()->environment('production'));
        });
    }

    public function down(): void
    {
        Schema::table('api_settings', function (Blueprint $table): void {
            $table->dropColumn(['graphql_enabled', 'graphql_introspection_enabled']);
        });
    }
};
