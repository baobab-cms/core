<?php

it('dumps the register to the console with every core declaration', function () {
    $this->artisan('baobab:privacy:register')
        ->expectsOutputToContain(__('baobab::privacy.users.title'))
        ->expectsOutputToContain('core.audit_log')
        ->expectsOutputToContain(__('baobab::admin.privacy_register.no_recipients'))
        ->assertSuccessful();
});
