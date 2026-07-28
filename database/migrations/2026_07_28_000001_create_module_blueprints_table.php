<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_blueprints', function (Blueprint $table): void {
            $table->id();
            $table->string('vendor_slug')->unique();
            $table->string('title');
            $table->unsignedInteger('blueprint_version')->default(1);
            $table->unsignedInteger('current_step')->default(1);
            $table->json('blueprint');
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_blueprints');
    }
};
