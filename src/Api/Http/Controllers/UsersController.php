<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Controllers;

use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Api\Http\Resources\UserResource;
use Baobab\Api\Support\ApiActor;
use Baobab\Users\Actions\CancelUserInvitation;
use Baobab\Users\Actions\GrantUserRole;
use Baobab\Users\Actions\InviteUser;
use Baobab\Users\Actions\RevokeUserRole;
use Baobab\Users\Actions\SendUserInvitation;
use Baobab\Users\Exceptions\InvitationNotPendingException;
use Baobab\Users\Models\User;
use Baobab\Users\UserDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;

/**
 * `/api/v1/users` (spec 05 §7, décision 5) — adaptateur mince des mêmes
 * Actions que l'admin. Lecture sous `baobab.users.manage` ou
 * `baobab.users.impersonate`, écriture sous `baobab.users.manage` ; les
 * refus de hiérarchie et d'anti-lockout répondent 403, un renvoi sans objet
 * 409 (RFC 9457, spec 08 §2.2).
 */
final class UsersController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeBrowse();

        $query = User::query()->with('roles')->orderBy('name');

        /** @var array<string, mixed> $filters */
        $filters = (array) $request->query('filter', []);

        if (isset($filters['role']) && is_string($filters['role'])) {
            $query->role($filters['role'], 'baobab');
        }

        if (isset($filters['pending'])) {
            filter_var($filters['pending'], FILTER_VALIDATE_BOOLEAN)
                ? $query->whereNotNull('invited_at')
                : $query->whereNull('invited_at');
        }

        $perPage = min(max((int) ($request->query('per_page') ?? 25), 1), 100);
        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (User $user): array => (new UserResource($user))->resolve($request))
                ->all(),
            'meta' => ['pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
            ]],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, string $user): JsonResponse
    {
        $this->authorizeBrowse();

        return response()->json(['data' => (new UserResource($this->findUserOrFail($user)))->resolve($request)]);
    }

    public function store(Request $request, InviteUser $invite): JsonResponse
    {
        $actor = $this->authorizeManage();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string'],
        ]);

        try {
            $user = $invite($actor, $validated['name'], $validated['email'], $validated['role']);
        } catch (HierarchyViolationException) {
            abort(403, __('baobab::admin.users.invite.role_forbidden'));
        }

        return response()->json(
            ['data' => (new UserResource($user->load('roles')))->resolve($request)],
            201,
            ['Location' => route('api.v1.users.show', ['user' => $user->id])],
        );
    }

    public function resendInvitation(string $user, SendUserInvitation $send): Response
    {
        $actor = $this->authorizeManage();

        try {
            $send($this->findUserOrFail($user), $actor);
        } catch (HierarchyViolationException) {
            abort(403);
        } catch (InvitationNotPendingException) {
            abort(409, __('baobab::admin.users.invite.not_pending'));
        }

        return response()->noContent(202);
    }

    public function cancelInvitation(string $user, CancelUserInvitation $cancel): Response
    {
        $actor = $this->authorizeManage();

        try {
            $cancel($actor, $this->findUserOrFail($user));
        } catch (HierarchyViolationException) {
            abort(403);
        } catch (InvitationNotPendingException) {
            abort(409, __('baobab::admin.users.invite.not_pending'));
        }

        return response()->noContent();
    }

    public function grantRole(Request $request, string $user, GrantUserRole $grant): JsonResponse
    {
        $actor = $this->authorizeManage();

        $target = $this->findUserOrFail($user);
        $validated = $request->validate(['role' => ['required', 'string']]);
        $role = $this->findRoleOrFail($validated['role']);

        try {
            $grant($actor, $target, $role);
        } catch (HierarchyViolationException) {
            abort(403, __('baobab::admin.users.roles.forbidden'));
        }

        return response()->json(['data' => (new UserResource($this->findUserOrFail($user)))->resolve($request)]);
    }

    public function revokeRole(string $user, string $role, RevokeUserRole $revoke): Response
    {
        $actor = $this->authorizeManage();

        try {
            $revoke($actor, $this->findUserOrFail($user), $this->findRoleOrFail($role));
        } catch (HierarchyViolationException) {
            abort(403, __('baobab::admin.users.roles.forbidden'));
        } catch (AdminLockoutException) {
            abort(403, __('baobab::admin.users.roles.lockout'));
        }

        return response()->noContent();
    }

    /**
     * Le groupe `api/v1` n'a pas de liaison implicite de modèles (patron de
     * `ContentController`) : l'identifiant arrive brut.
     */
    private function findUserOrFail(string $id): User
    {
        /** @var User|null $user */
        $user = ctype_digit($id) ? User::query()->with('roles')->find((int) $id) : null;

        abort_if($user === null, 404);

        return $user;
    }

    private function findRoleOrFail(string $name): Role
    {
        /** @var Role|null $role */
        $role = Role::query()->where('name', $name)->where('guard_name', 'baobab')->first();

        abort_if($role === null, 404);

        return $role;
    }

    private function authorizeBrowse(): User
    {
        $actor = $this->requireActor();

        abort_unless(UserDirectory::allows($actor), 403);

        return $actor;
    }

    private function authorizeManage(): User
    {
        $actor = $this->requireActor();

        abort_unless($actor->can('baobab.users.manage'), 403);

        return $actor;
    }

    private function requireActor(): User
    {
        $actor = app(ApiActor::class)();

        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
