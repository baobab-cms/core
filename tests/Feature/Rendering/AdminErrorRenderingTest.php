<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Rendering\Actions\RenderAdminError;
use Baobab\Users\Models\User;

/**
 * Filet d'exception admin (suivi n° 202, n° 295) — l'admin était la seule
 * surface du produit sans filet, un défaut non rattrapé y servait la page
 * brute de Laravel (incident n° 147).
 */
it('renders each supported status with its own title, body and status code', function () {
    foreach ([403, 404, 419] as $status) {
        $response = app(RenderAdminError::class)($status);

        expect($response->getStatusCode())->toBe($status);
    }

    $forbidden = (string) app(RenderAdminError::class)(403)->getContent();
    expect($forbidden)->toContain(__('baobab::admin.errors.forbidden_title'));

    $notFound = (string) app(RenderAdminError::class)(404)->getContent();
    expect($notFound)->toContain(__('baobab::admin.errors.not_found_title'));

    $sessionExpired = (string) app(RenderAdminError::class)(419)->getContent();
    expect($sessionExpired)->toContain(__('baobab::admin.errors.session_expired_title'));
});

it('shows a generic message on a 500 with no business exception', function () {
    $response = app(RenderAdminError::class)(500);
    $html = (string) $response->getContent();

    expect($response->getStatusCode())->toBe(500)
        ->and($html)->toContain(__('baobab::admin.errors.server_error_title'))
        ->and($html)->toContain(e(__('baobab::admin.errors.server_error_body')));
});

it('shows the business exception message instead of the generic one when given', function () {
    $html = (string) app(RenderAdminError::class)(500, "Ce contenu est en cours d'édition par Alice.")->getContent();

    expect($html)->toContain(e("Ce contenu est en cours d'édition par Alice."))
        ->and($html)->not->toContain(e(__('baobab::admin.errors.server_error_body')));
});

it('depends on nothing that could itself be broken: no component, no compiled token', function () {
    $html = (string) app(RenderAdminError::class)(500)->getContent();

    expect($html)->not->toContain('--bb-')
        ->and($html)->not->toContain('<link rel="stylesheet"');
});

/**
 * Même arbitrage que `wizard.css` (spec 15 §6.1, suivi n° 223) : couleurs de
 * marque figées en dur plutôt que le thème/profil actif (suivi n° 242), et
 * la pile système plutôt que Bricolage/Figtree — aucune dépendance réseau ou
 * build sur une page censée survivre à leur absence.
 */
it('styles the dashboard link as a button, with the fixed admin brand colors', function () {
    $html = (string) app(RenderAdminError::class)(404)->getContent();

    expect($html)->toContain('class="button"')
        ->and($html)->toContain('#1E7A54')
        ->and($html)->not->toContain('fonts.googleapis.com')
        ->and($html)->not->toContain('Bricolage')
        ->and($html)->not->toContain('Figtree');
});

it('turns a real 404 on an admin URL into the admin error page, not the bare Laravel page', function () {
    config(['app.debug' => false]);

    $response = $this->get('/admin/une-adresse-qui-n-existe-pas')->assertNotFound();
    $html = (string) $response->getContent();

    expect($html)->toContain(__('baobab::admin.errors.not_found_title'))
        ->and($html)->toContain(__('baobab::admin.errors.back_to_dashboard'));
});

it('turns a real policy denial into the admin error page', function () {
    config(['app.debug' => false]);

    $user = User::create(['name' => 'No access', 'email' => 'no-mail-access-filet@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $html = (string) $this->actingAs($user, 'baobab')
        ->get(route('admin.mails.settings'))
        ->assertForbidden()
        ->getContent();

    expect($html)->toContain(__('baobab::admin.errors.forbidden_title'));
});

it('leaves the exception untouched in debug mode, even on an admin URL', function () {
    config(['app.debug' => true]);

    $html = (string) $this->get('/admin/une-adresse-qui-n-existe-pas')->getContent();

    expect($html)->not->toContain(__('baobab::admin.errors.not_found_title'));
});
