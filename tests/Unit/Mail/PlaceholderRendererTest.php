<?php

use Baobab\Mail\Support\PlaceholderRenderer;

it('inserts a simple variable, HTML-escaped', function () {
    $rendered = app(PlaceholderRenderer::class)->render(
        'Bonjour {{ user.name }} !',
        ['user' => ['name' => 'Renault <Admin>']],
    );

    expect($rendered)->toBe('Bonjour Renault &lt;Admin&gt; !');
});

it('renders an unknown placeholder as empty and logs a warning', function () {
    $rendered = app(PlaceholderRenderer::class)->render('Bonjour {{ user.name }} !', []);

    expect($rendered)->toBe('Bonjour  !');
});

it('keeps a conditional block when the variable is truthy', function () {
    $rendered = app(PlaceholderRenderer::class)->render(
        '{{# if post.title }}Article : {{ post.title }}{{/ if }}',
        ['post' => ['title' => 'Mon article']],
    );

    expect($rendered)->toBe('Article : Mon article');
});

it('drops a conditional block when the variable is absent', function () {
    $rendered = app(PlaceholderRenderer::class)->render(
        'Avant.{{# if post.title }}Article : {{ post.title }}{{/ if }}Après.',
        [],
    );

    expect($rendered)->toBe('Avant.Après.');
});

it('has no loops or expressions — a literal double-brace stays untouched outside the mini-syntax', function () {
    $rendered = app(PlaceholderRenderer::class)->render('{{ 1 + 1 }}', []);

    // "1 + 1" ne matche pas le motif [a-zA-Z0-9_.]+ : rendu tel quel, jamais évalué.
    expect($rendered)->toBe('{{ 1 + 1 }}');
});
