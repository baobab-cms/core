<?php

declare(strict_types=1);

namespace Baobab\Admin\Access\Http\Controllers;

use Baobab\Access\Actions\CreateRole;
use Baobab\Access\Actions\GrantPermission;
use Baobab\Access\Actions\RevokePermission;
use Baobab\Access\Actions\UpdateRole;
use Baobab\Admin\Access\Http\Requests\StoreRoleRequest;
use Baobab\Admin\Access\PermissionMatrixBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(Role $role): View
    {
        $matrix = $this->builder->build();

        return view('baobab::admin.access.show', [
            'role' => $role->load(['users' => fn ($query) => $query->orderBy('name')]),
            'groups' => $matrix['groups'],
            'grants' => $matrix['grants'][$role->id] ?? [],
        ]);
    }

    public function updateSettings(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'requires_two_factor' => ['nullable', 'boolean'],
        ]);

        $validated['requires_two_factor'] = $request->boolean('requires_two_factor');

        app(UpdateRole::class)($role, $validated);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.access.show.settings_updated', ['name' => $role->name]),
        ]);

        return back();
    }
}
