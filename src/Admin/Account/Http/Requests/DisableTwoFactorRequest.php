<?php

declare(strict_types=1);

namespace Baobab\Admin\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DisableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:baobab'],
        ];
    }
}
