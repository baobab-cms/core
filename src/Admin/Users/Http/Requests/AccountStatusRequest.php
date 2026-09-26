<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Requests;

use Baobab\Users\Actions\DeactivateUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme seulement : l'état du compte, la hiérarchie et les garde-fous sont
 * vérifiés par les Actions `DeactivateUser` et `ReactivateUser`, pour tous
 * leurs clients. Le motif est facultatif (spec 05 §5, décision 5 k).
 */
final class AccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:'.DeactivateUser::MAX_REASON_LENGTH],
        ];
    }
}
