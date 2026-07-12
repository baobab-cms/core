<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Controllers;

use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

final class UserController
{
    public function index(): View
    {
        /** @var User $actor */
        $actor = Auth::guard('baobab')->user();

        $users = User::query()->with('roles')->orderBy('name')->get();

        return view('baobab::admin.users.index', [
            'users' => $users,
            'columns' => $this->columns($actor),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(User $actor): array
    {
        return [
            ['key' => 'name', 'label' => __('baobab::admin.users.column_name')],
            ['key' => 'email', 'label' => __('baobab::admin.users.column_email')],
            [
                'key' => 'roles',
                'label' => __('baobab::admin.users.column_roles'),
                'render' => fn (User $user) => $user->roles->pluck('name')->join(', ') ?: '—',
            ],
            [
                'key' => 'level',
                'label' => __('baobab::admin.users.column_level'),
                'render' => fn (User $user) => (string) $user->level(),
            ],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => function (User $user) use ($actor) {
                    if ($user->is($actor) || $user->level() >= $actor->level()) {
                        return '';
                    }

                    return view('baobab::admin.users.partials.impersonate-button', ['user' => $user])->render();
                },
            ],
        ];
    }
}
