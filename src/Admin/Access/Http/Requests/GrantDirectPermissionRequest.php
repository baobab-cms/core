<?php

declare(strict_types=1);

namespace Baobab\Admin\Access\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GrantDirectPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'permission' => ['required', 'string', 'exists:baobab_permissions,name'],
            'justification' => ['required', 'string', 'max:1000'],
        ];
    }
}
