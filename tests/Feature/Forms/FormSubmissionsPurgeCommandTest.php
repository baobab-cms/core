<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\FormSubmission;
use Illuminate\Support\Facades\Storage;

it('purges submissions past each form\'s own retention, and audits it', function () {
    $shortRetention = app(SaveForm::class)(null, [
        'slug' => 'short', 'title' => 'Short', 'fields' => [], 'retention_days' => 10,
    ]);
    $longRetention = app(SaveForm::class)(null, [
        'slug' => 'long', 'title' => 'Long', 'fields' => [], 'retention_days' => 400,
    ]);

    $old = FormSubmission::create([
        'form_id' => $shortRetention->id,
        'form_version' => 1,
        'payload' => [],
        'status' => 'new',
    ]);
    $old->forceFill(['created_at' => now()->subDays(30)])->save();

    $recent = FormSubmission::create([
        'form_id' => $shortRetention->id,
        'form_version' => 1,
        'payload' => [],
        'status' => 'new',
    ]);
    $recent->forceFill(['created_at' => now()->subDays(2)])->save();

    $stillWithinLongRetention = FormSubmission::create([
        'form_id' => $longRetention->id,
        'form_version' => 1,
        'payload' => [],
        'status' => 'new',
    ]);
    $stillWithinLongRetention->forceFill(['created_at' => now()->subDays(30)])->save();

    $this->artisan('baobab:forms:purge')->assertSuccessful();

    expect(FormSubmission::find($old->id))->toBeNull()
        ->and(FormSubmission::find($recent->id))->not->toBeNull()
        ->and(FormSubmission::find($stillWithinLongRetention->id))->not->toBeNull();

    expect(AuditEntry::where('action', 'form_submissions.purged')->where('auditable_id', $shortRetention->id)->exists())->toBeTrue();
});

it('deletes the attached file of a purged submission (spec 14 §5, Pass C3)', function () {
    Storage::fake('local');
    Storage::disk('local')->put('form-submissions/2026/09/old.pdf', 'contenu');

    $form = app(SaveForm::class)(null, [
        'slug' => 'contact', 'title' => 'Contact', 'retention_days' => 10,
        'fields' => [['key' => 'cv', 'type' => 'file']],
    ]);

    $old = FormSubmission::create([
        'form_id' => $form->id,
        'form_version' => 1,
        'blueprint_snapshot' => $form->blueprint['fields'],
        'payload' => ['cv' => ['original_name' => 'old.pdf', 'stored_path' => 'form-submissions/2026/09/old.pdf']],
        'status' => 'new',
    ]);
    $old->forceFill(['created_at' => now()->subDays(30)])->save();

    $this->artisan('baobab:forms:purge')->assertSuccessful();

    Storage::disk('local')->assertMissing('form-submissions/2026/09/old.pdf');
});

it('reports zero without auditing when nothing is past retention', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->artisan('baobab:forms:purge')->assertSuccessful();

    expect(AuditEntry::where('action', 'form_submissions.purged')->exists())->toBeFalse();
});
