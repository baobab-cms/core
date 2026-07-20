<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des livraisons de webhooks (spec 08 §5) : une ligne par
 * *tentative* (pas par événement) — le premier essai et chaque retry
 * automatique/manuel y figurent séparément, historique complet visible
 * depuis `admin/webhooks/{subscription}/deliveries`. `payload` porte le
 * corps JSON réellement envoyé (snapshot, jamais reconstruit après coup) :
 * une re-livraison manuelle doit renvoyer exactement ce qui a été tenté,
 * même si l'entité source a disparu depuis. `response_body` tronqué à
 * l'écriture (`Baobab\Webhooks\Jobs\DeliverWebhook`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->unsignedInteger('attempt');
            $table->json('payload');
            $table->string('status');
            $table->unsignedInteger('response_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
