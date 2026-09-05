<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\FormSubmission;

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

it('reports zero without auditing when nothing is past retention', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->artisan('baobab:forms:purge')->assertSuccessful();

    expect(AuditEntry::where('action', 'form_submissions.purged')->exists())->toBeFalse();
});
