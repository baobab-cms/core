<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Privacy\Contracts\ExportsPersonalData;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;

/**
 * Socle commun des fournisseurs Core : résout, pour un sujet donné par
 * identifiant OU par e-mail, l'autre moitié de son identité (un sujet
 * « e-mail » peut avoir un compte, un sujet « compte » a toujours une
 * adresse).
 */
abstract class CoreProvider implements ExportsPersonalData
{
    protected function userIdOf(Subject $subject): ?int
    {
        if ($subject->userId !== null) {
            return $subject->userId;
        }

        if ($subject->email === null) {
            return null;
        }

        $id = User::query()->whereRaw('LOWER(email) = ?', [$subject->email])->value('id');

        return $id === null ? null : (int) $id;
    }

    protected function emailOf(Subject $subject): ?string
    {
        if ($subject->email !== null) {
            return $subject->email;
        }

        $email = User::query()->whereKey($subject->userId)->value('email');

        return is_string($email) ? mb_strtolower($email) : null;
    }

    /**
     * Services externes par lesquels transitent les e-mails (spec 16 §6) :
     * tout driver autre que `log`/`array`/`null` sort du serveur.
     *
     * @return list<string>
     */
    protected function mailServices(): array
    {
        $driver = (string) config('mail.default', 'log');

        if (in_array($driver, ['log', 'array', 'null', ''], true)) {
            return [];
        }

        return [__('baobab::privacy.services.mail', ['driver' => $driver])];
    }
}
