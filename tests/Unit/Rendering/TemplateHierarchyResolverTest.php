<?php

use Baobab\Rendering\TemplateHierarchyResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

function themeViewsPath(): string
{
    return sys_get_temp_dir().'/baobab-test-theme-views';
}

afterEach(function () {
    File::deleteDirectory(themeViewsPath());
});

it('resolves the Core default when no theme view matches', function () {
    $view = app(TemplateHierarchyResolver::class)->resolve(['does-not-exist', 'index']);

    expect($view)->toBe('baobab::templates.index');
});

it('falls through candidates in order until one resolves', function () {
    $view = app(TemplateHierarchyResolver::class)->resolve(['single-car-peugeot-208', 'single-car', 'single', 'index']);

    expect($view)->toBe('baobab::templates.single');
});

it('prefers the active theme view over the Core default', function () {
    File::ensureDirectoryExists(themeViewsPath().'/templates');
    File::put(themeViewsPath().'/templates/single.blade.php', '<p>theme single</p>');
    View::addNamespace('theme', themeViewsPath());

    $view = app(TemplateHierarchyResolver::class)->resolve(['single-car', 'single', 'index']);

    expect($view)->toBe('theme::templates.single');
});
