<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Mail\Contracts\MailDataResolver;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\MailLogStatus;
use Baobab\Mail\Models\MailLogEntry;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Queue;

final class InvoiceResolver implements MailDataResolver
{
    public function resolve(MailLogEntry $entry): array
    {
        // Imbriqué, jamais à plat : `PlaceholderRenderer` traverse
        // `invoice` puis `url`, il ne cherche pas la clé littérale
        // « invoice.url ».
        return ['invoice' => ['url' => 'https://example.test/factures/'.md5($entry->recipient)]];
    }
}

/**
 * @param  list<string>  $permissions
 */
function logActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Log Actor {$counter}",
        'email' => "log-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

function declareLoggedTemplate(?string $resolver = null): string
{
    $path = sys_get_temp_dir().'/baobab-test-mail-log-'.getmypid().'.json';

    file_put_contents($path, json_encode([
        'subject' => 'Votre facture',
        'body' => 'Votre facture est disponible : {{ invoice.url }}',
    ]));

    $template = [
        'key' => 'core.invoice_logged',
        'description' => 'Envoyé quand une facture est émise.',
        'variables' => ['invoice.url' => 'Lien vers la facture'],
        'defaults' => $path,
    ];

    if ($resolver !== null) {
        $template['resolver'] = $resolver;
    }

    config(['baobab.mail.templates' => [$template]]);

    return 'core.invoice_logged';
}

function logEntry(array $attributes = []): MailLogEntry
{
    return MailLogEntry::create($attributes + [
        'template_key' => 'core.invoice_logged',
        'recipient' => 'client@example.com',
        'subject' => 'Votre facture',
        'status' => MailLogStatus::Sent,
    ]);
}

it('denies the screen without baobab.system.mail.log_view', function () {
    $this->actingAs(logActor(['baobab.system.mail.templates']), 'baobab')
        ->get(route('admin.mails.log'))
        ->assertForbidden();
});

/**
 * Les deux permissions sont distinctes dans la spec : consulter ce qui est
 * parti ne suppose pas de pouvoir réécrire les templates.
 */
it('grants the screen with log_view alone, without the templates permission', function () {
    declareLoggedTemplate();

    $this->actingAs(logActor(['baobab.system.mail.log_view']), 'baobab')
        ->get(route('admin.mails.log'))
        ->assertOk();
});

it('lists entries with their state', function () {
    declareLoggedTemplate();
    logEntry();
    logEntry(['recipient' => 'autre@example.com', 'status' => MailLogStatus::Failed, 'error' => 'SMTP indisponible']);

    $this->actingAs(logActor(['baobab.system.mail.log_view']), 'baobab')
        ->get(route('admin.mails.log'))
        ->assertOk()
        ->assertSee('client@example.com')
        ->assertSee('autre@example.com')
        ->assertSee('Envoyé')
        ->assertSee('Échec')
        ->assertSee('SMTP indisponible');
});

it('filters by status', function () {
    declareLoggedTemplate();
    logEntry();
    logEntry(['recipient' => 'echoue@example.com', 'status' => MailLogStatus::Failed]);

    $this->actingAs(logActor(['baobab.system.mail.log_view']), 'baobab')
        ->get(route('admin.mails.log', ['status' => 'failed']))
        ->assertOk()
        ->assertSee('echoue@example.com')
        ->assertDontSee('client@example.com');
});

/**
 * Le filtre destinataire est partiel : on cherche un domaine ou un prénom
 * bien plus souvent qu'une adresse dont on a la graphie exacte.
 */
it('filters recipients partially', function () {
    declareLoggedTemplate();
    logEntry(['recipient' => 'awa@acme.test']);
    logEntry(['recipient' => 'kofi@autre.test']);

    $this->actingAs(logActor(['baobab.system.mail.log_view']), 'baobab')
        ->get(route('admin.mails.log', ['recipient' => 'acme']))
        ->assertOk()
        ->assertSee('awa@acme.test')
        ->assertDontSee('kofi@autre.test');
});

