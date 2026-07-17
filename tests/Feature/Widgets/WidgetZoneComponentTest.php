<?php

use Baobab\Widgets\Models\WidgetInstance;
use Illuminate\Support\Facades\Blade;

it('renders active widgets of a zone', function () {
    WidgetInstance::create(['zone_key' => 'sidebar', 'widget_key' => 'baobab.custom-html', 'settings' => ['html' => '<strong>Hi</strong>'], 'order' => 0]);

    $html = Blade::render('<x-baobab::widget-zone name="sidebar" />');

    expect($html)->toContain('<strong>Hi</strong>')
        ->and($html)->toContain('widget-baobab.custom-html');
});

it('renders nothing for an empty zone', function () {
    $html = Blade::render('<x-baobab::widget-zone name="empty" />');

    expect(trim($html))->toBe('');
});
