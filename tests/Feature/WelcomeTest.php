<?php

it('loads the Baobab welcome page from the core package', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertViewIs('baobab::welcome');
    $response->assertSee('Baobab est là');
});
