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
use Baobab\Users\Actions\RequestProfileChange;
use Baobab\Users\Actions\RevokeUserRole;
use Baobab\Users\Actions\SendUserInvitation;
use Baobab\Users\Exceptions\InvitationNotPendingException;
use Baobab\Users\Models\ProfileChangeRequest;
use Baobab\Users\Models\User;
use Baobab\Users\UserDirectory;
use Illuminate\Auth\Access\AuthorizationException;
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

    /**
     * `PATCH /users/{id}` (spec 05 §5, décision 5 f-g et j) : nom et e-mail
     * d'un compte. Chemin ordinaire : `202`, la demande est enregistrée et
     * part à l'adresse actuelle du compte pour validation ; rien n'est encore
     * modifié. « Boîte perdue » : `lost_mailbox`, `admin_password` et
     * `justification`, la demande part à la nouvelle adresse.
     */
    public function update(Request $request, string $user, RequestProfileChange $requestChange): JsonResponse
    {
        return $this->requestProfileChange($request, $this->requireActor(), $this->findUserOrFail($user), $requestChange);
    }

    /**
     * `PATCH /me` : nom et e-mail du compte authentifié — mêmes règles, sans
     * l'exception « boîte perdue » (on ne perd pas la boîte de son propre
     * compte : un admin le fait pour lui).
     */
    public function updateMe(Request $request, RequestProfileChange $requestChange): JsonResponse
    {
        $actor = $this->requireActor();

        return $this->requestProfileChange($request, $actor, $actor, $requestChange);
    }

    private function requestProfileChange(Request $request, User $actor, User $target, RequestProfileChange $requestChange): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'max:255'],
            'lost_mailbox' => ['sometimes', 'boolean'],
            'admin_password' => ['sometimes', 'nullable', 'string'],
            'justification' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        try {
            $pending = $requestChange(
                $actor,
                $target,
                isset($validated['name']) ? (string) $validated['name'] : null,
                isset($validated['email']) ? (string) $validated['email'] : null,
                (bool) ($validated['lost_mailbox'] ?? false),
                isset($validated['admin_password']) ? (string) $validated['admin_password'] : null,
                isset($validated['justification']) ? (string) $validated['justification'] : null,
            );
        } catch (HierarchyViolationException|AuthorizationException) {
            abort(403);
        }

        return response()->json(['data' => $this->pendingChange($pending)], 202);
    }

    /**
     * Ce que la réponse dit d'une demande en attente : jamais le jeton, ni la
     * justification.
     *
     * @return array<string, mixed>
     */
    private function pendingChange(ProfileChangeRequest $pending): array
    {
        return [
            'status' => 'pending',
            'stage' => $pending->stage->value,
            'new_name' => $pending->new_name,
            'new_email' => $pending->new_email,
            'lost_mailbox' => $pending->forced,
            'expires_at' => $pending->expires_at->toIso8601String(),
        ];
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
