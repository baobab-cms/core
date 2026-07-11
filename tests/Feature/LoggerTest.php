<?php

use Baobab\Support\Logger;
use Baobab\Users\Models\User;

it('writes to the dedicated baobab channel, isolated from the default stack', function () {
    app(Logger::class)->info('Test message from LoggerTest');

    expect(file_exists(baobabLogPath()))->toBeTrue();
    expect(file_get_contents(baobabLogPath()))->toContain('Test message from LoggerTest');
});

it('defaults the context to source=core when none is given', function () {
    app(Logger::class)->warning('Core-sourced message');

    expect(file_get_contents(baobabLogPath()))->toContain('"source":"core"');
});

it('preserves an explicit source and slug', function () {
    app(Logger::class)->error('Module-sourced message', ['source' => 'module', 'slug' => 'blog']);

    $content = file_get_contents(baobabLogPath());

    expect($content)->toContain('"source":"module"')
        ->and($content)->toContain('"slug":"blog"');
});

it('enriches every entry with the current actor and a per-request id', function () {
    $user = User::create(['name' => 'Log Tester', 'email' => 'logtester@example.com', 'password' => 'secret']);
    test()->actingAs($user, 'baobab');

    app(Logger::class)->info('Actor-enriched message');

    $content = file_get_contents(baobabLogPath());

    expect($content)->toContain('"user_id":'.$user->id)
        ->and($content)->toContain('"request_id":');
});
