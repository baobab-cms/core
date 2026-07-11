<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('table_name')->unique();
            $table->boolean('is_addressable')->default(false);
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('blueprint');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_types');
    }
};
