<?php

use Baobab\Scheduler\Support\CronHumanizer;

it('describes the every-minute form', function () {
    expect(CronHumanizer::describe('* * * * *'))->toBe('Chaque minute');
});

it('describes a daily fixed-time form', function () {
    expect(CronHumanizer::describe('0 3 * * *'))->toBe('Tous les jours à 03:00');
    expect(CronHumanizer::describe('30 14 * * *'))->toBe('Tous les jours à 14:30');
});

it('describes a weekly fixed-day-and-time form', function () {
    expect(CronHumanizer::describe('0 8 * * 1'))->toBe('Chaque lundi à 08:00');
    expect(CronHumanizer::describe('0 8 * * 0'))->toBe('Chaque dimanche à 08:00');
});

it('falls back to the raw expression for an unrecognized form', function () {
    expect(CronHumanizer::describe('*/5 * * * *'))->toBe('*/5 * * * *');
    expect(CronHumanizer::describe('0 0 1 * *'))->toBe('0 0 1 * *');
});

it('falls back to the raw string for a malformed expression', function () {
    expect(CronHumanizer::describe('not a cron'))->toBe('not a cron');
});
