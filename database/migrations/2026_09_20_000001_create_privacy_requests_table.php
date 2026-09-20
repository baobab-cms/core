<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les demandes d'exercice des droits RGPD (spec 16 §4, décision 10, M9 0.b
 * Pass D1) : une table générique export/effacement, à **état grossier**
 * (`pending`/`running`/`completed`/`failed`/`expired`), patron `export_jobs`.
 * `uuid` est l'identifiant public. Le sujet est un compte et/ou une adresse
 * e-mail. `password` ne porte que le mot de passe chiffré d'une archive
 * d'export, jusqu'à sa première lecture. `origin` vaut `admin` ; le portail
 * public (Pass E) y posera `portal`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_email')->nullable()->index();
            $table->string('origin')->default('admin');
            $table->string('file_disk')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('password')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
