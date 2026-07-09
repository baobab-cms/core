<?php

use Baobab\Hooks\HookRegistry;
use Baobab\Tests\Fixtures\DemoHookListenerStub;

it('invokes action listeners in priority order', function () {
    $registry = new HookRegistry;
    $calls = [];

    $registry->listen('demo.event', function () use (&$calls) {
        $calls[] = 'second';
    }, priority: 20);
    $registry->listen('demo.event', function () use (&$calls) {
        $calls[] = 'first';
    }, priority: 5);

    $registry->action('demo.event');

    expect($calls)->toBe(['first', 'second']);
});

it('passes the payload to listeners', function () {
    $registry = new HookRegistry;
    $received = null;

    $registry->listen('demo.event', function ($value) use (&$received) {
        $received = $value;
    });
    $registry->action('demo.event', 'payload');

    expect($received)->toBe('payload');
});

it('resolves string listeners through the container', function () {
    app()->singleton(DemoHookListenerStub::class);

    $registry = new HookRegistry;
    $registry->listen('demo.event', DemoHookListenerStub::class);
    $registry->action('demo.event');

    expect(app(DemoHookListenerStub::class)->called)->toBeTrue();
});

it('exposes registered actions for introspection', function () {
    $registry = new HookRegistry;
    $registry->listen('demo.event', fn () => null, priority: 15);

    $actions = $registry->actions();

    expect($actions)->toHaveKey('demo.event')
        ->and($actions['demo.event'][0]['priority'])->toBe(15);
});
