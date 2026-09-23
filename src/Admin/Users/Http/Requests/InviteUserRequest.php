<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme seulement : l'unicité de l'e-mail, l'existence du rôle et la
 * hiérarchie sont vérifiées par l'Action `InviteUser`, pour tous ses clients.
 */
final class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string'],
        ];
    }
}
