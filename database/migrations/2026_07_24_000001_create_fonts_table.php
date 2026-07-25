<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre de polices (spec 18 §5.1, M8 point 8 Pass B) : une police n'est
 * pas un média (§1 point 4) — registre dédié, jamais `media`/`media_usages`.
 * `source` est validé côté PHP (`bundled`/`theme`/`uploaded`), pas d'enum SQL
 * natif dans ce codebase (cf. `Baobab\Content\...` workflow status, même
 * choix). `files` porte les chemins relatifs sous `storage/app/baobab/fonts/
 * {slug}/`, résolus par `PublishFontAssets`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fonts', function (Blueprint $table): void {
            $table->id();
            $table->string('family');
            $table->string('slug')->unique();
            $table->string('source');
            $table->boolean('is_variable')->default(false);
            $table->json('axes')->nullable();
            $table->json('files');
            $table->string('license')->nullable();
            $table->string('license_file')->nullable();
            $table->boolean('license_attested')->default(false);
            $table->foreignId('theme_module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fonts');
    }
};
