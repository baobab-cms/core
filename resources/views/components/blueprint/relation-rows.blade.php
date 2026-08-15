{{--
    Éditeur de relations d'un blueprint — le pendant de `field-rows`, mêmes
    principes et même raison d'être (suivi n° 163).

    La seule asymétrie entre les deux chemins est le groupe de cibles
    « entités sœurs » : un module en a N et peut se relier à lui-même, un
    Content Type n'a qu'une entité et n'en a donc aucune. `siblings` porte
    l'expression Alpine qui les liste ; laissée vide, le groupe disparaît —
    plutôt qu'un `optgroup` vide, qui se lit comme une option manquante.

    Seconde asymétrie, invisible à l'œil mais structurante : les deux chemins
    ne nomment pas leurs cibles pareil. Le Studio les préfixe
    (`content_type:Brand`, `core:User`) parce qu'il doit distinguer trois
    familles dont ses propres entités ; un Content Type les nomme nues
    (`Brand`, `User`), `RelationTargetResolver` cherchant d'abord un type puis
    un modèle du Core. Le préfixe est donc un paramètre, pas une constante —
    même patron que `withPivotPrefix()` côté moteur (n° 161).

    @param string      $collection    Expression Alpine désignant le tableau de relations.
    @param string      $addExpression Expression Alpine ajoutant une relation à cette collection.
    @param array       $relationTypes Valeurs de `RelationType`.
    @param array       $onDeleteOptions Comportements à la suppression (spec 02 §5).
    @param array       $coreTargets     Modèles du Core relationnables.
    @param array       $contentTypeTargets Content Types construits.
    @param string|null $siblings        Expression Alpine listant les cibles `entity:`.
    @param string      $contentTypePrefix Préfixe des cibles Content Type (`''` ou `content_type:`).
    @param string      $corePrefix        Préfixe des cibles Core (`''` ou `core:`).
--}}
@props([
    'collection',
    'addExpression',
    'relationTypes',
    'onDeleteOptions',
    'coreTargets' => [],
    'contentTypeTargets' => [],
    'siblings' => null,
    'contentTypePrefix' => '',
    'corePrefix' => '',
])

<div>
    <h3 class="mb-2 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.studio.entities.relations') }}</h3>

    <template x-if="{{ $collection }}.length === 0">
        <p class="mb-2 text-sm text-muted">{{ __('baobab::admin.studio.entities.no_relations') }}</p>
    </template>

    <div class="space-y-2">
        <template x-for="(relation, relationIndex) in {{ $collection }}" :key="relationIndex">
            <div class="flex flex-wrap items-center gap-2 rounded-md border border-border bg-surface-subtle p-2">
                <input
                    type="text"
                    x-model="relation.key"
                    placeholder="{{ __('baobab::admin.studio.entities.relation_key') }}"
                    aria-label="{{ __('baobab::admin.studio.entities.relation_key') }}"
                    class="w-40 rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
                >

                <select
                    x-model="relation.type"
                    aria-label="{{ __('baobab::admin.studio.entities.relation_type') }}"
                    class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-foreground"
                >
                    @foreach ($relationTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>

                <select
                    x-model="relation.target"
                    aria-label="{{ __('baobab::admin.studio.entities.relation_target') }}"
                    class="rounded-md border border-border bg-surface px-2 py-1 font-mono text-sm text-foreground"
                >
                    <option value="">—</option>
                    @if ($siblings !== null)
                        <optgroup label="{{ __('baobab::admin.studio.entities.target_group_entities') }}">
                            <template x-for="sibling in {{ $siblings }}" :key="sibling">
                                <option x-bind:value="sibling" x-text="sibling"></option>
                            </template>
                        </optgroup>
                    @endif
                    @if (! empty($contentTypeTargets))
                        <optgroup label="{{ __('baobab::admin.studio.entities.target_group_content_types') }}">
                            @foreach ($contentTypeTargets as $target)
                                <option value="{{ $contentTypePrefix.$target }}">{{ $contentTypePrefix.$target }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    <optgroup label="{{ __('baobab::admin.studio.entities.target_group_core') }}">
                        @foreach ($coreTargets as $target)
                            <option value="{{ $corePrefix.$target }}">{{ $corePrefix.$target }}</option>
                        @endforeach
                    </optgroup>
                </select>

                <select
                    x-model="relation.on_delete"
                    aria-label="{{ __('baobab::admin.studio.entities.relation_on_delete') }}"
                    class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-foreground"
                >
                    @foreach ($onDeleteOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>

                <button
                    type="button"
                    class="ml-auto rounded p-1 text-danger hover:bg-surface"
                    x-on:click="{{ $collection }}.splice(relationIndex, 1)"
                    aria-label="{{ __('baobab::admin.studio.entities.remove_relation') }}"
                    title="{{ __('baobab::admin.studio.entities.remove_relation') }}"
                >
                    <x-baobab::icon name="bi-x-lg" class="h-3 w-3" />
                </button>
            </div>
        </template>
    </div>

    <x-baobab::button type="button" variant="secondary" class="mt-2" x-on:click="{{ $addExpression }}">
        {{ __('baobab::admin.studio.entities.add_relation') }}
    </x-baobab::button>
</div>
