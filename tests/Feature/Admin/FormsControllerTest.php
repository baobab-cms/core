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

it('deletes a form', function () {
    $user = formsActor(['baobab.system.forms.manage']);
    $form = app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $this->actingAs($user, 'baobab')
        ->delete(route('admin.forms.destroy', ['form' => $form->id]))
        ->assertRedirect(route('admin.forms.index'));

    expect(Form::find($form->id))->toBeNull();
});
