<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Édition non destructive (M4 point 2b, spec 06 §4.2) : `edited_path` pointe
 * vers la version courante d'un média édité (recadrage/rotation/retournement)
 * sans jamais toucher `path` (l'original vrai). `edited_width`/`edited_height`
 * décrivent cette version courante. Trois colonnes nullable : tant qu'aucune
 * édition n'existe, tout retombe sur l'original (`edited_path ?? path`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('edited_path')->nullable()->after('focal_y');
            $table->unsignedInteger('edited_width')->nullable()->after('edited_path');
            $table->unsignedInteger('edited_height')->nullable()->after('edited_width');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn(['edited_path', 'edited_width', 'edited_height']);
        });
    }
};
