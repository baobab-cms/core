<?php

declare(strict_types=1);

namespace Baobab\Admin\Account\Http\Controllers;

use Baobab\Auth\Actions\CreateApiToken;
use Baobab\Auth\Actions\RevokeApiToken;
use Baobab\Auth\Exceptions\InvalidTokenAbilityException;
use Baobab\Modules\Models\ModulePermission;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * Jetons API personnels, self-service (spec 08 §4.1, M7 point 2) — patron
 * exact de `SecurityController` (2FA) : pas de permission dédiée au-delà de
 * `auth:baobab` (déjà posée par `loadAdminRoutes()`), chacun ne gère que
 * ses propres tokens.
 */
final class ApiTokenController
{
    public function index(): View
    {
        $actor = $this->actor();

        return view('baobab::admin.account.api-tokens.index', [
            'tokens' => $actor->tokens()->orderByDesc('created_at')->get(),
            'availablePermissions' => $this->availablePermissions($actor),
            'plainTextToken' => session('plain_text_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        try {
            $token = app(CreateApiToken::class)(
                $actor,
                (string) $validated['name'],
                $validated['abilities'],
                isset($validated['expires_at']) ? Carbon::parse((string) $validated['expires_at']) : null,
            );
        } catch (InvalidTokenAbilityException $e) {
            throw ValidationException::withMessages(['abilities' => $e->getMessage()]);
        }

        session()->flash('plain_text_token', $token->plainTextToken);
        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.account.api_tokens.created')]);

        return redirect()->route('admin.account.api-tokens.index');
    }

    public function destroy(int $token): RedirectResponse
    {
        $tokenModel = $this->actor()->tokens()->findOrFail($token);

        app(RevokeApiToken::class)($tokenModel);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.account.api_tokens.revoked')]);

        return redirect()->route('admin.account.api-tokens.index');
    }

    /**
     * Uniquement les permissions que l'acteur possède *actuellement* — pas
     * la matrice complète (`PermissionMatrixBuilder`, qui liste tout le
     * monde) : un token ne peut porter que ce que son créateur possède déjà
     * (spec 08 §4.1).
     *
     * `search` porte le texte de correspondance déjà normalisé (minuscules,
     * nom + libellé) pour le filtre `x-show` côté vue — jamais de logique
     * dans la vue elle-même (patron CLAUDE.md).
     *
     * @return Collection<int, array{name: string, label: string, search: string}>
     */
    private function availablePermissions(User $actor): Collection
    {
        /** @var Collection<string, ModulePermission> $modulePermissions */
        $modulePermissions = ModulePermission::query()->get()->keyBy('key');

        return Permission::query()
            ->where('guard_name', 'baobab')
            ->orderBy('name')
            ->get()
            ->filter(fn (Permission $permission): bool => $actor->can($permission->name))
            ->map(function (Permission $permission) use ($modulePermissions): array {
                $modulePermission = $modulePermissions->get($permission->name);
                $label = $modulePermission !== null ? $modulePermission->label : $permission->name;

                return [
                    'name' => $permission->name,
                    'label' => $label,
                    'search' => $this->searchText($permission->name.' '.$label),
                ];
            })
            ->values();
    }

    /**
     * Extraite pour que le type déclaré `string` (natif, pas un docblock)
     * efface le littéral affiné `lowercase-string&non-falsy-string` que
     * PHPStan déduirait de `mb_strtolower()` en ligne — `Collection` n'étant
     * pas covariante sur son type de valeur, la forme exacte du tableau
     * retourné doit correspondre au `@return` ci-dessus au caractère près.
     */
    private function searchText(string $value): string
    {
        return mb_strtolower($value);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
