<?php

use Baobab\ContentTypes\Fields\Types\DateField;
use Baobab\ContentTypes\Fields\Types\DateTimeField;
use Baobab\ContentTypes\Fields\Types\TimeField;

it('DateField builds a nullable date column', function () {
    $field = new DateField;

    expect($field->columnDefinition('birthdate', []))->toBe("\$table->date('birthdate')->nullable();")
        ->and($field->rules('birthdate', []))->toBe(['date'])
        ->and($field->cast([]))->toBe('date')
        ->and($field->graphqlType([]))->toBe('Date');
});

it('DateTimeField builds a nullable datetime column', function () {
    $field = new DateTimeField;

    expect($field->columnDefinition('starts_at', []))->toBe("\$table->dateTime('starts_at')->nullable();")
        ->and($field->rules('starts_at', []))->toBe(['date'])
        ->and($field->cast([]))->toBe('datetime')
        ->and($field->graphqlType([]))->toBe('DateTime');
});

it('TimeField builds a nullable time column with an H:i:s format rule', function () {
    $field = new TimeField;

    expect($field->columnDefinition('opens_at', []))->toBe("\$table->time('opens_at')->nullable();")
        ->and($field->rules('opens_at', []))->toBe(['date_format:H:i:s'])
        ->and($field->cast([]))->toBeNull()
        ->and($field->graphqlType([]))->toBe('Time');
});
