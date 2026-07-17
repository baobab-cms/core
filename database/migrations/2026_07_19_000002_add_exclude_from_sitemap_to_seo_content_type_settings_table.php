<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEO Pass C (spec 07 §5) : exclusion d'un Content Type entier du sitemap
 * — déjà annoncée comme différée à cette passe (suivi n° 54/Pass A) plutôt
 * que d'ajouter une case inerte en attendant que le sitemap existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_content_type_settings', function (Blueprint $table): void {
            $table->boolean('exclude_from_sitemap')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('seo_content_type_settings', function (Blueprint $table): void {
            $table->dropColumn('exclude_from_sitemap');
        });
    }
};
