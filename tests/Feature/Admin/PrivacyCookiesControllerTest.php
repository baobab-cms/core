<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Modules\Models\Module;
use Baobab\Privacy\Cookies\CoreCookies;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

/**
 * Écran de consultation des cookies déclarés (spec 16 §3.2, décision 23 —
 * M9 chantier 0.b Pass F2).
 */

/**
 * @param  list<string>  $permissions
 */
function privacyCookiesActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Cookies Actor {$counter}",
        'email' => "privacy-cookies-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

function cookiesScreenModule(string $name, string $category): Module
{
    $path = sys_get_temp_dir().'/baobab-test-cookies-screen/'.str_replace('/', '-', $name);
    File::ensureDirectoryExists($path);

    return Module::create([
        'name' => $name,
        'title' => $name,
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\CookiesScreen\\Provider',
        'source' => 'local',
        'path' => $path,
        'manifest' => ['name' => $name, 'privacy' => ['cookies' => [[
            'name' => '_screen_tracker',
            'category' => $category,
            'purpose' => 'Mesure d\'audience de démonstration',
            'duration' => '13 mois',
            'provider' => 'Acme Analytics',
        ]]]],
        'status' => 'active',
    ]);
}

afterEach(function (): void {
    File::deleteDirectory(sys_get_temp_dir().'/baobab-test-cookies-screen');
});

it('denies the cookies screen without baobab.privacy.register.view', function () {
    $this->actingAs(privacyCookiesActor([]), 'baobab')
        ->get(route('admin.privacy.cookies.index'))
        ->assertForbidden();
});

it('lists the Core cookies, all four categories and a hidden banner on a bare install', function () {
    $this->actingAs(privacyCookiesActor(['baobab.privacy.register.view']), 'baobab')
        ->get(route('admin.privacy.cookies.index'))
        ->assertOk()
        ->assertSee(CoreCookies::CONSENT_COOKIE)
        ->assertSee('XSRF-TOKEN')
        ->assertSee(__('baobab::privacy.cookies.categories.necessary.label'))
        ->assertSee(__('baobab::privacy.cookies.categories.functional.label'))
        ->assertSee(__('baobab::privacy.cookies.categories.analytics.label'))
        ->assertSee(__('baobab::privacy.cookies.categories.marketing.label'))
        ->assertSee(__('baobab::admin.privacy_cookies.empty'))
        ->assertSee(__('baobab::admin.privacy_cookies.banner_hidden'))
        ->assertDontSee(__('baobab::admin.privacy_cookies.banner_shown'));
});

it('shows a module cookie with its provider and source, and the banner as shown', function () {
    cookiesScreenModule('acme/cookies-screen-analytics', 'analytics');

    $this->actingAs(privacyCookiesActor(['baobab.privacy.register.view']), 'baobab')
        ->get(route('admin.privacy.cookies.index'))
        ->assertOk()
        ->assertSee('_screen_tracker')
        ->assertSee('Acme Analytics')
        ->assertSee('acme/cookies-screen-analytics')
        ->assertSee(__('baobab::admin.privacy_cookies.banner_shown'))
        ->assertDontSee(__('baobab::admin.privacy_cookies.banner_hidden'));
});

it('keeps the banner hidden when a module only declares necessary cookies', function () {
    cookiesScreenModule('acme/cookies-screen-necessary', 'necessary');

    $this->actingAs(privacyCookiesActor(['baobab.privacy.register.view']), 'baobab')
        ->get(route('admin.privacy.cookies.index'))
        ->assertOk()
        ->assertSee('_screen_tracker')
        ->assertSee(__('baobab::admin.privacy_cookies.banner_hidden'));
});

it('links the cookies screen from the sidebar only with the permission', function () {
    $this->actingAs(privacyCookiesActor(['baobab.privacy.register.view']), 'baobab')
        ->get(route('admin.dashboard'))
        ->assertSee(route('admin.privacy.cookies.index'), false);

    $this->actingAs(privacyCookiesActor([]), 'baobab')
        ->get(route('admin.dashboard'))
        ->assertDontSee(route('admin.privacy.cookies.index'), false);
});
