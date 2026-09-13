<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 05 §6.3 : permissions directes, exceptionnelles et tracées. Le pivot
 * Spatie (`baobab_model_has_permissions`) ne porte que la relation
 * elle-même — ni justification, ni auteur, ni date. Cette table suit l'état
 * courant des exceptions en vigueur (une ligne par grant actif, supprimée
 * au revoke) ; le journal d'audit reste l'historique des événements, celle-ci
 * est la source pour l'écran de revue (M9 point 2, Pass 3.C, suivi n° 297).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baobab_direct_permission_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('baobab_permissions')->cascadeOnDelete();
            $table->text('justification');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baobab_direct_permission_grants');
    }
};
