<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Métadonnées SEO par contenu (spec 07 §2.1) : polymorphique
 * (`seoable_type`/`seoable_id`), une ligne par entrée de Content Type
 * adressable qui a reçu au moins un champ de sa metabox. Aucune colonne
 * dans les tables `ct_*` (spec 07 §1) — le moteur de contenu ne connaît pas
 * le SEO. `og_image_media_id` suit le patron `image`/`file` (nullOnDelete,
 * pas la protection à la suppression de `gallery` — suivi n° 38).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table): void {
            $table->id();
            $table->morphs('seoable');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('robots_noindex')->default(false);
            $table->boolean('robots_nofollow')->default(false);
            $table->string('canonical_url')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->foreignId('og_image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
