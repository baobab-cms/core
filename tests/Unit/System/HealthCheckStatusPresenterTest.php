<?php

use Baobab\System\HealthCheckStatusPresenter;

it('maps ok to a success badge', function () {
    expect(HealthCheckStatusPresenter::badgeVariant('ok'))->toBe('success')
        ->and(HealthCheckStatusPresenter::label('ok'))->toBe('OK');
});

it('maps warning to a warning badge', function () {
    expect(HealthCheckStatusPresenter::badgeVariant('warning'))->toBe('warning')
        ->and(HealthCheckStatusPresenter::label('warning'))->toBe('Avertissement');
});

it('maps anything else, including failed, to a danger badge', function () {
    expect(HealthCheckStatusPresenter::badgeVariant('failed'))->toBe('danger')
        ->and(HealthCheckStatusPresenter::badgeVariant('crashed'))->toBe('danger')
        ->and(HealthCheckStatusPresenter::label('failed'))->toBe('Échec');
});
