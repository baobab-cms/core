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

        return view('baobab::admin.users.index', ['users' => $users, 'actor' => $actor]);
    }
}
