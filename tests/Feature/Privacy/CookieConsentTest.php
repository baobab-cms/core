<?php

use Baobab\Facades\Hook;
use Baobab\Forms\Models\Form;
use Baobab\Modules\Models\Module;
use Baobab\Privacy\Actions\BuildCookieRegister;
use Baobab\Privacy\Cookies\CookieCategory;
use Baobab\Privacy\Cookies\CookieDeclaration;
use Baobab\Privacy\Cookies\CookieRegister;
use Baobab\Rendering\RenderedTheme;
use Baobab\Themes\ThemeViewRegistrar;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

/**
 * Consentement aux cookies (spec 16 §3.2, M9 chantier 0.b Pass F1) :
 * registre des déclarations, composant `<x-baobab::consent-banner />`,
 * surcharge par le thème (support `cookie-banner`).
 */

/**
 * @param  list<array{name: string, category: string, purpose: string, duration: string, provider?: string}>  $cookies
 */
function consentModule(string $name, array $cookies, string $status = 'active', string $type = 'module', array $theme = []): Module
{
    $path = sys_get_temp_dir().'/baobab-test-cookie-consent/'.str_replace('/', '-', $name);
    File::deleteDirectory($path);
    File::ensureDirectoryExists($path);

    $manifest = ['name' => $name, 'privacy' => ['cookies' => $cookies]];

    if ($theme !== []) {
        $manifest['theme'] = $theme;
    }

    return Module::create([
        'name' => $name,
        'title' => $name,
        'type' => $type,
        'version' => '1.0.0',
        'provider' => 'Acme\\Consent\\Provider',
        'source' => 'local',
        'path' => $path,
        'manifest' => $manifest,
        'status' => $status,
    ]);
}

function analyticsCookie(string $name = '_consent_stats'): array
{
    return ['name' => $name, 'category' => 'analytics', 'purpose' => 'Mesure d\'audience', 'duration' => '13 mois'];
}

afterEach(function (): void {
    File::deleteDirectory(sys_get_temp_dir().'/baobab-test-cookie-consent');
    app(RenderedTheme::class)->decide(null);
    app(ThemeViewRegistrar::class)->registerFor(null);
});

it('declares the Core cookies as necessary only, so a bare install asks no consent', function () {
    $register = app(BuildCookieRegister::class)();

    $names = array_map(fn (CookieDeclaration $cookie): string => $cookie->name, $register->cookies);

    expect($names)->toContain(config('session.cookie'), 'XSRF-TOKEN', 'remember_baobab_*', 'baobab_consent')
        ->and($register->inCategory(CookieCategory::Necessary))->toHaveCount(count($register->cookies))
        ->and($register->requiresConsent())->toBeFalse()
        ->and($register->consentCategories())->toBe([]);
});

it('adds the cookies declared by active modules, with their source', function () {
    consentModule('acme/consent-analytics', [analyticsCookie()]);

    $register = app(BuildCookieRegister::class)();
    $declared = $register->inCategory(CookieCategory::Analytics);

    expect($declared)->toHaveCount(1)
        ->and($declared[0]->name)->toBe('_consent_stats')
        ->and($declared[0]->source)->toBe('acme/consent-analytics')
        ->and($register->consentCategories())->toBe([CookieCategory::Analytics]);
});

it('ignores the cookies of an inactive module', function () {
    consentModule('acme/consent-inactive', [analyticsCookie()], status: 'inactive');

    expect(app(BuildCookieRegister::class)()->requiresConsent())->toBeFalse();
});

it('declares a captcha provider only once a form uses it (spec 16 decision 22)', function () {
    $captchaNames = fn (): array => array_map(
        fn (CookieDeclaration $cookie): string => $cookie->name,
        array_filter(app(BuildCookieRegister::class)()->cookies, fn (CookieDeclaration $cookie): bool => $cookie->provider !== null),
    );

    expect($captchaNames())->toBe([]);

    Form::create([
        'slug' => 'consent-captcha',
        'title' => 'Consent captcha',
        'version' => 1,
        'blueprint' => ['fields' => []],
        'settings' => ['anti_spam' => ['captcha' => ['provider' => 'turnstile', 'site_key' => 'key']]],
    ]);

    $register = app(BuildCookieRegister::class)();

    expect(array_values($captchaNames()))->toBe(['Turnstile'])
        ->and($register->requiresConsent())->toBeFalse();
});

it('lets a PHP declaration join through the baobab.privacy.cookies filter, and drops anything else', function () {
    Hook::modify('baobab.privacy.cookies', function (array $cookies): array {
        $cookies[] = new CookieDeclaration('_consent_hooked', CookieCategory::Marketing, 'Publicité', '6 mois', 'Acme Ads', 'acme/hooked');
        $cookies[] = 'not a declaration';

        return $cookies;
    });

    $register = app(BuildCookieRegister::class)();

    expect($register->consentCategories())->toBe([CookieCategory::Marketing])
        ->and(array_filter($register->cookies, fn ($cookie): bool => ! $cookie instanceof CookieDeclaration))->toBe([]);
});

