<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Models\DirectPermissionGrant;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use InvalidArgumentException;

/**
 * Spec 05 §6.3 : attribuer une permission directement à un utilisateur est
 * exceptionnel et tracé — l'interface exige une justification. Délègue le
 * grant Spatie lui-même à {@see GrantPermission} (inchangée, toujours
 * utilisée telle quelle pour les rôles) et n'ajoute que le suivi de
 * l'exception (justification, auteur) dans {@see DirectPermissionGrant}.
 */
final class GrantDirectPermission
{
    public function __construct(private readonly AccessManager $manager) {}

    public function __invoke(User $user, string $permission, string $justification, ?User $grantedBy, string $guard = 'baobab'): DirectPermissionGrant
    {
        $justification = trim($justification);

        if ($justification === '') {
            throw new InvalidArgumentException('A justification is required to grant a direct permission.');
        }

        app(GrantPermission::class)($user, $permission, $guard);

        $permissionModel = $this->manager->findOrCreatePermission($permission, $guard);

        $grant = DirectPermissionGrant::updateOrCreate(
            ['user_id' => $user->id, 'permission_id' => $permissionModel->getKey()],
            ['justification' => $justification, 'granted_by' => $grantedBy?->id],
        );

        Hook::action('baobab.access.direct_permission.granted', $user, $permission, $justification);

        return $grant;
    }
}
