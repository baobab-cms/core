<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de lecture (spec 03 §4, amendement du 17 juillet 2026) : ligne
 * unique (id=1) gouvernant le mode de la page d'accueil (`static_page` ou
 * `latest_posts`) — patron exact `branding_settings`, volontairement pas le
 * framework générique de réglages par module (spec-admin §5.1). Pas de FK
 * classique vers l'entrée choisie (`page_entry_id`) : elle vit dans une
 * table `ct_*` dynamique par Content Type, jamais connue de ce module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('mode')->nullable();
            $table->string('page_content_type_key')->nullable();
            $table->unsignedBigInteger('page_entry_id')->nullable();
            $table->string('posts_content_type_key')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_settings');
    }
};
