<?php

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\SaveContentEntry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ViewErrorBag;

/**
 * Embed de formulaire dans un champ richtext (spec 14 §4, M8 point 6 Pass C4)
 * — noeud Tiptap dédié, marqueur `<span data-baobab-embed="form:{slug}">`.
 * Deux garanties distinctes couvertes séparément : le marqueur survit à la
 * purification (`MarkerPreservingCleanHtml`, cast Eloquent réellement câblé
 * sur un Content Type généré) ; il se résout en formulaire rendu à
 * l'affichage (`baobab.richtext.display`, `field.richtext-display`).
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
    view()->share('errors', new ViewErrorBag);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * @return array{0: ContentType, 1: class-string<Model>}
 */
function buildFormEmbedArticle(): array
{
    $contentType = app(BuildContentType::class)(contentTypeBlueprintJson('FormEmbedArticle', [
        'fields' => [
            ['key' => 'title', 'type' => 'text', 'required' => true],
            ['key' => 'body', 'type' => 'richtext'],
        ],
    ]));

    $module = Module::findOrFail($contentType->module_id);
    app(ModuleAutoloader::class)->registerFor($module);

    /** @var class-string<Model> $modelClass */
    $modelClass = $contentType->modelClass();

    return [$contentType->fresh(), $modelClass];
}

function formEmbedActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Form Embed Actor {$counter}",
        'email' => "form-embed-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('preserves a form embed marker through the richtext cast while still stripping disallowed markup', function () {
    [$type, $modelClass] = buildFormEmbedArticle();

    $body = '<p>Avant</p>'
        .'<span data-baobab-embed="form:contact" contenteditable="false">📋 Contact</span>'
        .'<script>alert(1)</script>'
        .'<p>Après</p>';

    $entry = app(SaveContentEntry::class)($type, ['title' => 'Test', 'body' => $body], formEmbedActor());

    $reloaded = $modelClass::query()->findOrFail($entry->getKey());

    expect($reloaded->getAttribute('body'))
        ->toContain('data-baobab-embed="form:contact"')
        ->not->toContain('<script>');
});

it('strips attacker-controlled attributes and child markup from a form embed marker instead of preserving it verbatim', function () {
    [$type, $modelClass] = buildFormEmbedArticle();

    $body = '<p>Avant</p>'
        .'<span data-baobab-embed="form:contact" onclick="alert(1)"><img src=x onerror="alert(2)">Contact</span>'
        .'<p>Après</p>';

    $entry = app(SaveContentEntry::class)($type, ['title' => 'Test', 'body' => $body], formEmbedActor());

    $reloaded = $modelClass::query()->findOrFail($entry->getKey());

    expect($reloaded->getAttribute('body'))
        ->toContain('data-baobab-embed="form:contact"')
        ->toContain('Contact')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('<img');
});

it('drops a malformed embed marker entirely instead of preserving it verbatim', function () {
    [$type, $modelClass] = buildFormEmbedArticle();

    $body = '<p>Avant</p>'
        .'<span data-baobab-embed="evil" onclick="alert(1)">click</span>'
        .'<p>Après</p>';

    $entry = app(SaveContentEntry::class)($type, ['title' => 'Test', 'body' => $body], formEmbedActor());

    $reloaded = $modelClass::query()->findOrFail($entry->getKey());

    expect($reloaded->getAttribute('body'))
        ->not->toContain('data-baobab-embed')
        ->not->toContain('onclick');
});

it('resolves a preserved form embed marker into the real form-embed render at display time', function () {
    app(SaveForm::class)(null, ['slug' => 'contact', 'title' => 'Contact', 'fields' => []]);

    $value = '<p>Avant</p><span data-baobab-embed="form:contact" contenteditable="false">📋 Contact</span><p>Après</p>';

    $html = Blade::render('<x-baobab::field.richtext-display :value="$value" />', ['value' => $value]);

    expect($html)->toContain(route('baobab.forms.submit', ['form' => 'contact']))
        ->not->toContain('data-baobab-embed');
});

it('drops a form embed marker at display time when the slug no longer resolves to a form', function () {
    $value = '<span data-baobab-embed="form:does-not-exist" contenteditable="false">📋 X</span>';

    $html = Blade::render('<x-baobab::field.richtext-display :value="$value" />', ['value' => $value]);

    expect(trim($html))->toBe('');
});
