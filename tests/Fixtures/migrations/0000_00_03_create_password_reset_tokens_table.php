<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixture testbench pour `password_reset_tokens` (spec 04 §9, décision 7) —
 * même rôle que `0000_00_02_create_sessions_table.php` : la table vit dans la
 * migration `create_users_table` du squelette, jamais chargée par le
 * testbench du package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
