<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @param  list<string>  $permissions
 */
function queuesActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Queues Actor {$counter}",
        'email' => "queues-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

function createFailedJob(string $displayName = 'App\\Jobs\\SendTestJob'): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'baobab',
        'payload' => json_encode(['displayName' => $displayName, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call']),
        'exception' => "Exception: Something broke\n#0 somewhere",
        'failed_at' => now(),
    ]);

    return $uuid;
}

it('denies the screen without baobab.system.queues.view', function () {
    $this->actingAs(queuesActor([]), 'baobab')
        ->get(route('admin.system.queues.index'))
        ->assertForbidden();
});

it('lists failed jobs with their job name and exception summary', function () {
    createFailedJob('App\\Jobs\\ImportantJob');

    $this->actingAs(queuesActor(['baobab.system.queues.view']), 'baobab')
        ->get(route('admin.system.queues.index'))
        ->assertOk()
        ->assertSee('App\\Jobs\\ImportantJob')
        ->assertSee('Something broke');
});

it('shows the pending count per queue', function () {
    DB::table('jobs')->insert(['queue' => 'baobab', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
    DB::table('jobs')->insert(['queue' => 'baobab', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

    $this->actingAs(queuesActor(['baobab.system.queues.view']), 'baobab')
        ->get(route('admin.system.queues.index'))
        ->assertOk()
        ->assertSee('baobab')
        ->assertSee('2 en attente');
});

it('shows a stale worker warning when the oldest pending job exceeds the threshold', function () {
    config(['baobab.queues.stale_worker_minutes' => 5]);
    DB::table('jobs')->insert(['queue' => 'baobab', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(10)->timestamp, 'created_at' => now()->timestamp]);

    $this->actingAs(queuesActor(['baobab.system.queues.view']), 'baobab')
        ->get(route('admin.system.queues.index'))
        ->assertOk()
        ->assertSee('worker semble à l\'arrêt');
});

it('does not show a stale worker warning when jobs are fresh', function () {
    config(['baobab.queues.stale_worker_minutes' => 5]);
    DB::table('jobs')->insert(['queue' => 'baobab', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

    $this->actingAs(queuesActor(['baobab.system.queues.view']), 'baobab')
        ->get(route('admin.system.queues.index'))
        ->assertOk()
        ->assertDontSee('worker semble à l\'arrêt');
});

it('denies retry/delete without baobab.system.queues.manage', function () {
    $uuid = createFailedJob();

    $this->actingAs(queuesActor(['baobab.system.queues.view']), 'baobab')
        ->post(route('admin.system.queues.retry', ['failedJob' => $uuid]))
        ->assertForbidden();
});

it('retries a failed job: removes it from failed_jobs and re-queues it', function () {
    $uuid = createFailedJob();

    $this->actingAs(queuesActor(['baobab.system.queues.view', 'baobab.system.queues.manage']), 'baobab')
        ->post(route('admin.system.queues.retry', ['failedJob' => $uuid]))
        ->assertRedirect(route('admin.system.queues.index'))
        ->assertSessionHas('toast');

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and(DB::table('jobs')->where('queue', 'baobab')->exists())->toBeTrue()
        ->and(AuditEntry::where('action', 'queue.jobs.retried')->exists())->toBeTrue();
});

it('deletes a failed job', function () {
    $uuid = createFailedJob();

    $this->actingAs(queuesActor(['baobab.system.queues.view', 'baobab.system.queues.manage']), 'baobab')
        ->post(route('admin.system.queues.delete', ['failedJob' => $uuid]))
        ->assertRedirect(route('admin.system.queues.index'));

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and(AuditEntry::where('action', 'queue.jobs.deleted')->exists())->toBeTrue();
});

it('bulk-retries several failed jobs at once', function () {
    $first = createFailedJob();
    $second = createFailedJob();

    $this->actingAs(queuesActor(['baobab.system.queues.view', 'baobab.system.queues.manage']), 'baobab')
        ->post(route('admin.system.queues.bulk-retry'), ['ids' => [$first, $second]])
        ->assertRedirect(route('admin.system.queues.index'));

    expect(DB::table('failed_jobs')->whereIn('uuid', [$first, $second])->count())->toBe(0)
        ->and(AuditEntry::where('action', 'queue.jobs.retried')->where('data->count', 2)->exists())->toBeTrue();
});

it('bulk-deletes several failed jobs at once', function () {
    $first = createFailedJob();
    $second = createFailedJob();

    $this->actingAs(queuesActor(['baobab.system.queues.view', 'baobab.system.queues.manage']), 'baobab')
        ->post(route('admin.system.queues.bulk-delete'), ['ids' => [$first, $second]])
        ->assertRedirect(route('admin.system.queues.index'));

    expect(DB::table('failed_jobs')->whereIn('uuid', [$first, $second])->count())->toBe(0)
        ->and(AuditEntry::where('action', 'queue.jobs.deleted')->where('data->count', 2)->exists())->toBeTrue();
});
