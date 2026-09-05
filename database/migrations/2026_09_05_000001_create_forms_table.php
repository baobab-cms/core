<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->unsignedInteger('version')->default(1);
            $table->json('blueprint');
            $table->json('settings');
            $table->boolean('store_submissions')->default(true);
            $table->unsignedInteger('retention_days')->default(365);
            $table->boolean('retain_ip')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
