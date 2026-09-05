<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Actions\SubmitForm;
use Baobab\Forms\FormSubmissionStatus;
use Baobab\Forms\Models\FormSubmission;
use Baobab\Users\Models\User;

/**
 * @param  list<string>  $permissions
 */
function submissionsActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Submissions actor {$counter}",
        'email' => "submissions-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies the index without baobab.system.forms.submissions_view', function () {
    $user = submissionsActor([]);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.forms.submissions.index', ['form' => $form->id]))
        ->assertForbidden();
});

it('lists submissions, filtered by status', function () {
    $user = submissionsActor(['baobab.system.forms.submissions_view']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => [['key' => 'email', 'type' => 'email']]]);

    FormSubmission::create(['form_id' => $form->id, 'form_version' => 1, 'payload' => ['email' => 'new@example.com'], 'status' => 'new']);
    FormSubmission::create(['form_id' => $form->id, 'form_version' => 1, 'payload' => ['email' => 'spam@example.com'], 'status' => 'spam']);

    $response = $this->actingAs($user, 'baobab')
        ->get(route('admin.forms.submissions.index', ['form' => $form->id, 'status' => 'spam']));

    $response->assertOk()->assertSee('spam@example.com')->assertDontSee('new@example.com');
});

it('shows a submission using its own blueprint snapshot, even after the form changed since', function () {
    $user = submissionsActor(['baobab.system.forms.submissions_view']);
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact', 'title' => 'Contact',
        'fields' => [['key' => 'old_name', 'type' => 'text', 'label' => 'Ancien nom']],
    ]);
    $submission = app(SubmitForm::class)($form, ['old_name' => 'Jane']);

    // Le champ est renommé après coup — la fiche doit rester fidèle au snapshot.
    app(SaveForm::class)($form, [
        'slug' => 'contact', 'title' => 'Contact',
        'fields' => [['key' => 'new_name', 'type' => 'text', 'label' => 'Nouveau nom']],
    ]);

    $response = $this->actingAs($user, 'baobab')
        ->get(route('admin.forms.submissions.show', ['form' => $form->id, 'submission' => $submission->id]));

    $response->assertOk()->assertSee('Ancien nom')->assertSee('Jane')->assertDontSee('Nouveau nom');
});

it('marks a submission read', function () {
    $user = submissionsActor(['baobab.system.forms.submissions_view']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'form_version' => 1, 'payload' => [], 'status' => 'new']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.forms.submissions.mark-status', ['form' => $form->id, 'submission' => $submission->id]), ['status' => 'read'])
        ->assertRedirect(route('admin.forms.submissions.index', ['form' => $form->id]));

    expect($submission->fresh()->status)->toBe(FormSubmissionStatus::Read);
});

it('denies export without baobab.system.forms.submissions_export, even with view', function () {
    $user = submissionsActor(['baobab.system.forms.submissions_view']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.forms.submissions.export', ['form' => $form->id]))
        ->assertForbidden();
});

it('exports the filtered submissions as CSV and audits it', function () {
    $user = submissionsActor(['baobab.system.forms.submissions_export']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => [['key' => 'email', 'type' => 'email']]]);
    FormSubmission::create(['form_id' => $form->id, 'form_version' => 1, 'payload' => ['email' => 'jane@example.com'], 'status' => 'new']);

    $response = $this->actingAs($user, 'baobab')
        ->get(route('admin.forms.submissions.export', ['form' => $form->id]));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    expect($response->getContent())->toContain('jane@example.com')
        ->and(AuditEntry::where('action', 'form_submissions.exported')->exists())->toBeTrue();
});

it('denies delete without baobab.system.forms.submissions_delete', function () {
    $user = submissionsActor(['baobab.system.forms.submissions_view']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'form_version' => 1, 'payload' => [], 'status' => 'new']);

    $this->actingAs($user, 'baobab')
        ->delete(route('admin.forms.submissions.destroy', ['form' => $form->id, 'submission' => $submission->id]))
        ->assertForbidden();
});

it('deletes a submission', function () {
    $user = submissionsActor(['baobab.system.forms.submissions_delete']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);
    $submission = FormSubmission::create(['form_id' => $form->id, 'form_version' => 1, 'payload' => [], 'status' => 'new']);

    $this->actingAs($user, 'baobab')
        ->delete(route('admin.forms.submissions.destroy', ['form' => $form->id, 'submission' => $submission->id]))
        ->assertRedirect(route('admin.forms.submissions.index', ['form' => $form->id]));

    expect(FormSubmission::find($submission->id))->toBeNull();
});
