<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\Form;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * @param  list<string>  $permissions
 */
function formsActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Forms actor {$counter}",
        'email' => "forms-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies access without baobab.system.forms.manage', function () {
    $user = formsActor([]);

    $this->actingAs($user, 'baobab')->get(route('admin.forms.index'))->assertForbidden();
});

it('lists forms', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.forms.index'))
        ->assertOk()
        ->assertSee('Contact');
});

it('creates an empty form from the admin form', function () {
    $user = formsActor(['baobab.system.forms.manage']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.forms.store'), ['title' => 'Contact', 'slug' => 'contact'])
        ->assertRedirect(route('admin.forms.index'));

    $form = Form::where('slug', 'contact')->first();

    expect($form)->not->toBeNull()
        ->and($form->title)->toBe('Contact')
        ->and($form->version)->toBe(1)
        ->and($form->blueprint['fields'])->toBe([]);
});

it('rejects a slug already used by another form, keeping the input', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->actingAs($user, 'baobab')
        ->from(route('admin.forms.create'))
        ->post(route('admin.forms.store'), ['title' => 'Autre', 'slug' => 'contact'])
        ->assertRedirect(route('admin.forms.create'))
        ->assertSessionHasErrors('slug');

    expect(Form::where('slug', 'contact')->count())->toBe(1);
});

it('rejects a slug with characters alpha_dash does not allow', function () {
    $user = formsActor(['baobab.system.forms.manage']);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.forms.store'), ['title' => 'Contact', 'slug' => 'contact form!'])
        ->assertSessionHasErrors('slug');
});

it('shows the edit screen with the current fields', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true, 'label' => 'E-mail']],
    ]);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.forms.edit', ['form' => $form->id]))
        ->assertOk()
        ->assertSee('Contact')
        ->assertSee('email');
});

it('renders the placeholder and the help text in the preview', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [[
            'key' => 'email',
            'type' => 'email',
            'label' => 'E-mail',
            'placeholder' => 'jane@example.com',
            'help_text' => 'Nous ne le partagerons jamais.',
        ]],
    ]);

    $this->actingAs($user, 'baobab')
        ->get(route('admin.forms.edit', ['form' => $form->id]))
        ->assertOk()
        ->assertSee('placeholder="jane@example.com"', false)
        ->assertSee('Nous ne le partagerons jamais.');
});

it('marks a required field with an asterisk in the preview, and leaves an optional one alone', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [
            ['key' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true],
            ['key' => 'company', 'type' => 'text', 'label' => 'Société', 'required' => false],
        ],
    ]);

    $response = $this->actingAs($user, 'baobab')->get(route('admin.forms.edit', ['form' => $form->id]));

    $response->assertOk()
        ->assertSeeInOrder(['E-mail *'])
        ->assertDontSee('Société *');
});

it('updates the title and the fields from the builder payload', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $fields = json_encode([
        ['key' => 'email', 'type' => 'email', 'label' => 'E-mail', 'placeholder' => null, 'help_text' => null, 'required' => true, 'options' => []],
        ['key' => 'topic', 'type' => 'select', 'label' => 'Sujet', 'placeholder' => null, 'help_text' => null, 'required' => false, 'options' => ['choices' => ['support', 'sales']]],
    ]);

    $this->actingAs($user, 'baobab')
        ->put(route('admin.forms.update', ['form' => $form->id]), [
            'title' => 'Nous contacter',
            'fields' => $fields,
        ])
        ->assertRedirect(route('admin.forms.edit', ['form' => $form->id]));

    $form = $form->fresh();

    expect($form->title)->toBe('Nous contacter')
        ->and($form->version)->toBe(2)
        ->and($form->blueprint['fields'])->toHaveCount(2)
        ->and($form->blueprint['fields'][0]['label'])->toBe('E-mail')
        ->and($form->blueprint['fields'][1]['options'])->toBe(['choices' => ['support', 'sales']]);
});

