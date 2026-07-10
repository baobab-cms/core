<?php

declare(strict_types=1);

namespace Baobab\Admin\Access\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('baobab_roles', 'name')->where('guard_name', 'baobab'),
            ],
            'level' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }
}
