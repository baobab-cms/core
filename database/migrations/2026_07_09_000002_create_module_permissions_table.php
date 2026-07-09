<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->json('default_roles')->nullable();
            $table->timestamps();

            $table->unique(['module_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_permissions');
    }
};
