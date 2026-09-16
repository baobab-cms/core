<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Modules\Models\Module;
use Baobab\Scheduler\Models\ScheduledTaskRun;
use Baobab\Scheduler\Models\ScheduledTaskSuspension;
use Baobab\Users\Models\User;

/**
 * @param  list<string>  $permissions
 */
function schedulerActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Scheduler Actor {$counter}",
        'email' => "scheduler-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

function activeModuleWithScheduledTask(): Module
{
    return Module::create([
        'name' => 'acme/newsletter',
        'title' => 'Newsletter',
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Newsletter\\Providers\\NewsletterServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-newsletter',
        'status' => 'active',
        'manifest' => [
            'schedule' => [
                ['key' => 'acme.newsletter.digest', 'command' => 'acme:newsletter:digest', 'cron' => '0 8 * * *', 'description' => 'Envoie le digest.'],
            ],
        ],
    ]);
}

it('denies the screen without baobab.system.scheduler.view', function () {
    $this->actingAs(schedulerActor([]), 'baobab')
        ->get(route('admin.system.scheduler.index'))
        ->assertForbidden();
});

it('lists core tasks with their description', function () {
    $this->actingAs(schedulerActor(['baobab.system.scheduler.view']), 'baobab')
        ->get(route('admin.system.scheduler.index'))
        ->assertOk()
        ->assertSee('Publie les contenus programmés arrivés à échéance.')
        ->assertSee('Chaque minute');
});

it('lists a module task with its manifest description', function () {
    activeModuleWithScheduledTask();

    $this->actingAs(schedulerActor(['baobab.system.scheduler.view']), 'baobab')
        ->get(route('admin.system.scheduler.index'))
        ->assertOk()
        ->assertSee('Envoie le digest.')
        ->assertSee('acme/newsletter');
});

it('denies running a task without baobab.system.scheduler.run', function () {
    $this->actingAs(schedulerActor(['baobab.system.scheduler.view']), 'baobab')
        ->post(route('admin.system.scheduler.run', ['taskKey' => 'baobab.notifications.purge-old']))
        ->assertForbidden();
});

it('runs a task now and records a successful history row', function () {
    $this->actingAs(schedulerActor(['baobab.system.scheduler.view', 'baobab.system.scheduler.run']), 'baobab')
        ->post(route('admin.system.scheduler.run', ['taskKey' => 'baobab.notifications.purge-old']))
        ->assertRedirect(route('admin.system.scheduler.index'))
        ->assertSessionHas('toast');

    $run = ScheduledTaskRun::where('task_key', 'baobab.notifications.purge-old')->sole();
    expect($run->status->value)->toBe('success')
        ->and($run->finished_at)->not->toBeNull();
});

it('suspends and reactivates a module task', function () {
    activeModuleWithScheduledTask();
    $actor = schedulerActor(['baobab.system.scheduler.view', 'baobab.system.scheduler.run']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.system.scheduler.suspend', ['taskKey' => 'acme.newsletter.digest']))
        ->assertRedirect(route('admin.system.scheduler.index'))
        ->assertSessionHas('toast');

    expect(ScheduledTaskSuspension::where('task_key', 'acme.newsletter.digest')->exists())->toBeTrue();

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.system.scheduler.resume', ['taskKey' => 'acme.newsletter.digest']))
        ->assertRedirect(route('admin.system.scheduler.index'));

    expect(ScheduledTaskSuspension::where('task_key', 'acme.newsletter.digest')->exists())->toBeFalse();
});

/**
 * Le refus doit devenir un message, jamais une page d'exception Laravel —
 * même patron que le renvoi d'e-mail (suivi n° 147).
 */
it('refuses to suspend a core task and turns it into a toast, not an exception page', function () {
    $this->actingAs(schedulerActor(['baobab.system.scheduler.view', 'baobab.system.scheduler.run']), 'baobab')
        ->post(route('admin.system.scheduler.suspend', ['taskKey' => 'baobab.content.publish-due']))
        ->assertRedirect(route('admin.system.scheduler.index'))
        ->assertSessionHas('toast');

    expect(ScheduledTaskSuspension::where('task_key', 'baobab.content.publish-due')->exists())->toBeFalse();
});

it('offers the suspend action for a module task but not for a core task', function () {
    activeModuleWithScheduledTask();

    $response = $this->actingAs(schedulerActor(['baobab.system.scheduler.view', 'baobab.system.scheduler.run']), 'baobab')
        ->get(route('admin.system.scheduler.index'));

    $response->assertOk()
        ->assertSee(route('admin.system.scheduler.suspend', ['taskKey' => 'acme.newsletter.digest']), escape: false)
        ->assertDontSee(route('admin.system.scheduler.suspend', ['taskKey' => 'baobab.content.publish-due']), escape: false);
});
