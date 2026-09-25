<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Controllers;

use Baobab\Access\Models\DirectPermissionGrant;
use Baobab\Admin\Access\PermissionMatrixBuilder;
use Baobab\Admin\Users\UserRoleOptions;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Auth\Actions\ListActiveSessions;
use Baobab\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class UserController
{
    public function __construct(private readonly PermissionMatrixBuilder $permissionMatrixBuilder) {}

    public function index(): View
    {
        /** @var User $actor */
        $actor = Auth::guard('baobab')->user();

        $users = User::query()->with('roles')->orderBy('name')->get();

        return view('baobab::admin.users.index', [
            'users' => $users,
            'columns' => $this->columns(),
            'canInvite' => $actor->can('baobab.users.manage'),
        ]);
    }

    public function show(User $user, UserRoleOptions $roleOptions): View
    {
        /** @var User $actor */
        $actor = Auth::guard('baobab')->user();

        $user->load(['roles' => fn ($query) => $query->orderBy('name')]);

        // Spec 05 §4.1 : jamais ses propres rôles, jamais un utilisateur de
        // niveau supérieur ou égal — les Actions le revérifient.
        $canManageRoles = $actor->can('baobab.users.manage')
            && ! $user->is($actor)
            && $user->level() < $actor->level();

        return view('baobab::admin.users.show', [
            'user' => $user,
            'canImpersonate' => $actor->can('baobab.users.impersonate') && ! $user->is($actor) && $user->level() < $actor->level(),
            'canManageRoles' => $canManageRoles,
            'canResendInvitation' => $canManageRoles && $user->hasPendingInvitation(),
            // Même garde que les rôles (spec 05 §4.1) ; son propre profil
            // se modifie depuis Mon compte.
            'canEditProfile' => $canManageRoles,
            'grantableRoles' => $canManageRoles
                ? $roleOptions->assignableBy($actor)->reject(fn ($role): bool => $user->roles->contains('id', $role->id))->values()
                : collect(),
            'revocableRoleIds' => $canManageRoles
                ? $user->roles->filter(fn ($role): bool => (int) $role->getAttribute('level') < $actor->level())->pluck('id')->all()
                : [],
            'canManageAccess' => $actor->can('baobab.access.manage'),
            'activity' => $this->activity($user),
            'activityColumns' => $this->activityColumns(),
            'directGrants' => DirectPermissionGrant::query()
                ->where('user_id', $user->id)
                ->with(['permission', 'grantedBy'])
                ->get(),
            'permissionGroups' => $this->permissionMatrixBuilder->build()['groups'],
            'activeSessions' => $this->activeSessions($user),
        ]);
    }

    /**
     * @return Collection<int, array{id: string, ip_address: ?string, user_agent: ?string, last_activity_label: string}>
     */
    private function activeSessions(User $user): Collection
    {
        return app(ListActiveSessions::class)($user)->map(fn ($session): array => [
            'id' => (string) $session->id,
            'ip_address' => $session->ip_address !== null ? (string) $session->ip_address : null,
            'user_agent' => $session->user_agent !== null ? (string) $session->user_agent : null,
            'last_activity_label' => Carbon::createFromTimestamp((int) $session->last_activity)->format('Y-m-d H:i'),
        ]);
    }

    /**
     * Spec 05 §5 : fiche utilisateur, onglet activité — le journal d'audit
     * n'a pas de colonne dédiée « sujet » distincte du polymorphe
     * `auditable` ; ce qui concerne cet utilisateur, c'est donc ce qu'il a
     * fait lui-même (`actor_id`) autant que ce qui lui a été fait (rôle
     * assigné, impersonation démarrée…, `auditable_type` = `User::class`).
     *
     * @return LengthAwarePaginator<int, AuditEntry>
     */
    private function activity(User $user): LengthAwarePaginator
    {
        return AuditEntry::query()
            ->with(['actor', 'impersonator'])
            ->where(function ($query) use ($user): void {
                $query->where('actor_id', $user->id)
                    ->orWhere(function ($query) use ($user): void {
                        $query->where('auditable_type', User::class)->where('auditable_id', $user->id);
                    });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activityColumns(): array
    {
        return [
            ['key' => 'created_at', 'label' => __('baobab::admin.audit.column_date'), 'render' => fn (AuditEntry $entry) => $entry->created_at->format('Y-m-d H:i')],
            ['key' => 'actor', 'label' => __('baobab::admin.audit.column_actor'), 'render' => fn (AuditEntry $entry) => $entry->actor !== null ? $entry->actor->name : __('baobab::admin.audit.system_actor')],
            ['key' => 'impersonator', 'label' => __('baobab::admin.audit.column_impersonator'), 'render' => fn (AuditEntry $entry) => $entry->impersonator !== null ? $entry->impersonator->name : '—'],
            ['key' => 'action', 'label' => __('baobab::admin.audit.column_action')],
            ['key' => 'data', 'label' => __('baobab::admin.audit.column_data'), 'render' => fn (AuditEntry $entry) => json_encode($entry->data)],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            [
                'key' => 'name',
                'label' => __('baobab::admin.users.column_name'),
                'raw' => true,
                'render' => fn (User $user) => '<a href="'.route('admin.users.show', ['user' => $user]).'" class="hover:underline">'.e($user->name).'</a>',
            ],
            [
                'key' => 'email',
                'label' => __('baobab::admin.users.column_email'),
                'render' => fn (User $user) => $user->hasPendingInvitation()
                    ? $user->email.' — '.__('baobab::admin.users.invite.pending_badge')
                    : $user->email,
            ],
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
        ];
    }
}
