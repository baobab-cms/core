<?php

use Baobab\Hooks\HookRegistry;
use Baobab\Tests\Fixtures\DemoHookListenerStub;
use Baobab\Tests\Fixtures\FilterDoubleStub;

// ── Actions (existing behaviour, unchanged) ───────────────────────────────────

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

// ── Filters ───────────────────────────────────────────────────────────────────

it('applies filter listeners in priority order and threads the value', function () {
    $registry = new HookRegistry;

    $registry->modify('demo.filter', fn (int $v) => $v + 10, priority: 20);
    $registry->modify('demo.filter', fn (int $v) => $v * 2, priority: 5);

    // priority 5 runs first: 3 * 2 = 6, then priority 20: 6 + 10 = 16
    expect($registry->filter('demo.filter', 3))->toBe(16);
});

it('passes context arguments to filter listeners', function () {
    $registry = new HookRegistry;
    $captured = null;

    $registry->modify('demo.filter', function (string $value, string $ctx) use (&$captured): string {
        $captured = $ctx;

        return $value;
    });

    $registry->filter('demo.filter', 'hello', 'world');

    expect($captured)->toBe('world');
});

it('returns the original value when no filter listener is registered', function () {
    $registry = new HookRegistry;

    expect($registry->filter('no.listeners', 'original'))->toBe('original');
});

it('resolves string filter listeners through the container', function () {
    app()->singleton(FilterDoubleStub::class);

    $registry = new HookRegistry;
    $registry->modify('demo.filter', FilterDoubleStub::class);

    expect($registry->filter('demo.filter', 5))->toBe(10);
});

it('exposes registered filters for introspection', function () {
    $registry = new HookRegistry;
    $registry->modify('demo.filter', fn (mixed $v) => $v, priority: 7);

    $filters = $registry->filters();

    expect($filters)->toHaveKey('demo.filter')
        ->and($filters['demo.filter'][0]['priority'])->toBe(7);
});
