<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compte désactivé par un admin (spec 05 §5, décision 5 k) : `deactivated_at`
 * est renseignée tant que le compte est bloqué, `deactivation_reason` porte le
 * motif facultatif. Les deux sont vidées à la réactivation ; l'audit garde
 * l'historique. Nullables, aucune ligne existante n'est touchée — un compte
 * existant n'a jamais été désactivé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'deactivated_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('deactivated_at')->nullable()->after('invited_at');
            $table->string('deactivation_reason', 500)->nullable()->after('deactivated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['deactivated_at', 'deactivation_reason']);
        });
    }
};