it('rejects an update whose fields break the blueprint, without saving anything', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $fields = json_encode([
        ['key' => 'topic', 'type' => 'select', 'options' => []],
    ]);

    $this->actingAs($user, 'baobab')
        ->put(route('admin.forms.update', ['form' => $form->id]), ['title' => 'Contact', 'fields' => $fields])
        ->assertSessionHasErrors('fields');

    expect($form->fresh()->version)->toBe(1);
});

it('updates the settings without touching the existing fields', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, [
        'slug' => 'contact',
        'title' => 'Contact',
        'fields' => [['key' => 'email', 'type' => 'email', 'required' => true]],
    ]);

    $this->actingAs($user, 'baobab')
        ->put(route('admin.forms.settings.update', ['form' => $form->id]), [
            'store_submissions' => '1',
            'retention_days' => '90',
            'retain_ip' => '1',
            'suites' => [
                'email_notification' => ['enabled' => '1', 'recipients' => "jane@example.com\njohn@example.com"],
                'admin_notification' => ['enabled' => '1'],
            ],
            'captcha_provider' => 'turnstile',
            'captcha_site_key' => '0x123',
            'captcha_secret_key' => '0xabc',
            'confirmation_message' => '  Merci, on revient vers vous sous 48h.  ',
        ])
        ->assertRedirect(route('admin.forms.edit', ['form' => $form->id]));

    $form = $form->fresh();

    expect($form->retention_days)->toBe(90)
        ->and($form->retain_ip)->toBeTrue()
        ->and($form->settings['suites']['email_notification'])->toBe(['enabled' => true, 'recipients' => ['jane@example.com', 'john@example.com']])
        ->and($form->settings['anti_spam']['captcha']['provider'])->toBe('turnstile')
        ->and($form->settings['confirmation'])->toBe(['message' => 'Merci, on revient vers vous sous 48h.'])
        ->and($form->blueprint['fields'])->toHaveCount(1)
        ->and($form->blueprint['fields'][0]['key'])->toBe('email')
        ->and($form->version)->toBe(2);
});

it('rejects a settings update with an invalid retention', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->actingAs($user, 'baobab')
        ->put(route('admin.forms.settings.update', ['form' => $form->id]), ['retention_days' => '0'])
        ->assertSessionHasErrors('retention_days');

    expect($form->fresh()->version)->toBe(1);
});

it('downloads a form export as an attachment', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $response = $this->actingAs($user, 'baobab')->get(route('admin.forms.export', ['form' => $form->id]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('Content-Disposition', 'attachment; filename="contact.json"');

    expect(json_decode($response->getContent(), true)['slug'])->toBe('contact');
});

it('imports a form uploaded from the index screen', function () {
    $user = formsActor(['baobab.system.forms.manage']);

    $file = UploadedFile::fake()->createWithContent('contact.json', json_encode([
        'source' => 'admin', 'format_version' => 1, 'slug' => 'contact', 'title' => 'Contact',
        'fields' => [], 'settings' => [], 'store_submissions' => true, 'retention_days' => 365, 'retain_ip' => false,
    ]));

    $this->actingAs($user, 'baobab')
        ->post(route('admin.forms.import'), ['file' => $file])
        ->assertRedirect();

    expect(Form::where('slug', 'contact')->exists())->toBeTrue();
});

it('rejects an import that is not valid JSON, as a form error rather than a 500', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $file = UploadedFile::fake()->createWithContent('bad.json', 'not json');

    $this->actingAs($user, 'baobab')
        ->post(route('admin.forms.import'), ['file' => $file])
        ->assertSessionHasErrors('file');
});

it('deletes a form', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->actingAs($user, 'baobab')
        ->delete(route('admin.forms.destroy', ['form' => $form->id]))
        ->assertRedirect(route('admin.forms.index'));

    expect(Form::find($form->id))->toBeNull();
});
