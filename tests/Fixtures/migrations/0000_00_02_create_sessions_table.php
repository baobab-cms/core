<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixture testbench pour `sessions` (M9 point 2 Pass 3.D) — même rôle que
 * `0000_00_00_create_test_users_table.php` : la vraie migration racine
 * (`database/migrations/2026_09_13_000003_create_sessions_table.php`) n'est
 * jamais chargée par le testbench du package, qui recrée son propre
 * squelette minimal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
