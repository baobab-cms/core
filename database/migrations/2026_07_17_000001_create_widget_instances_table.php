<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instances de widgets assignées à une zone de thème (spec 10 §3.3).
 * `zone_key` n'est pas unique : plusieurs widgets peuvent occuper la même
 * zone, ordonnés par `order`. `widget_key` n'est pas une FK — le catalogue
 * des widgets disponibles vit en code (`WidgetRegistry`), pas en base.
 * `is_active = false` matérialise la zone virtuelle « Inactifs » de la
 * spec — l'instance garde son `zone_key` pour être restituée à sa place en
 * cas de réactivation, pas de colonne séparée pour ça.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_instances', function (Blueprint $table): void {
            $table->id();
            $table->string('zone_key');
            $table->string('widget_key');
            $table->json('settings')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->string('visibility')->default('everyone');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_instances');
    }
};
