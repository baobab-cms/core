<?php

use Baobab\Seo\Actions\ComposeRobotsTxt;
use Baobab\Seo\Models\SeoSetting;

it('blocks everything outside production by default', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertSee("User-agent: *\nDisallow: /", false);
});

it('serves the default safe content in production', function () {
    $this->app['env'] = 'production';

    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertSee('Allow: /', false)
        ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
});

it('serves the custom robots_txt content in production when set', function () {
    $this->app['env'] = 'production';
    SeoSetting::current()->fill(['robots_txt' => "User-agent: *\nDisallow: /private\n"])->save();

    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /private', false);
});

it('ignores the custom robots_txt while the staging protection is active', function () {
    SeoSetting::current()->fill(['robots_txt' => "User-agent: *\nAllow: /private\n"])->save();

    $this->get('/robots.txt')->assertSee("Disallow: /\n", false)->assertDontSee('/private', false);
});

it('honors force_index_on_staging outside production', function () {
    SeoSetting::current()->fill(['force_index_on_staging' => true])->save();

    $this->get('/robots.txt')->assertSee('Allow: /', false);
});

it('validates robots.txt syntax, accepting comments and known directives', function () {
    $composer = app(ComposeRobotsTxt::class);

    expect($composer->isValid("# a comment\nUser-agent: *\nDisallow: /private\nSitemap: https://example.com/sitemap.xml\n"))->toBeTrue()
        ->and($composer->isValid("Ceci n'est pas une directive robots.txt"))->toBeFalse();
});
