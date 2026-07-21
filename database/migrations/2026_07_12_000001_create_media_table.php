<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk');
            $table->string('path');
            $table->string('file_name');
            $table->string('mime_type');
            // Média externe (spec 06 §7.1, M4 point 4) : 'local' ou 'external'
            // (oEmbed), external_url portant l'URL d'origine.
            $table->string('source', 16)->default('local');
            $table->string('external_url', 2048)->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->string('caption')->nullable();
            $table->text('description')->nullable();
            $table->string('checksum', 64);
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('conversions')->nullable();
            $table->json('meta')->nullable();
            // Recadrage des presets `fit: crop` (M4 point 2a), 0.0–1.0, null = centre.
            $table->float('focal_x')->nullable();
            $table->float('focal_y')->nullable();
            // Édition non destructive (M4 point 2b, spec 06 §4.2) : edited_path
            // pointe vers la version courante d'un média édité sans jamais
            // toucher path (l'original vrai).
            $table->string('edited_path')->nullable();
            $table->unsignedInteger('edited_width')->nullable();
            $table->unsignedInteger('edited_height')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
