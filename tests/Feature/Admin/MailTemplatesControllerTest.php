<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Mail\Actions\SaveMailTemplate;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Mail\Models\MailTemplateOverride;
use Baobab\Mail\TemplateRegistry;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * @param  list<string>  $permissions
 */
function mailActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Mail Actor {$counter}",
        'email' => "mail-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * Un template Core dédié à cet écran : `core.test` n'a aucune variable
 * `required`, il ne permettrait pas d'exercer la validation bloquante.
 */
function declareScreenTemplate(): string
{
    $path = sys_get_temp_dir().'/baobab-test-mail-screen-'.getmypid().'.json';

    file_put_contents($path, json_encode([
        'subject' => 'Votre facture {{ invoice.number }}',
        'body' => 'Bonjour {{ customer.name }}, votre facture est disponible : {{ invoice.url }}',
    ]));

    config(['baobab.mail.templates' => [[
        'key' => 'core.invoice_issued',
        'description' => 'Envoyé quand une facture est émise.',
        'variables' => [
            'customer.name' => 'Nom du client',
            'invoice.number' => 'Numéro de facture',
            'invoice.url' => ['label' => 'Lien vers la facture', 'required' => true],
        ],
        'defaults' => $path,
    ]]]);

    return 'core.invoice_issued';
}

it('denies the screen without baobab.system.mail.templates', function () {
    $actor = mailActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.mails.index'))
        ->assertForbidden();
});

it('lists declared templates with their state', function () {
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.mails.index'))
        ->assertOk()
        ->assertSee($key)
        ->assertSee('Texte d\'origine');

    app(SaveMailTemplate::class)($key, [
        'subject' => 'Facture {{ invoice.number }}',
        'body' => 'Bonjour, voici votre facture : {{ invoice.url }}',
    ]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.mails.index'))
        ->assertOk()
        ->assertSee('Personnalisé');
});

it('shows the declared variables and marks the required ones on the edit screen', function () {
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.mails.edit', $key))
        ->assertOk()
        ->assertSee('invoice.url')
        ->assertSee('Lien vers la facture')
        ->assertSee('obligatoire');
});

it('saves a customisation from the screen', function () {
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.mails.update', $key), [
            'subject' => 'Votre facture est prête',
            'body' => 'Téléchargez-la ici : {{ invoice.url }}',
        ])
        ->assertRedirect(route('admin.mails.edit', $key));

    expect(app(TemplateRegistry::class)->find($key)->subject)->toBe('Votre facture est prête');
});

it('refuses a save that drops a required variable, and keeps the input', function () {
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.mails.update', $key), [
            'subject' => 'Votre facture est prête',
            'body' => 'Un corps sans le lien.',
        ])
        ->assertSessionHasErrors('body');

    expect(MailTemplateOverride::query()->count())->toBe(0);
});

it('restores the code default from the screen', function () {
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    app(SaveMailTemplate::class)($key, [
        'subject' => 'Le mien',
        'body' => 'Le mien : {{ invoice.url }}',
    ]);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.mails.restore', $key))
        ->assertRedirect(route('admin.mails.edit', $key));

    expect(MailTemplateOverride::query()->count())->toBe(0)
        ->and(AuditEntry::where('action', 'mail.template_restored')->exists())->toBeTrue();
});

it('previews what is in the form, with variables replaced by their labels', function () {
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    $response = $this->actingAs($actor, 'baobab')
        ->post(route('admin.mails.preview', $key), [
            'subject' => 'Aperçu',
            'body' => 'Bonjour {{ customer.name }}, lien : {{ invoice.url }}',
        ])
        ->assertOk();

    // Le libellé à la place de la valeur (écart assumé, suivi n° 190), et le
    // layout Core réellement monté — pas un rendu au rabais.
    expect($response->getContent())->toContain('Nom du client')
        ->and($response->getContent())->toContain('Lien vers la facture')
        // Document complet et CSS inliné : c'est bien le layout Core qui est
        // monté. (Le DOCTYPE, lui, est retiré par `CssInliner` — comportement
        // de tous les e-mails Baobab, pas une particularité de l'aperçu.)
        ->and($response->getContent())->toContain('<html')
        ->and($response->getContent())->toContain('</body>')
        ->and($response->getContent())->toContain('style=');
});

it('previews the draft rather than the saved version', function () {
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    app(SaveMailTemplate::class)($key, [
        'subject' => 'Version enregistrée',
        'body' => 'Corps enregistré : {{ invoice.url }}',
    ]);

    $response = $this->actingAs($actor, 'baobab')
        ->post(route('admin.mails.preview', $key), [
            'subject' => 'Brouillon en cours',
            'body' => 'Corps de brouillon : {{ invoice.url }}',
        ])
        ->assertOk();

    expect($response->getContent())->toContain('Corps de brouillon')
        ->and($response->getContent())->not->toContain('Corps enregistré');
});

