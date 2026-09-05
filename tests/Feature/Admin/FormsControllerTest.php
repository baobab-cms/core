<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\Form;
use Baobab\Users\Models\User;

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

it('deletes a form', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->actingAs($user, 'baobab')
        ->delete(route('admin.forms.destroy', ['form' => $form->id]))
        ->assertRedirect(route('admin.forms.index'));

    expect(Form::find($form->id))->toBeNull();
});
