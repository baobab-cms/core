<?php

use Baobab\Tests\TestCase;

it('falls back to the Core index template when no theme is active and no reading setting is configured', function () {
    /** @var TestCase $this */
    $response = $this->get('/');

    $response->assertOk();
    $response->assertViewIs('baobab::templates.index');
});
