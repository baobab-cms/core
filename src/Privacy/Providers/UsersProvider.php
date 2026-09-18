<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\Subject;

/** `core.users` (spec 16 §2.1) : le profil du compte. */
final class UsersProvider extends CoreProvider
{
    public function key(): string
    {
        return 'core.users';
    }

    public function describe(): DataDeclaration
    {
        return new DataDeclaration(
            title: __('baobab::privacy.users.title'),
            nature: __('baobab::privacy.users.nature'),
            purpose: __('baobab::privacy.users.purpose'),
            legalBasis: __('baobab::privacy.users.legal_basis'),
            retention: __('baobab::privacy.users.retention'),
            externalServices: $this->mailServices(),
        );
    }

    public function locate(Subject $subject): bool
    {
        return $this->userIdOf($subject) !== null;
    }
}
