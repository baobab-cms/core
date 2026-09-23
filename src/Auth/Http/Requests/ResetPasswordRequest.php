<?php

declare(strict_types=1);

namespace Baobab\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La robustesse du mot de passe est vérifiée par l'Action `ResetPassword` ;
 * seule la confirmation, propre au formulaire, est vérifiée ici.
 */
final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed'],
        ];
    }
}