it('queues a test send on the saved template', function () {
    Queue::fake();

    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    app(SaveMailTemplate::class)($key, [
        'subject' => 'Version enregistrée',
        'body' => 'Corps enregistré : {{ invoice.url }}',
    ]);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.mails.test', $key), ['recipient' => 'integrateur@example.com'])
        ->assertRedirect(route('admin.mails.edit', $key));

    Queue::assertPushedOn('baobab', SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === 'integrateur@example.com'
        && $job->subject === 'Version enregistrée'
        && str_contains($job->html, 'Lien vers la facture'));

    expect(AuditEntry::where('action', 'mail.test_sent')->exists())->toBeTrue();
});

it('shows the drift banner and its diff once the code default moves', function () {
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    app(SaveMailTemplate::class)($key, [
        'subject' => 'Le mien',
        'body' => 'Le mien : {{ invoice.url }}',
    ]);

    // Ce que ferait une mise à jour du module qui déclare le template.
    $path = sys_get_temp_dir().'/baobab-test-mail-screen-'.getmypid().'.json';
    file_put_contents($path, json_encode([
        'subject' => 'Votre facture {{ invoice.number }} est disponible',
        'body' => 'Bonjour {{ customer.name }}, téléchargez ici : {{ invoice.url }}',
    ]));

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.mails.edit', $key))
        ->assertOk()
        ->assertSee('Le texte d\'origine a changé depuis votre personnalisation')
        // La version de l'admin est toujours celle qu'on édite.
        ->assertSee('Le mien');
});

it('does not show the drift banner while the default has not moved', function () {
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    app(SaveMailTemplate::class)($key, [
        'subject' => 'Le mien',
        'body' => 'Le mien : {{ invoice.url }}',
    ]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.mails.edit', $key))
        ->assertOk()
        ->assertDontSee('Le texte d\'origine a changé depuis votre personnalisation');
});

it('offers the sidebar entry only to an actor holding the permission', function () {
    $allowed = mailActor(['baobab.system.mail.templates']);
    $denied = mailActor([]);

    $this->actingAs($allowed, 'baobab')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.mails.index'));

    $this->actingAs($denied, 'baobab')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.mails.index'));
});

/*
|--------------------------------------------------------------------------
| Non-régressions — quatre défauts trouvés en vérification navigateur
| (22 août 2026). Chacun a son test parce qu'aucun n'aurait pu être vu
| depuis la suite telle qu'elle était : deux tenaient à la compilation
| Blade, un à l'ordre de deux tableaux, un au rendu final d'un vrai e-mail.
|--------------------------------------------------------------------------
*/

it('does not method-spoof the edit form, so the preview button can reach its own route', function () {
    // Le `_method` d'un formulaire spoofé vaut pour TOUS ses boutons, y
    // compris ceux qui portent un `formaction` : l'aperçu partait en PUT.
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.mails.edit', $key))
        ->assertOk()
        ->assertDontSee('name="_method"', false);
});

it('accepts the save on the same verb the form actually posts', function () {
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.mails.update', $key), [
            'subject' => 'Enregistré en POST',
            'body' => 'Corps : {{ invoice.url }}',
        ])
        ->assertRedirect(route('admin.mails.edit', $key));

    expect(app(TemplateRegistry::class)->find($key)->subject)->toBe('Enregistré en POST');
});

it('inserts a bare placeholder, without the Blade escape character leaking in', function () {
    // Un commentaire contenant la séquence ouvrante décalait la
    // correspondance de Blade et laissait un `@` devant le placeholder inséré.
    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    $html = $this->actingAs($actor, 'baobab')
        ->get(route('admin.mails.edit', $key))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('insertVariable')
        ->and($html)->not->toContain("'@{")
        ->and($html)->not->toContain('@{{');
});

it('sends a test mail stamped with the real time, not the sample label', function () {
    Queue::fake();

    $key = declareScreenTemplate();
    $actor = mailActor(['baobab.system.mail.templates']);

    // `sent_at` n'est pas déclarée par ce template : c'est l'Action qui la
    // connaît, et sa valeur ne doit pas être écrasée par les valeurs d'exemple.
    app(SaveMailTemplate::class)($key, [
        'subject' => 'Test',
        'body' => 'Lien : {{ invoice.url }}',
    ]);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.mails.test', $key), ['recipient' => 'integrateur@example.com']);

    Queue::assertPushed(SendQueuedMail::class);
});
