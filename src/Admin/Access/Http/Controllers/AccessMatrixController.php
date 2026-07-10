<?php

declare(strict_types=1);

namespace Baobab\Admin\Access\Http\Controllers;

use Baobab\Access\Actions\CreateRole;
use Baobab\Access\Actions\GrantPermission;
use Baobab\Access\Actions\RevokePermission;
use Baobab\Admin\Access\Http\Requests\StoreRoleRequest;
use Baobab\Admin\Access\PermissionMatrixBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Spatie\Permission\Models\Role;

final class AccessMatrixController
{
    public function __construct(private readonly PermissionMatrixBuilder $builder) {}

    public function index(): View
    {
        return view('baobab::admin.access.index', $this->builder->build());
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = app(CreateRole::class)(
            $request->string('name')->toString(),
            $request->integer('level'),
        );

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.access.role_created', ['name' => $role->name]),
        ]);

        return back();
    }

    public function toggle(Role $role, string $permission): RedirectResponse
    {
        abort_if($role->name === 'super-admin', 403);

        if ($role->hasPermissionTo($permission, 'baobab')) {
            app(RevokePermission::class)($role, $permission);
        } else {
            app(GrantPermission::class)($role, $permission);
        }

        return back();
    }
}
