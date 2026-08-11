<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Rendering\Actions\RenderServerError;
use Baobab\Users\Models\User;

/**
 * Pages de repli du Core (spec 19 §6, M8 point 10, Pass A).
 *
 * Reprend au passage l'assertion de l'ancien `WelcomeTest` : `/` retombe bien
 * sur `baobab::templates.index` sans thème ni réglage de lecture — la « page
 * de bienvenue » qui donnait son nom à ce test n'existe plus (§6.6).
 */

/**
 * @param  list<string>  $permissions
 */
function fallbackActor(array $permissions = ['baobab.admin.access']): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Fallback Actor {$counter}",
        'email' => "fallback-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('serves the Core index fallback when no theme is active', function () {
    $this->get('/')
        ->assertOk()
        ->assertViewIs('baobab::templates.index');
});

/**
 * Le défaut que cette passe corrige : le repli annonçait « Aucun thème actif —
 * ce site utilise le rendu de repli du Core » à un visiteur, qui ne pouvait
 * rien en faire.
 */
it('never tells a visitor that no theme is active', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('Aucun thème actif')
        ->and($html)->not->toContain('repli du Core');
});

it('gives the fallbacks a real shell: language, skip link and main landmark', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    // La langue suit la locale de l'application, elle n'est plus codée en dur
    // à « fr » comme dans les replis d'origine.
    $locale = str_replace('_', '-', app()->getLocale());

    expect($html)->toContain('<html lang="'.$locale.'"')
        ->and($html)->toContain(__('baobab::rendering.skip_to_content'))
        ->and($html)->toContain('<main id="bb-main"');
});

it('mounts the design tokens, so a brand profile tints the fallbacks', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    // `<x-baobab::design-tokens />` sert soit un <link>, soit du CSS inline
    // selon l'état de compilation (spec 18 §4.3) : les deux sont acceptables,
    // l'absence des deux ne l'est pas.
    expect(str_contains($html, 'tokens-') || str_contains($html, '--bb-'))->toBeTrue();
});

/**
 * §6.7 : les replis doivent rester lisibles si la compilation des tokens
 * échoue. On retire toute présentation et on vérifie que le contenu subsiste,
 * structuré.
 */
it('stays readable once every stylesheet is stripped', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    $withoutStyles = (string) preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
    $withoutStyles = (string) preg_replace('#<link\b[^>]*>#i', '', $withoutStyles);

    expect($withoutStyles)->toContain(__('baobab::rendering.index_title'))
        ->and($withoutStyles)->toContain('<h1')
        ->and($withoutStyles)->toContain('<main');
});

it('offers a way out on the not-found fallback, rather than a dead end', function () {
    $html = (string) $this->get('/une-adresse-qui-n-existe-pas')->assertNotFound()->getContent();

    // `e()` : les apostrophes françaises sont échappées à l'affichage.
    expect($html)->toContain(__('baobab::rendering.not_found_title'))
        ->and($html)->toContain(e(__('baobab::rendering.home_link')))
        ->and($html)->toContain(route('baobab.search'));
});

/**
 * Règle des états vides (§5.9) : un vide de **contenu** parle.
 */
it('speaks when a search returns nothing, and invites when there is no query', function () {
    $invite = (string) $this->get(route('baobab.search'))->assertOk()->getContent();

    expect($invite)->toContain(__('baobab::rendering.search_invite'));

    $empty = (string) $this->get(route('baobab.search', ['q' => 'zzzzzz']))->assertOk()->getContent();

    expect($empty)->toContain('zzzzzz');
});

// --- Diagnostic réservé à qui peut agir (§6.5) -------------------------------

it('shows the missing-theme diagnostic in the admin bar, to an admin', function () {
    $html = (string) $this->actingAs(fallbackActor(['baobab.admin.access', 'baobab.system.themes.manage']), 'baobab')
        ->get('/')
        ->assertOk()
        ->getContent();

    expect($html)->toContain(__('baobab::rendering.no_active_theme_notice'))
        ->and($html)->toContain(__('baobab::rendering.no_active_theme_action'));
});

it('offers no themes link to an admin who cannot manage themes', function () {
    $html = (string) $this->actingAs(fallbackActor(), 'baobab')->get('/')->assertOk()->getContent();

    expect($html)->toContain(__('baobab::rendering.no_active_theme_notice'))
        ->and($html)->not->toContain(__('baobab::rendering.no_active_theme_action'));
});

it('drops the diagnostic once a theme is actually active', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    app(InstallModule::class)('acme/theme');
    app(ActivateModule::class)('acme/theme');

    $html = (string) $this->actingAs(fallbackActor(), 'baobab')->get('/')->getContent();

    expect($html)->not->toContain(__('baobab::rendering.no_active_theme_notice'));
});

// --- Page 500 (§6.4) --------------------------------------------------------

/**
 * Son unique exigence : fonctionner quand plus rien ne fonctionne. Pas de
 * tokens compilés, pas de feuille externe, pas de composant.
 */
it('renders a 500 page that depends on nothing', function () {
    $response = app(RenderServerError::class)();
    $html = (string) $response->getContent();

    expect($response->getStatusCode())->toBe(500)
        ->and($html)->toContain(__('baobab::rendering.error_title'))
        ->and($html)->not->toContain('--bb-')
        ->and($html)->not->toContain('<link rel="stylesheet"');
});

/**
 * Le garde-fou qui compte : un `renderable()` s'exécute avant le `match` de
 * Laravel, donc une abstention manquante casserait les redirections
 * d'authentification et les retours de validation. On vérifie ici qu'un 404
 * reste un 404 — et non une page 500.
 */
it('leaves a 404 alone instead of turning it into a server error', function () {
    config(['app.debug' => false]);

    $html = (string) $this->get('/toujours-introuvable')->assertNotFound()->getContent();

    expect($html)->toContain(__('baobab::rendering.not_found_title'))
        ->and($html)->not->toContain(__('baobab::rendering.error_title'));
});
