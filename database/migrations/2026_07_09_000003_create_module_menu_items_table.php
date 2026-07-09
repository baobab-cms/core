<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('module_menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->string('route')->nullable();
            $table->string('permission')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_menu_items');
    }
};