it('filters by period', function () {
    declareLoggedTemplate();
    $old = logEntry(['recipient' => 'ancien@example.com']);
    MailLogEntry::query()->whereKey($old->id)->update(['created_at' => now()->subDays(10)]);
    logEntry(['recipient' => 'recent@example.com']);

    $this->actingAs(logActor(['baobab.system.mail.log_view']), 'baobab')
        ->get(route('admin.mails.log', ['from' => now()->subDays(2)->toDateString()]))
        ->assertOk()
        ->assertSee('recent@example.com')
        ->assertDontSee('ancien@example.com');
});

it('offers the resend action only when the template declares a resolver', function () {
    declareLoggedTemplate();
    $entry = logEntry();

    $this->actingAs(logActor(['baobab.system.mail.log_view']), 'baobab')
        ->get(route('admin.mails.log'))
        ->assertOk()
        ->assertSee('ne sait pas reconstituer ses données')
        ->assertDontSee(route('admin.mails.log.resend', ['entry' => $entry->id]));

    declareLoggedTemplate(InvoiceResolver::class);

    $this->actingAs(logActor(['baobab.system.mail.log_view']), 'baobab')
        ->get(route('admin.mails.log'))
        ->assertOk()
        ->assertSee(route('admin.mails.log.resend', ['entry' => $entry->id]), escape: false);
});

it('resends through the action and reports it', function () {
    Queue::fake();
    declareLoggedTemplate(InvoiceResolver::class);
    $entry = logEntry();

    $this->actingAs(logActor(['baobab.system.mail.log_view', 'baobab.system.mail.resend']), 'baobab')
        ->post(route('admin.mails.log.resend', ['entry' => $entry->id]))
        ->assertRedirect(route('admin.mails.log'))
        ->assertSessionHas('toast');

    Queue::assertPushed(SendQueuedMail::class);
});

it('denies the resend without baobab.system.mail.resend', function () {
    declareLoggedTemplate(InvoiceResolver::class);
    $entry = logEntry();

    $this->actingAs(logActor(['baobab.system.mail.log_view']), 'baobab')
        ->post(route('admin.mails.log.resend', ['entry' => $entry->id]))
        ->assertForbidden();
});

/**
 * Le refus doit devenir un message, jamais une page d'exception Laravel —
 * leçon du suivi n° 147. Le cas se produit dès qu'on poste la route
 * directement, le bouton étant grisé dans l'écran.
 */
it('turns a refused resend into a message instead of an exception page', function () {
    Queue::fake();
    declareLoggedTemplate();
    $entry = logEntry();

    $this->actingAs(logActor(['baobab.system.mail.log_view', 'baobab.system.mail.resend']), 'baobab')
        ->post(route('admin.mails.log.resend', ['entry' => $entry->id]))
        ->assertRedirect(route('admin.mails.log'))
        ->assertSessionHas('toast');

    Queue::assertNothingPushed();
});

/**
 * Le journal survit à la désinstallation de ce qu'il a tracé : un template
 * disparu n'est plus déclaré, et `declaration()` lève — l'écran ne doit pas
 * tomber avec.
 */
it('still renders entries whose template is no longer declared', function () {
    config(['baobab.mail.templates' => []]);
    logEntry();

    $this->actingAs(logActor(['baobab.system.mail.log_view']), 'baobab')
        ->get(route('admin.mails.log'))
        ->assertOk()
        ->assertSee('client@example.com');
});

it('links to the log from the templates screen', function () {
    declareLoggedTemplate();

    $this->actingAs(logActor(['baobab.system.mail.templates', 'baobab.system.mail.log_view']), 'baobab')
        ->get(route('admin.mails.index'))
        ->assertOk()
        ->assertSee(route('admin.mails.log'), escape: false);
});
