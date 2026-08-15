<?php

declare(strict_types=1);

namespace Baobab\Admin\Content\Http\Controllers;

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\EvolveContentType;
use Baobab\ContentTypes\Exceptions\DestructiveChangeNotConfirmedException;
use Baobab\ContentTypes\Exceptions\DuplicateContentTypeException;
use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Exceptions\UnknownRelationTargetException;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Baobab\ContentTypes\Relations\RelationType;
use Baobab\Studio\Support\BlueprintFields;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Le Content Type builder (spec 02 §2.1 client « Admin », spec-modules §6) —
 * dernier écran « créer la chose » qui manquait au produit : jusqu'ici un
 * Content Type ne naissait qu'à la CLI (`content-type:make`).
 *
 * **Adaptateur mince, sans aucune Action à lui** : `BuildContentType` et
 * `EvolveContentType` existent depuis M3 et portent tout le pipeline
 * (validation, génération, migration, permissions, hooks, audit). Cet écran
 * ne fait qu'assembler un blueprint JSON et le leur passer — même rapport
 * que `ContentController` entretient avec `SaveContentEntry`.
 *
 * **Formulaire et non wizard** : décision n° 163. Un Content Type n'a qu'une
 * entité, aucune permission à choisir et une enveloppe de réglages sans
 * équivalent Studio — un parcours à neuf étapes s'y réduirait à une seule.
 * L'éditeur de champs et de relations, lui, est bien celui du Studio
 * (`<x-baobab::blueprint.*>`), qui est l'endroit où la fusion du §6 se joue.
 *
 * Accès gouverné par `baobab.system.content_types.manage` (routes/admin.php),
 * aucune policy own/any : patron `StudioController`, outillage de
 * développeur, pas de contenu appartenant à quelqu'un.
 */
final class ContentTypesController
{
    public function __construct(private readonly FieldRegistry $fields) {}

    /**
     * Le libellé pluriel vit dans le blueprint, pas en colonne : il est résolu
     * ici plutôt que dans la vue — « les contrôleurs calculent, les vues
     * affichent ». Repli sur la clé pour un type dont le blueprint serait
     * antérieur au libellé.
     */
    public function index(): View
    {
        $rows = ContentType::orderBy('key')->get()->map(static function (ContentType $type): array {
            /** @var array<string, mixed> $blueprint */
            $blueprint = $type->blueprint;
            /** @var array{plural?: string} $label */
            $label = $blueprint['label'] ?? [];

            return [
                'model' => $type,
                'key' => $type->key,
                'label' => $label['plural'] ?? $type->key,
                'table' => $type->table_name,
                'addressable' => (bool) $type->is_addressable,
                'version' => $type->version,
                'built' => $type->module_id !== null,
            ];
        });

        return view('baobab::admin.content-types.index', ['rows' => $rows]);
    }

    public function create(): View
    {
        return view('baobab::admin.content-types.form', [
            'contentType' => null,
            'values' => $this->blankValues(),
            'formAction' => route('admin.content-types.store'),
            ...$this->catalogues(null),
        ]);
    }

