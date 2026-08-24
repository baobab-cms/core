<?php

use Baobab\Mail\MailLogStatus;
use Baobab\Mail\Models\MailLogEntry;

function loggedAt(string $when, ?string $body = null): MailLogEntry
{
    $entry = MailLogEntry::create([
        'template_key' => 'core.test',
        'recipient' => 'dest@example.com',
        'subject' => 'Sujet',
        'status' => MailLogStatus::Sent,
        'body' => $body,
    ]);

    // `created_at` est géré par Eloquent : le forcer demande une écriture
    // directe, sinon la valeur repart à maintenant au prochain save.
    MailLogEntry::query()->whereKey($entry->id)->update(['created_at' => $when]);

    return $entry->refresh();
}

it('purges entries older than the configured retention', function () {
    config(['baobab.mail.log_retention_days' => 90]);

    $old = loggedAt(now()->subDays(120)->toDateTimeString());
    $recent = loggedAt(now()->subDays(30)->toDateTimeString());

    $this->artisan('baobab:mail:purge-log')->assertSuccessful();

    expect(MailLogEntry::find($old->id))->toBeNull()
        ->and(MailLogEntry::find($recent->id))->not->toBeNull();
});

/**
 * Les deux rétentions sont distinctes, et la plus courte est celle du corps :
 * on garde la preuve qu'un e-mail est parti bien plus longtemps que son
 * contenu (spec 13 §4.1).
 */
it('clears stored bodies past their own shorter retention, keeping the entry', function () {
    config([
        'baobab.mail.log_retention_days' => 90,
        'baobab.mail.log_body_retention_days' => 7,
    ]);

    $entry = loggedAt(now()->subDays(30)->toDateTimeString(), '<p>Corps</p>');

    $this->artisan('baobab:mail:purge-log')->assertSuccessful();

    $entry->refresh();

    expect($entry->exists)->toBeTrue()
        ->and($entry->body)->toBeNull();
});

it('leaves a recent body alone', function () {
    config(['baobab.mail.log_body_retention_days' => 7]);

    $entry = loggedAt(now()->subDays(2)->toDateTimeString(), '<p>Corps</p>');

    $this->artisan('baobab:mail:purge-log')->assertSuccessful();

    expect($entry->refresh()->body)->toBe('<p>Corps</p>');
});
