<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tâches de module suspendues (spec 12 §2.3, M9 chantier 0.a Pass B) — la
 * présence d'une ligne pour un `task_key` vaut suspension ; consultée par
 * Baobab\Scheduler\SchedulerRegistrar à l'enregistrement pour ne pas
 * scheduler la tâche. Une tâche Core ne peut pas y figurer
 * (Baobab\System\Actions\ToggleScheduledTask le refuse).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_task_suspensions', function (Blueprint $table): void {
            $table->id();
            $table->string('task_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_suspensions');
    }
};