    public function store(Request $request, BuildContentType $build): RedirectResponse
    {
        $validated = $this->validateSubmission($request, null);

        try {
            $contentType = $build($this->assembleBlueprint($validated, null));
        } catch (InvalidBlueprintException|DuplicateContentTypeException|UnknownRelationTargetException $e) {
            return back()->withInput()->withErrors(['blueprint' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.content-types.edit', $contentType)
            ->with('success', __('baobab::admin.content_types.created', ['key' => $contentType->key]));
    }

    public function edit(ContentType $contentType): View
    {
        return view('baobab::admin.content-types.form', [
            'contentType' => $contentType,
            'values' => $this->valuesFrom($contentType),
            'formAction' => route('admin.content-types.update', $contentType),
            ...$this->catalogues($contentType),
        ]);
    }

    /**
     * L'évolution ne porte que sur les champs (`EvolveContentType`, spec 02
     * §2.2). L'enveloppe soumise est donc réécrite telle quelle dans le
     * blueprint, mais un changement de relation n'aurait aucun effet en base :
     * le formulaire l'affiche en lecture seule et le dit — asymétrie relevée
     * et assumée au n° 158, dont la résolution ne relève pas de cette passe.
     */
    public function update(Request $request, ContentType $contentType, EvolveContentType $evolve): RedirectResponse
    {
        $validated = $this->validateSubmission($request, $contentType);

        try {
            $evolve(
                $contentType,
                $this->assembleBlueprint($validated, $contentType),
                $request->boolean('confirm_destructive'),
            );
        } catch (InvalidBlueprintException|UnknownRelationTargetException $e) {
            return back()->withInput()->withErrors(['blueprint' => $e->getMessage()]);
        } catch (DestructiveChangeNotConfirmedException $e) {
            return back()->withInput()->withErrors(['destructive' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.content-types.edit', $contentType)
            ->with('success', __('baobab::admin.content_types.updated', ['key' => $contentType->key]));
    }

    /**
     * Validation de surface seulement : la forme du blueprint est jugée par
     * `ContentTypeBlueprint::fromJson()` à l'intérieur de l'Action, sous le
     * schéma JSON qui fait autorité. Doubler ce jeu de règles ici créerait
     * exactement le risque écarté au n° 161 — deux validations qui se
     * contredisent, et un blueprint valide devenu inconstructible.
     *
     * @return array<string, mixed>
     */
    private function validateSubmission(Request $request, ?ContentType $contentType): array
    {
        return $request->validate([
            // La clé est immuable : `EvolveContentType` la refuse déjà, mais
            // l'interdire ici évite d'aller chercher l'erreur au fond de la pile.
            'key' => [$contentType === null ? 'required' : 'nullable', 'string', 'regex:/^[A-Z][A-Za-z0-9]*$/'],
            'label_singular' => ['required', 'string', 'min:1', 'max:100'],
            'label_plural' => ['required', 'string', 'min:1', 'max:100'],
            'is_addressable' => ['nullable', 'boolean'],
            'url_prefix' => ['nullable', 'string', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'title_field' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'workflow' => ['nullable', 'boolean'],
            'unpublish_at' => ['nullable', 'boolean'],
            'api_enabled' => ['nullable', 'boolean'],
            'public_api_read' => ['nullable', 'boolean'],
            'revisions_limit' => ['nullable', 'integer', 'min:0'],
            'fields' => ['required', 'string'],
            'relations' => ['required', 'string'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assembleBlueprint(array $validated, ?ContentType $contentType): string
    {
        $blueprint = [
            // À l'évolution, la clé vient du type et jamais du formulaire, qui
            // l'affiche désactivé : un champ désactivé n'est pas soumis.
            'key' => $contentType !== null ? $contentType->key : (string) $validated['key'],
            'label' => [
                'singular' => (string) $validated['label_singular'],
                'plural' => (string) $validated['label_plural'],
            ],
            'is_addressable' => (bool) ($validated['is_addressable'] ?? false),
            'workflow' => (bool) ($validated['workflow'] ?? false),
            'unpublish_at' => (bool) ($validated['unpublish_at'] ?? true),
            'api_enabled' => (bool) ($validated['api_enabled'] ?? true),
            'public_api_read' => (bool) ($validated['public_api_read'] ?? true),
            'fields' => $this->decodeList($validated['fields'] ?? '[]'),
            'relations' => $this->decodeList($validated['relations'] ?? '[]'),
        ];

        // Clés facultatives : le schéma interdit les propriétés additionnelles
        // et refuse une chaîne vide là où il attend un motif — mieux vaut
        // l'absence qu'une valeur vide, qui serait un refus certain.
        foreach (['url_prefix', 'title_field'] as $optional) {
            $value = trim((string) ($validated[$optional] ?? ''));

            if ($value !== '') {
                $blueprint[$optional] = $value;
            }
        }

        if (($validated['revisions_limit'] ?? null) !== null) {
            $blueprint['revisions'] = ['limit' => (int) $validated['revisions_limit']];
        }

        return (string) json_encode($blueprint, JSON_THROW_ON_ERROR);
    }

    /**
     * Le formulaire transporte champs et relations en JSON dans un champ
     * caché, alimenté par Alpine — même mécanique que l'étape 2 du Studio,
     * dont il partage l'éditeur. Un JSON illisible devient une liste vide
     * plutôt qu'une erreur 500 : la validation du blueprint dira ensuite ce
     * qui manque, dans les termes de l'utilisateur.
     *
     * @return list<array<string, mixed>>
     */
    private function decodeList(mixed $raw): array
    {
        $decoded = json_decode(is_string($raw) ? $raw : '[]', true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    /**
     * @return array<string, mixed>
     */
    private function blankValues(): array
    {
        return [
            'key' => '',
            'label_singular' => '',
            'label_plural' => '',
            'is_addressable' => false,
            'url_prefix' => '',
            'title_field' => '',
            'workflow' => false,
            'unpublish_at' => true,
            'api_enabled' => true,
            'public_api_read' => true,
            'revisions_limit' => null,
            'fields' => [],
            'relations' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valuesFrom(ContentType $contentType): array
    {
        /** @var array<string, mixed> $blueprint */
        $blueprint = $contentType->blueprint;
        /** @var array{singular?: string, plural?: string} $label */
        $label = $blueprint['label'] ?? [];
        /** @var array{limit?: int|null} $revisions */
        $revisions = $blueprint['revisions'] ?? [];

        return [
            'key' => $contentType->key,
            'label_singular' => $label['singular'] ?? $contentType->key,
            'label_plural' => $label['plural'] ?? Str::plural($contentType->key),
            'is_addressable' => (bool) ($blueprint['is_addressable'] ?? false),
            'url_prefix' => $blueprint['url_prefix'] ?? '',
            'title_field' => $blueprint['title_field'] ?? '',
            'workflow' => (bool) ($blueprint['workflow'] ?? false),
            'unpublish_at' => (bool) ($blueprint['unpublish_at'] ?? true),
            'api_enabled' => (bool) ($blueprint['api_enabled'] ?? true),
            'public_api_read' => (bool) ($blueprint['public_api_read'] ?? true),
            'revisions_limit' => $revisions['limit'] ?? null,
            'fields' => $blueprint['fields'] ?? [],
            'relations' => $blueprint['relations'] ?? [],
        ];
    }

    /**
     * Listes de référence poussées à la vue, calculées côté serveur — « pas de
     * logique dans les vues admin ». Un type ne peut pas se cibler lui-même,
     * d'où son exclusion des cibles proposées.
     *
     * @return array<string, mixed>
     */
    private function catalogues(?ContentType $contentType): array
    {
        return [
            'fieldTypes' => array_keys($this->fields->all()),
            'typesNeedingChoices' => BlueprintFields::TYPES_NEEDING_CHOICES,
            'relationTypes' => array_map(
                static fn (RelationType $type): string => $type->value,
                RelationType::cases(),
            ),
            'onDeleteOptions' => ['restrict', 'cascade', 'set_null'],
            'coreTargets' => RelationTargetResolver::coreModelKeys(),
            'contentTypeTargets' => ContentType::whereNotNull('module_id')
                ->when($contentType !== null, fn ($query) => $query->whereKeyNot($contentType?->getKey()))
                ->orderBy('key')
                ->pluck('key')
                ->all(),
        ];
    }
}
