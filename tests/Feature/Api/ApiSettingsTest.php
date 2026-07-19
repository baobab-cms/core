<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Api\Models\ApiSetting;
use Baobab\Auth\Actions\CreateApiToken;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('serves the API normally while rest_enabled is true (the default)', function () {
    [, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    $this->getJson('/api/v1/content/api-cars')->assertOk();
});

it('responds 404 to every REST endpoint once rest_enabled is turned off globally', function () {
    [, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    ApiSetting::current()->fill(['rest_enabled' => false])->save();

    $this->getJson('/api/v1/content/api-cars')
        ->assertStatus(404)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/not-found');
});

it('responds 404 only for a content type with api_enabled disabled in its blueprint', function () {
    [, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    [, $disabledClass] = buildApiCar(['key' => 'ApiCarDisabled', 'api_enabled' => false]);
    $disabledClass::create(['brand' => 'Renault', 'price' => 20000, 'internal_note' => '', 'slug' => 'renault', 'status' => 'published']);

    $this->getJson('/api/v1/content/api-cars')->assertOk();
    $this->getJson('/api/v1/content/api-car-disableds')->assertStatus(404);
});

it('returns 429 with an RFC 9457 body once the per-minute rate limit is exceeded', function () {
    [, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);
    $actor = apiActor(['content.api_car.view']);
    $token = app(CreateApiToken::class)($actor, 'ci', ['content.api_car.view']);

    ApiSetting::current()->fill(['rate_limit_per_minute' => 2])->save();

    $request = fn () => $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->getJson('/api/v1/content/api-cars');

    $request()->assertOk();
    $request()->assertOk();

    $request()
        ->assertStatus(429)
        ->assertJsonPath('type', 'https://docs.baobabcms.com/errors/rate-limited')
        ->assertJsonPath('status', 429);
});

it('falls back to per-IP keying for the rate limit on anonymous requests', function () {
    [, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    ApiSetting::current()->fill(['rate_limit_per_minute' => 1])->save();

    $this->getJson('/api/v1/content/api-cars')->assertOk();
    $this->getJson('/api/v1/content/api-cars')->assertStatus(429);
});

it('sets CORS headers only when the request Origin is in the configured allow-list', function () {
    [, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    ApiSetting::current()->fill(['allowed_origins' => "https://front.example.com\n"])->save();

    $this->withHeader('Origin', 'https://front.example.com')
        ->getJson('/api/v1/content/api-cars')
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://front.example.com');

    $this->withHeader('Origin', 'https://evil.example.com')
        ->getJson('/api/v1/content/api-cars')
        ->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('answers an OPTIONS preflight from an allowed origin with 204 without reaching the controller', function () {
    [, $carClass] = buildApiCar();
    $carClass::create(['brand' => 'Peugeot', 'price' => 25000, 'internal_note' => '', 'slug' => 'peugeot', 'status' => 'published']);

    ApiSetting::current()->fill(['allowed_origins' => 'https://front.example.com'])->save();

    $this->withHeader('Origin', 'https://front.example.com')
        ->json('OPTIONS', '/api/v1/content/api-cars')
        ->assertStatus(204)
        ->assertHeader('Access-Control-Allow-Origin', 'https://front.example.com')
        ->assertHeader('Access-Control-Allow-Methods');
});

it('denies access to the API settings screen without baobab.system.api.manage', function () {
    $user = User::create(['name' => 'No access', 'email' => 'no-api-access@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $this->actingAs($user, 'baobab')
        ->get(route('admin.api.index'))
        ->assertForbidden();
});

it('shows and persists updates on the API settings screen for a user with baobab.system.api.manage', function () {
    $user = User::create(['name' => 'API admin', 'email' => 'api-admin@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.api.manage');

    $this->actingAs($user, 'baobab')
        ->get(route('admin.api.index'))
        ->assertOk();

    $this->actingAs($user, 'baobab')
        ->post(route('admin.api.update'), [
            'rest_enabled' => '1',
            'rate_limit_per_minute' => 120,
            'allowed_origins' => 'https://front.example.com',
        ])
        ->assertRedirect(route('admin.api.index'));

    $setting = ApiSetting::current();

    expect($setting->rest_enabled)->toBeTrue()
        ->and($setting->rate_limit_per_minute)->toBe(120)
        ->and($setting->allowed_origins)->toBe('https://front.example.com');
});
