<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sac d'erreurs dédié : la fiche utilisateur porte d'autres formulaires
 * (justification d'une permission directe, rôle…) dont les champs portent
 * les mêmes noms.
 */
final class UpdateUserProfileRequest extends FormRequest
{
    public const ERROR_BAG = 'updateProfile';

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'lost_mailbox' => ['sometimes', 'boolean'],
            'admin_password' => ['nullable', 'string'],
            'justification' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
