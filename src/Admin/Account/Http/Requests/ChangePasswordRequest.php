<?php

declare(strict_types=1);

namespace Baobab\Admin\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sac d'erreurs dédié : la modale de désactivation de la 2FA, sur le même
 * écran, s'ouvre sur une erreur `current_password` du sac par défaut.
 *
 * Mot de passe actuel et robustesse sont vérifiés par l'Action
 * `ChangePassword` ; seule la confirmation est vérifiée ici.
 */
final class ChangePasswordRequest extends FormRequest
{
    public const ERROR_BAG = 'updatePassword';

    /** @var string */
    protected $errorBag = self::ERROR_BAG;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed'],
        ];
    }
}
