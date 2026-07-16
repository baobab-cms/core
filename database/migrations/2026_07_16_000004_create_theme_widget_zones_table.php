<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des zones de widgets déclarées par le thème actif — même rôle et
 * même sémantique `is_active` que `theme_menu_locations` (spec 03 §7, M6
 * point 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_widget_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_widget_zones');
    }
};
