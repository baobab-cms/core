<?php

use Baobab\Modules\SemverConstraint;

it('accepts a version satisfying a caret constraint', function () {
    expect(SemverConstraint::satisfiedBy('^1.0', '1.4.2'))->toBeTrue();
});

it('rejects a version outside a caret constraint', function () {
    expect(SemverConstraint::satisfiedBy('^1.0', '2.0.0'))->toBeFalse();
});

it('accepts a version satisfying a tilde constraint', function () {
    expect(SemverConstraint::satisfiedBy('~1.2.3', '1.2.9'))->toBeTrue();
});

it('rejects a version outside a tilde constraint', function () {
    expect(SemverConstraint::satisfiedBy('~1.2.3', '1.3.0'))->toBeFalse();
});

it('accepts a version satisfying an AND range', function () {
    expect(SemverConstraint::satisfiedBy('>=1.0,<2.0', '1.9.9'))->toBeTrue();
});

it('rejects a version outside an AND range', function () {
    expect(SemverConstraint::satisfiedBy('>=1.0,<2.0', '2.0.0'))->toBeFalse();
});

it('matches an exact version', function () {
    expect(SemverConstraint::satisfiedBy('1.0.0', '1.0.0'))->toBeTrue();
    expect(SemverConstraint::satisfiedBy('1.0.0', '1.0.1'))->toBeFalse();
});
