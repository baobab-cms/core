<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arbre par liste d'adjacence (spec 10 §2.1) — `parent_id`/`order`, profondeur
 * bornée validée en application (`SaveMenuItems`), pas en base. `type`
 * discrimine la référence effective : `content` (polymorphe `linkable_*`,
 * patron `media_usages.usable_*`), `archive` (`content_type_key`),
 * `custom_link` (`url`), `section` (aucune référence, non cliquable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('type');
            $table->nullableMorphs('linkable');
            $table->string('content_type_key')->nullable();
            $table->string('url')->nullable();
            $table->string('label')->nullable();
            $table->string('target')->default('_self');
            $table->string('css_class')->nullable();
            $table->string('icon')->nullable();
            $table->string('visibility')->default('everyone');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
