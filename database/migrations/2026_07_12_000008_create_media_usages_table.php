<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi d'usage d'un média (M4 point 3, spec 06 §5) — matérialise les
 * relations entre un média et les modèles qui l'utilisent (many-to-many
 * polymorphique). `field_key` identifie le champ d'origine (ex. un champ
 * `richtext` précis) quand plusieurs champs d'une même ligne peuvent
 * référencer des médias, pour pouvoir resynchroniser un champ sans toucher
 * aux usages des autres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->morphs('usable');
            $table->string('field_key')->nullable();
            // Position dans un champ `gallery` (M4 point 4b-ii, spec 06 §6) — un
            // usage produit par le scanner richtext reste à 0, sans ordre significatif.
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['media_id', 'usable_type', 'usable_id', 'field_key'], 'media_usages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
    }
};
