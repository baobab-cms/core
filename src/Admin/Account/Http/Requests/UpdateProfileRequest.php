<?php

declare(strict_types=1);

namespace Baobab\Admin\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nom et e-mail du compte connecté (spec 05 §5, décision 5 f et j). Les
 * règles qui dépendent de l'état du compte — e-mail déjà pris, rien à
 * changer, invitation en attente — sont vérifiées par l'Action
 * `RequestProfileChange`.
 */
final class UpdateProfileRequest extends FormRequest
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
        ];
    }
}
