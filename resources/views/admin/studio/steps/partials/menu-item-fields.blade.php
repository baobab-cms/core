{{--
    Champs d'une entrée de menu, partagés par le niveau racine et les
    sous-entrées de l'étape 6 — `$model` est le nom de la variable Alpine
    portant l'entrée (`item` ou `child`), les deux catalogues viennent du
    handler. Aucune logique ici : le partiel n'existe que pour ne pas écrire
    deux fois les mêmes six champs.
--}}
<div class="grid gap-2 sm:grid-cols-2">
    <input
        type="text"
        x-model="{{ $model }}.label"
        placeholder="{{ __('baobab::admin.studio.menus.label') }}"
        aria-label="{{ __('baobab::admin.studio.menus.label') }}"
        class="rounded-md border border-border px-2 py-1 text-sm text-foreground"
    >

    <input
        type="text"
        x-model="{{ $model }}.icon"
        placeholder="bi-box-seam"
        aria-label="{{ __('baobab::admin.studio.menus.icon') }}"
        class="rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
    >

    <select
        x-model="{{ $model }}.route"
        aria-label="{{ __('baobab::admin.studio.menus.route') }}"
        class="rounded-md border border-border bg-surface px-2 py-1 font-mono text-sm text-foreground"
    >
        <option value="">{{ __('baobab::admin.studio.menus.no_route') }}</option>
        @foreach ($routeChoices as $route)
            <option value="{{ $route }}">{{ $route }}</option>
        @endforeach
    </select>

    <select
        x-model="{{ $model }}.permission"
        aria-label="{{ __('baobab::admin.studio.menus.permission') }}"
        class="rounded-md border border-border bg-surface px-2 py-1 font-mono text-sm text-foreground"
    >
        <option value="">{{ __('baobab::admin.studio.menus.no_permission') }}</option>
        @foreach ($permissionChoices as $permission)
            <option value="{{ $permission }}">{{ $permission }}</option>
        @endforeach
    </select>

    <input
        type="number"
        x-model="{{ $model }}.order"
        placeholder="{{ __('baobab::admin.studio.menus.order') }}"
        aria-label="{{ __('baobab::admin.studio.menus.order') }}"
        class="rounded-md border border-border px-2 py-1 text-sm text-foreground"
    >
</div>
