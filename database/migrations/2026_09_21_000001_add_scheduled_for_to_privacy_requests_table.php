<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Délai de grâce de l'effacement (spec 16 §4.3, décision 12, M9 0.b Pass D2) :
 * `scheduled_for` porte l'échéance d'une demande d'effacement `scheduled`.
 * Les statuts `scheduled` et `cancelled` n'exigent aucune migration — `status`
 * est une chaîne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privacy_requests', function (Blueprint $table): void {
            $table->timestamp('scheduled_for')->nullable()->after('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('privacy_requests', function (Blueprint $table): void {
            $table->dropColumn('scheduled_for');
        });
    }
};
