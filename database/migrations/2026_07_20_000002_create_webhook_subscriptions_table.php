<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnements webhooks sortants (spec 08 §5, M7 point 4 Pass A) : une ligne
 * par abonnement, patron `redirects` (liste, pas un singleton de réglages).
 * `secret` chiffré au repos (`encrypted` cast, modèle) — premier secret de
 * cette nature dans le Core. `text`, pas `string` : le texte chiffré
 * (base64, AES-256-CBC + tag + IV) dépasse largement 255 caractères même
 * pour un secret court en clair, découvert en testant contre MySQL (SQLite,
 * utilisé par la suite Pest, n'impose aucune limite réelle sur `VARCHAR` et
 * ne l'aurait jamais révélé). `events` porte les noms de hooks souscrits
 * (`Baobab\Webhooks\Support\WebhookEventCatalog`). `consecutive_failures`
 * pilote la désactivation automatique (spec 08 §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('url');
            $table->text('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
