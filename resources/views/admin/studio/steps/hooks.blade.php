@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page
        :title="$draft?->title ?? __('baobab::admin.studio.new_module')"
        :breadcrumbs="[[__('baobab::admin.sidebar.studio'), route('admin.studio.index')]]"
    >
        <x-baobab::wizard :steps="$steps" />

        <div
            x-data="studioHooks({
                listens: @js($values['listens']),
            })"
        >
            <x-baobab::form method="POST" action="{{ $formAction }}">
                <input type="hidden" name="listens" x-bind:value="payload">

                {{-- Hooks émis : documentation pure, aucun fichier généré --}}
                <x-baobab::card :header="__('baobab::admin.studio.hooks.emits_title')">
                    <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.studio.hooks.emits_intro') }}</p>

                    <x-baobab::field.textarea
                        name="emits"
                        :label="__('baobab::admin.studio.hooks.emits_label')"
                        :value="$values['emits']"
                        rows="4"
                        placeholder="{{ $hookPrefix }}.car.serviced"
                        class="font-mono"
                    />

                    <p class="-mt-2 text-xs text-muted">
                        {{ __('baobab::admin.studio.hooks.emits_hint', ['prefix' => $hookPrefix]) }}
                    </p>
                </x-baobab::card>

                {{-- Hooks écoutés : génèrent un squelette de classe --}}
                <x-baobab::card :header="__('baobab::admin.studio.hooks.listens_title')" class="mt-4">
                    <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.studio.hooks.listens_intro') }}</p>

                    <template x-if="listens.length === 0">
                        <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.studio.hooks.no_listens') }}</p>
                    </template>

                    <div class="space-y-2">
                        <template x-for="(entry, index) in listens" :key="index">
                            <div class="rounded-md border border-border bg-surface-subtle p-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input
                                        type="text"
                                        x-model="entry.hook"
                                        list="baobab-hook-catalogue"
                                        placeholder="{{ __('baobab::admin.studio.hooks.hook_name') }}"
                                        aria-label="{{ __('baobab::admin.studio.hooks.hook_name') }}"
                                        class="w-72 rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
                                    >

                                    <x-baobab::icon name="bi-arrow-right-short" class="h-4 w-4 shrink-0 text-muted" />

                                    <input
                                        type="text"
                                        x-model="entry.class_name"
                                        placeholder="LogCarServiced"
                                        aria-label="{{ __('baobab::admin.studio.hooks.class_name') }}"
                                        class="w-56 rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
                                    >

                                    <button
                                        type="button"
                                        class="ml-auto rounded p-1 text-danger hover:bg-surface"
                                        x-on:click="listens.splice(index, 1)"
                                        aria-label="{{ __('baobab::admin.studio.hooks.remove_listen') }}"
                                        title="{{ __('baobab::admin.studio.hooks.remove_listen') }}"
                                    >
                                        <x-baobab::icon name="bi-x-lg" class="h-3 w-3" />
                                    </button>
                                </div>

                                {{-- Le fichier réellement écrit, composé à la frappe --}}
                                <p class="mt-2 font-mono text-xs text-muted" x-text="listenerFile(entry)"></p>
                            </div>
                        </template>
                    </div>

                    <datalist id="baobab-hook-catalogue">
                        @foreach ($hookCatalogue as $hook)
                            <option value="{{ $hook }}"></option>
                        @endforeach
                    </datalist>

                    <x-baobab::button type="button" variant="secondary" class="mt-2" x-on:click="addListen()">
                        {{ __('baobab::admin.studio.hooks.add_listen') }}
                    </x-baobab::button>

                    <p class="mt-3 text-xs text-muted">{{ __('baobab::admin.studio.hooks.skeleton_hint') }}</p>
                </x-baobab::card>

                <div class="mt-10 flex items-center justify-between">
                    <x-baobab::button :href="route('admin.studio.step.show', [$draft, 7])" variant="ghost">
                        {{ __('baobab::admin.studio.previous') }}
                    </x-baobab::button>

                    <x-baobab::button type="submit" variant="primary">
                        {{ $isLastImplementedStep ? __('baobab::admin.studio.save') : __('baobab::admin.studio.save_and_continue') }}
                    </x-baobab::button>
                </div>
            </x-baobab::form>
        </div>
    </x-baobab::page>
@endsection

@once
    <script>
        function studioHooks(config) {
            return {
                listens: config.listens.map((entry) => ({ ...entry })),

                get payload() {
                    return JSON.stringify(this.listens.map((entry) => ({
                        hook: entry.hook,
                        class_name: entry.class_name,
                    })));
                },

                listenerFile(entry) {
                    return entry.class_name ? 'src/Hooks/' + entry.class_name + '.php' : '—';
                },

                addListen() {
                    this.listens.push({ hook: '', class_name: '' });
                },
            };
        }
    </script>
@endonce
