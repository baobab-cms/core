<?php

use Baobab\Tests\TestCase;

it('loads the Baobab welcome page from the core package', function () {
    /** @var TestCase $this */
    $response = $this->get('/');

    $response->assertOk();
    $response->assertViewIs('baobab::welcome');
    $response->assertSee('Baobab est là');
});
