<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de sauvegarde (spec 12 §4.1, §4.3 — M9 chantier 0.a Pass D) :
 * ligne unique (id=1), patron exact `seo_settings`/`branding_settings`.
 * Les trois colonnes de rétention sont nullables : `null` retombe sur
 * `config('baobab.backups.retention')` (BackupSetting::current(), jamais
 * ici) tant que rien n'a été réglé depuis l'écran — décision de séance du
 * 17 septembre 2026, spec 12 §4.1 amendée en conséquence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('retention_daily')->nullable();
            $table->unsignedInteger('retention_weekly')->nullable();
            $table->unsignedInteger('max_total_size_mb')->nullable();
            $table->boolean('scheduled_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
    }
};
