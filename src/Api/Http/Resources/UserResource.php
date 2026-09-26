<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Resources;

use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un utilisateur tel que l'API l'expose (spec 05 §7, décision 5) : identité,
 * rôles et état de l'invitation. Jamais de secret — mot de passe, jetons,
 * 2FA restent hors de la représentation.
 *
 * @property User $resource
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->map(fn (Model $role): array => [
                'name' => (string) $role->getAttribute('name'),
                'level' => (int) $role->getAttribute('level'),
            ])->values()->all(),
            'level' => $user->level(),
            'invitation_pending' => $user->hasPendingInvitation(),
            'invited_at' => $user->invited_at?->toIso8601String(),
            'deactivated' => $user->isDeactivated(),
            'deactivated_at' => $user->deactivated_at?->toIso8601String(),
            'deactivation_reason' => $user->deactivation_reason,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
