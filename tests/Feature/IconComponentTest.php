<?php

use Illuminate\Support\Facades\Blade;

it('renders the SVG for a valid icon name', function () {
    $html = Blade::render('<x-baobab::icon name="bi-house-door" />');

    expect($html)->toContain('<svg');
});

it('falls back to the generic icon when the name does not resolve', function () {
    $html = Blade::render('<x-baobab::icon name="bi-not-a-real-icon" />');

    expect($html)->toContain('<svg');
});

it('renders nothing when no name is given', function () {
    $html = Blade::render('<x-baobab::icon />');

    expect(trim($html))->toBe('');
});
