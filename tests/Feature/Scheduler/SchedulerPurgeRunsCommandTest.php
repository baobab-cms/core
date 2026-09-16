<?php

use Baobab\Scheduler\Models\ScheduledTaskRun;
use Illuminate\Support\Facades\Artisan;

it('purges scheduled task runs older than the configured retention', function () {
    config(['baobab.scheduler.run_retention_days' => 30]);

    ScheduledTaskRun::create(['task_key' => 'baobab.content.purge-trash', 'started_at' => now()->subDays(40), 'status' => 'success']);
    ScheduledTaskRun::create(['task_key' => 'baobab.content.purge-trash', 'started_at' => now()->subDays(5), 'status' => 'success']);

    Artisan::call('baobab:scheduler:purge-runs');

    expect(ScheduledTaskRun::where('started_at', '<=', now()->subDays(35))->exists())->toBeFalse()
        ->and(ScheduledTaskRun::count())->toBe(1);
});
