@extends('baobab::layouts.admin')

@use('Baobab\Admin\Modules\Support\ModuleLifecyclePresenter')

@section('title', __('baobab::admin.modules.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.modules.title')">
        <p class="text-sm text-muted">{{ __('baobab::admin.modules.intro') }}</p>

        <x-baobab::card class="mt-6" :header="__('baobab::admin.modules.upload_title')">
            <x-baobab::form
                method="POST"
                action="{{ route('admin.modules.upload') }}"
                enctype="multipart/form-data"
                class="space-y-3"
            >
                <p class="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning-700">
                    {{ __('baobab::admin.modules.upload_warning') }}
                </p>

                <div class="space-y-1">
                    <label for="module-archive" class="block text-sm font-medium text-foreground">
                        {{ __('baobab::admin.modules.upload_label') }}
                    </label>

                    <input
                        id="module-archive"
                        type="file"
                        name="archive"
                        accept=".zip,application/zip"
                        required
                        class="block w-full text-sm text-foreground file:mr-3 file:rounded-md file:border file:border-border file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium"
                    >

                    <p class="text-xs text-muted">{{ __('baobab::admin.modules.upload_hint') }}</p>
                </div>

                <x-baobab::button type="submit" variant="primary">
                    {{ __('baobab::admin.modules.upload_action') }}
                </x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>

        @if (empty($modules))
            <x-baobab::empty-state class="mt-6" :message="__('baobab::admin.modules.empty')" />
        @else
            <div class="mt-6 space-y-4">
                @foreach ($modules as $module)
                    <x-baobab::card>
                        <x-slot:header>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-display text-base font-semibold text-foreground">{{ $module->title }}</span>

                                <x-baobab::badge :variant="ModuleLifecyclePresenter::badgeVariant($module->status)">
                                    {{ __('baobab::admin.modules.status_'.$module->status) }}
                                </x-baobab::badge>

                                {{-- `module` est le cas courant : un badge sur chaque ligne ne dirait rien. --}}
                                @if ($module->type !== 'module')
                                    <x-baobab::badge variant="info">{{ __('baobab::admin.modules.type_'.$module->type) }}</x-baobab::badge>
                                @endif
                            </div>
                        </x-slot:header>

                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="space-y-1 text-xs text-muted">
                                <p class="font-mono text-foreground">{{ $module->name }}</p>

                                <p>
                                    {{ __('baobab::admin.modules.version_label', ['version' => $module->version]) }}
                                    &middot;
                                    {{ __('baobab::admin.modules.source_'.$module->source) }}
                                </p>

                                @if ($module->requires !== [])
                                    <p>{{ __('baobab::admin.modules.requires_label', ['modules' => implode(', ', array_keys($module->requires))]) }}</p>
                                @endif

                                @unless ($module->onDisk)
                                    <p class="text-danger">{{ __('baobab::admin.modules.missing_files') }}</p>
                                @endunless

                                @if ($module->isTheme())
                                    <p>{{ __('baobab::admin.modules.theme_hint') }}</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                @if ($module->isTheme() && $canManageThemes)
                                    <a href="{{ route('admin.themes.index') }}" class="text-sm text-muted hover:underline">
                                        {{ __('baobab::admin.modules.theme_link') }}
                                    </a>
                                @endif

                                @if (ModuleLifecyclePresenter::canInstall($module))
                                    <x-baobab::form method="POST" action="{{ route('admin.modules.install', $module->routeParams()) }}">
                                        <x-baobab::button type="submit" variant="primary">
                                            {{ __('baobab::admin.modules.install_action') }}
                                        </x-baobab::button>
                                    </x-baobab::form>
                                @endif

                                @if (ModuleLifecyclePresenter::canActivate($module))
                                    <x-baobab::form method="POST" action="{{ route('admin.modules.activate', $module->routeParams()) }}">
                                        <x-baobab::button type="submit" variant="primary">
                                            {{ __('baobab::admin.modules.activate_action') }}
                                        </x-baobab::button>
                                    </x-baobab::form>
                                @endif

                                @if (ModuleLifecyclePresenter::canDeactivate($module))
                                    <x-baobab::form method="POST" action="{{ route('admin.modules.deactivate', $module->routeParams()) }}">
                                        <x-baobab::button type="submit" variant="secondary">
                                            {{ __('baobab::admin.modules.deactivate_action') }}
                                        </x-baobab::button>
                                    </x-baobab::form>
                                @endif

                                @if (ModuleLifecyclePresenter::canUpdate($module))
                                    {{--
                                        Un seul bouton pour les deux étapes « sur place » de la
                                        spec §3 : il joue les migrations en attente, puis relit le
                                        manifeste. L'écran n'offre pas la resynchronisation seule
                                        — c'est un sous-ensemble strict, et personne ne veut
                                        rafraîchir les permissions en laissant le schéma périmé.
                                        `module:sync` la garde côté terminal (suivi n° 156).
                                    --}}
                                    <x-baobab::button
                                        type="button"
                                        variant="secondary"
                                        x-data
                                        x-on:click="$dispatch('open-modal', 'update-{{ $module->name }}')"
                                    >
                                        {{ __('baobab::admin.modules.update_action') }}
                                    </x-baobab::button>
                                @endif

                                @if (ModuleLifecyclePresenter::canUninstall($module))
                                    <x-baobab::button
                                        type="button"
                                        variant="danger"
                                        x-data
                                        x-on:click="$dispatch('open-modal', 'uninstall-{{ $module->name }}')"
                                    >
                                        {{ __('baobab::admin.modules.uninstall_action') }}
                                    </x-baobab::button>
                                @endif
                            </div>
                        </div>
                    </x-baobab::card>

                    @if (ModuleLifecyclePresenter::canUninstall($module))
                        {{--
                            La suppression des données n'est jamais le défaut (spec-modules §3 :
                            « avec confirmation explicite »). La case à cocher est cette
                            confirmation : sans elle, la désinstallation laisse les tables du
                            module intactes et le module reste réinstallable sans perte.
                        --}}
                        <x-baobab::modal name="uninstall-{{ $module->name }}">
                            <h2 class="font-display text-base font-semibold text-foreground">
                                {{ __('baobab::admin.modules.uninstall_confirm_title', ['module' => $module->title]) }}
                            </h2>

                            <p class="mt-2 text-sm text-muted">
                                {{ __('baobab::admin.modules.uninstall_confirm_description') }}
                            </p>

                            <x-baobab::form method="DELETE" action="{{ route('admin.modules.uninstall', $module->routeParams()) }}" class="mt-4">
                                <label class="flex items-start gap-2 text-sm text-foreground">
                                    <input type="checkbox" name="purge" value="1" class="mt-1">
                                    <span>
                                        {{ __('baobab::admin.modules.uninstall_purge_label') }}
                                        <span class="mt-1 block text-xs text-danger">
                                            {{ __('baobab::admin.modules.uninstall_purge_warning') }}
                                        </span>
                                    </span>
                                </label>

                                {{--
                                    Deux cases distinctes parce que ce sont deux pertes distinctes :
                                    les données vivent en base, le code vit sur disque, et on peut
                                    vouloir l'une sans l'autre (garder les données d'un module qu'on
                                    réinstallera depuis une archive, par exemple).
                                --}}
                                <label class="mt-3 flex items-start gap-2 text-sm text-foreground">
                                    <input type="checkbox" name="delete_files" value="1" class="mt-1">
                                    <span>
                                        {{ __('baobab::admin.modules.uninstall_delete_files_label') }}
                                        <span class="mt-1 block text-xs text-danger">
                                            {{ __('baobab::admin.modules.uninstall_delete_files_warning') }}
                                        </span>
                                    </span>
                                </label>

                                <div class="mt-4 flex justify-end gap-2">
                                    <x-baobab::button type="button" variant="ghost" x-on:click="show = false">
                                        {{ __('baobab::admin.components.close') }}
                                    </x-baobab::button>

                                    <x-baobab::button type="submit" variant="danger">
                                        {{ __('baobab::admin.modules.uninstall_action') }}
                                    </x-baobab::button>
                                </div>
                            </x-baobab::form>
                        </x-baobab::modal>
                    @endif

                    @if (ModuleLifecyclePresenter::canUpdate($module))
                        {{--
                            La resynchronisation n'est destructrice que sur un point : une
                            permission que le manifeste ne déclare plus est révoquée partout
                            où elle avait été accordée. La case est cette confirmation ;
                            sans elle, l'Action refuse et nomme les permissions concernées.
                        --}}
                        <x-baobab::modal name="update-{{ $module->name }}">
                            <h2 class="font-display text-base font-semibold text-foreground">
                                {{ __('baobab::admin.modules.update_confirm_title', ['module' => $module->title]) }}
                            </h2>

                            <p class="mt-2 text-sm text-muted">
                                {{ __('baobab::admin.modules.update_confirm_description') }}
                            </p>

                            <x-baobab::form method="POST" action="{{ route('admin.modules.update', $module->routeParams()) }}" class="mt-4">
                                <label class="flex items-start gap-2 text-sm text-foreground">
                                    <input type="checkbox" name="force" value="1" class="mt-1">
                                    <span>
                                        {{ __('baobab::admin.modules.update_force_label') }}
                                        <span class="mt-1 block text-xs text-danger">
                                            {{ __('baobab::admin.modules.update_force_warning') }}
                                        </span>
                                    </span>
                                </label>

                                <div class="mt-4 flex justify-end gap-2">
                                    <x-baobab::button type="button" variant="ghost" x-on:click="show = false">
                                        {{ __('baobab::admin.components.close') }}
                                    </x-baobab::button>

                                    <x-baobab::button type="submit" variant="primary">
                                        {{ __('baobab::admin.modules.update_action') }}
                                    </x-baobab::button>
                                </div>
                            </x-baobab::form>
                        </x-baobab::modal>
                    @endif
                @endforeach
            </div>
        @endif
    </x-baobab::page>
@endsection
