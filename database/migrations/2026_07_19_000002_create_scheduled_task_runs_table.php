<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique d'exécution des tâches planifiées (spec 12 §2, M5 point 7) —
 * une ligne par exécution, aussi bien pour les tâches Core (content:publish-
 * due...) que pour toute tâche déclarée par un module via `schedule` au
 * manifeste. Écrite par Baobab\Scheduler\SchedulerRegistrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_task_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('task_key');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status')->default('running');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('task_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};
