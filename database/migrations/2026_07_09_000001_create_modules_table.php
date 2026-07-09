<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('title');
            $table->string('type');
            $table->string('version');
            $table->string('provider');
            $table->string('source');
            $table->string('path');
            $table->json('manifest');
            $table->string('status')->default('installed');
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