it('changes the fingerprint when the declared categories change, not when a category gains a cookie', function () {
    $cookie = fn (string $name, CookieCategory $category): CookieDeclaration => new CookieDeclaration($name, $category, 'p', 'd', null, 'core');

    $analytics = new CookieRegister([$cookie('a', CookieCategory::Analytics)]);
    $moreAnalytics = new CookieRegister([$cookie('a', CookieCategory::Analytics), $cookie('b', CookieCategory::Analytics), $cookie('s', CookieCategory::Necessary)]);
    $withMarketing = new CookieRegister([$cookie('a', CookieCategory::Analytics), $cookie('m', CookieCategory::Marketing)]);

    expect($moreAnalytics->fingerprint())->toBe($analytics->fingerprint())
        ->and($withMarketing->fingerprint())->not->toBe($analytics->fingerprint());
});

it('renders nothing, neither banner nor script, while no module declares a non-necessary cookie', function () {
    expect(trim(Blade::render('<x-baobab::consent-banner />')))->toBe('');
});

it('renders the Core banner and the configured script once a module declares an analytics cookie', function () {
    config(['baobab.privacy.consent_lifetime_days' => 90]);
    consentModule('acme/consent-banner', [analyticsCookie()]);

    $html = Blade::render('<x-baobab::consent-banner />');
    $fingerprint = app(BuildCookieRegister::class)()->fingerprint();

    expect($html)->toContain('data-baobab-consent-banner')
        ->and($html)->toContain('class="bb-consent"')
        ->and($html)->toContain('data-baobab-consent-category="analytics"')
        ->and($html)->not->toContain('data-baobab-consent-category="marketing"')
        ->and($html)->toContain('data-baobab-consent-action="reject-all"')
        ->and($html)->toContain('data-baobab-consent-action="accept-all"')
        ->and($html)->toMatch('#src="/baobab/consent/consent\.js\?v=[0-9a-f]{10}"#')
        ->and($html)->toContain('data-categories="analytics"')
        ->and($html)->toContain("data-fingerprint=\"{$fingerprint}\"")
        ->and($html)->toContain('data-lifetime="90"')
        ->and(File::exists(public_path('baobab/consent/consent.js')))->toBeTrue();
});

it('renders the theme partial instead of the Core banner when the rendered theme declares cookie-banner', function () {
    consentModule('acme/consent-override', [analyticsCookie()]);
    $theme = consentModule('acme/consent-theme', [], type: 'theme', theme: ['supports' => ['cookie-banner']]);
    File::ensureDirectoryExists($theme->path.'/resources/views/partials');
    File::put($theme->path.'/resources/views/partials/cookie-banner.blade.php', '<div data-baobab-consent-banner hidden class="theme-banner">@foreach ($categories as $category){{ $category[\'key\'] }}@endforeach|{{ $necessary[\'label\'] }}</div>');

    app(RenderedTheme::class)->decide($theme);
    app(ThemeViewRegistrar::class)->registerFor($theme);

    $html = Blade::render('<x-baobab::consent-banner />');

    expect($html)->toContain('class="theme-banner"')
        ->and($html)->toContain('analytics|'.CookieCategory::Necessary->label())
        ->and($html)->not->toContain('class="bb-consent"')
        ->and($html)->toContain('data-baobab-consent-config');
});

it('keeps the Core banner when the theme ships the partial without declaring the support', function () {
    consentModule('acme/consent-undeclared', [analyticsCookie()]);
    $theme = consentModule('acme/consent-silent-theme', [], type: 'theme', theme: ['supports' => []]);
    File::ensureDirectoryExists($theme->path.'/resources/views/partials');
    File::put($theme->path.'/resources/views/partials/cookie-banner.blade.php', '<div class="theme-banner"></div>');

    app(RenderedTheme::class)->decide($theme);
    app(ThemeViewRegistrar::class)->registerFor($theme);

    $html = Blade::render('<x-baobab::consent-banner />');

    expect($html)->toContain('class="bb-consent"')
        ->and($html)->not->toContain('class="theme-banner"');
});

it('keeps the Core fallback pages free of any consent script without a declared tracker', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('consent.js')
        ->and($html)->not->toContain('data-baobab-consent-banner');
});

it('adds the banner and the manage link to the Core fallback pages once a tracker is declared', function () {
    consentModule('acme/consent-fallback', [analyticsCookie()]);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-baobab-consent-banner')
        ->and($html)->toContain('/baobab/consent/consent.js')
        ->and($html)->toContain('data-baobab-consent-open');
});
