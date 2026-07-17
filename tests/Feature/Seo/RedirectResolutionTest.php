<?php

use Baobab\Seo\Actions\CreateRedirect;
use Baobab\Seo\Actions\DeleteRedirect;
use Baobab\Seo\Actions\ResolveRedirectTarget;
use Baobab\Seo\Actions\UpdateRedirect;
use Baobab\Seo\Models\Redirect;
use Illuminate\Support\Facades\Cache;

it('resolves an exact source match', function () {
    app(CreateRedirect::class)(['source' => '/old-page', 'target' => '/new-page', 'status_code' => 301]);

    $match = app(ResolveRedirectTarget::class)('/old-page');

    expect($match)->not->toBeNull()
        ->and($match['target'])->toBe('/new-page')
        ->and($match['status_code'])->toBe(301);
});

it('resolves a wildcard source and substitutes the captured segment into the target', function () {
    app(CreateRedirect::class)(['source' => '/ancien-blog/*', 'target' => '/blog/*', 'status_code' => 301]);

    $match = app(ResolveRedirectTarget::class)('/ancien-blog/mon-article');

    expect($match)->not->toBeNull()
        ->and($match['target'])->toBe('/blog/mon-article');
});

it('prefers an exact match over an overlapping wildcard pattern', function () {
    app(CreateRedirect::class)(['source' => '/blog/*', 'target' => '/articles/*', 'status_code' => 301]);
    app(CreateRedirect::class)(['source' => '/blog/special', 'target' => '/special-page', 'status_code' => 301]);

    $match = app(ResolveRedirectTarget::class)('/blog/special');

    expect($match['target'])->toBe('/special-page');
});

it('returns null for a path with no matching redirect', function () {
    expect(app(ResolveRedirectTarget::class)('/nothing-here'))->toBeNull();
});

it('ignores an inactive redirect', function () {
    app(CreateRedirect::class)(['source' => '/old', 'target' => '/new', 'status_code' => 301, 'is_active' => false]);

    expect(app(ResolveRedirectTarget::class)('/old'))->toBeNull();
});

it('invalidates the resolution cache when a redirect is created, updated or deleted', function () {
    expect(app(ResolveRedirectTarget::class)('/a'))->toBeNull();

    $redirect = app(CreateRedirect::class)(['source' => '/a', 'target' => '/b', 'status_code' => 301]);
    expect(app(ResolveRedirectTarget::class)('/a')['target'])->toBe('/b');

    app(UpdateRedirect::class)($redirect, ['target' => '/c']);
    expect(app(ResolveRedirectTarget::class)('/a')['target'])->toBe('/c');

    app(DeleteRedirect::class)($redirect);
    expect(app(ResolveRedirectTarget::class)('/a'))->toBeNull();
});

it('does not invalidate the resolution cache on a hit through the public middleware', function () {
    app(CreateRedirect::class)(['source' => '/old-cached', 'target' => '/new-cached', 'status_code' => 301]);
    app(ResolveRedirectTarget::class)('/old-cached');

    expect(Cache::has(ResolveRedirectTarget::CACHE_KEY))->toBeTrue();

    $this->get('/old-cached')->assertStatus(301)->assertHeader('Location', url('/new-cached'));

    expect(Cache::has(ResolveRedirectTarget::CACHE_KEY))->toBeTrue()
        ->and(Redirect::where('source', '/old-cached')->first()?->hit_count)->toBe(1);
});

it('responds 410 gone for a redirect configured with that status code, ignoring the target', function () {
    app(CreateRedirect::class)(['source' => '/removed-page', 'target' => '/anything', 'status_code' => 410]);

    $this->get('/removed-page')->assertStatus(410);
});

it('redirects a matching public request end to end', function () {
    app(CreateRedirect::class)(['source' => '/old-url', 'target' => '/somewhere-else', 'status_code' => 302]);

    $this->get('/old-url')->assertStatus(302)->assertHeader('Location', url('/somewhere-else'));
});
