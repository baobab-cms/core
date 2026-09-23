<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitation en attente (spec 05 §5, décision 5) : renseignée à l'invitation,
 * vidée dès que l'invité choisit son mot de passe. Nullable, aucune ligne
 * existante n'est touchée — un compte existant n'a jamais été invité.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'invited_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('invited_at')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('invited_at');
        });
    }
};
