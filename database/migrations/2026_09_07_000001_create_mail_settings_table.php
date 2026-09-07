<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de transport e-mail (spec 13 §2.1, suivi n° 187) : ligne unique
 * (id=1), patron exact `api_settings`/`branding_settings`. `credentials`
 * porte les identifiants propres au `mailer` choisi (host/port/user/password
 * pour `smtp` aujourd'hui) — un JSON dont la forme dépend du driver plutôt
 * que des colonnes dédiées par provider, patron `forms.settings.anti_spam.captcha`
 * (Pass D3) : ajouter un provider demain n'exigera aucune migration.
 *
 * Absente tant qu'aucun admin n'a visité l'écran : `BaobabServiceProvider`
 * ne surcharge alors rien, `.env`/`config/mail.php` restent seuls maîtres —
 * comportement actuel inchangé, jamais de régression silencieuse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('mailer')->default('smtp');
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->json('credentials')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
