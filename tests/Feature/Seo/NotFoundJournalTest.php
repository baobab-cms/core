<?php

use Baobab\Seo\Models\NotFoundHit;
use Illuminate\Support\Facades\Artisan;

it('records a 404 hit on an unknown public URL', function () {
    $this->get('/this-page-does-not-exist')->assertNotFound();

    $hit = NotFoundHit::where('path', 'this-page-does-not-exist')->first();

    expect($hit)->not->toBeNull()->and($hit->hits)->toBe(1);
});

it('increments the hit counter on a repeated 404', function () {
    $this->get('/still-missing');
    $this->get('/still-missing');
    $this->get('/still-missing');

    expect(NotFoundHit::where('path', 'still-missing')->first()?->hits)->toBe(3);
});

it('captures the referer header when present', function () {
    $this->withHeaders(['referer' => 'https://example.com/somewhere'])
        ->get('/missing-with-referer');

    expect(NotFoundHit::where('path', 'missing-with-referer')->first()?->referer)->toBe('https://example.com/somewhere');
});

it('purges 404 hits older than the configured retention', function () {
    config(['baobab.redirects.not_found_retention_days' => 30]);

    NotFoundHit::create(['path' => 'old-hit', 'hits' => 1, 'last_hit_at' => now()->subDays(40)]);
    NotFoundHit::create(['path' => 'recent-hit', 'hits' => 1, 'last_hit_at' => now()->subDays(5)]);

    Artisan::call('seo:purge-404-log');

    expect(NotFoundHit::where('path', 'old-hit')->exists())->toBeFalse()
        ->and(NotFoundHit::where('path', 'recent-hit')->exists())->toBeTrue();
});
