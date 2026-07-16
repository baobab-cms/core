<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table native Laravel (spec 11 §5.1) — `Baobab\Users\Models\User` est déjà
 * `Notifiable`. `data` en JSON (convention du projet) plutôt que `text` :
 * `Baobab\Notify\Notifications\BaobabNotification::toDatabase()` y stocke
 * `{key, description, data}`, lu tel quel par le centre de notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
