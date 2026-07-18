<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Resources;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation REST d'une entrée de Content Type (spec 08 §2.1) — structure
 * commune (id/slug/status/timestamps) puis chaque champ `exposed_in_api` du
 * blueprint via `FieldType::toApi()` (déjà prêt sur chaque type de champ,
 * pas de resérialisation à la main). `?fields=` restreint la sortie sans
 * jamais pouvoir réintroduire un champ non exposé. `?include=` ajoute les
 * relations déjà eager-loadées par `ContentQueryBuilder::applyIncludes()` —
 * une seule profondeur : une relation vers un autre Content Type est
 * sérialisée avec ses seuls champs `exposed_in_api` (pas de récursion
 * d'include), une relation vers un modèle Core sans blueprint (ex. `User`)
 * est réduite à `{"id": ...}` — simplification assumée en Pass A, aucun
 * flag d'exposition n'existe sur les modèles Core.
 */
final class ContentEntryResource extends JsonResource
{
    /**
     * @param  list<string>  $requestedFields
     * @param  list<string>  $includes
     */
    public function __construct(
        Model $resource,
        private readonly ContentType $contentType,
        private readonly array $requestedFields = [],
        private readonly array $includes = [],
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = ['id' => $this->resource->getKey()];

        if ($this->contentType->is_addressable) {
            $attributes['slug'] = $this->resource->getAttribute('slug');
        }

        $attributes['status'] = $this->resource->getAttribute('status');
        $attributes['published_at'] = $this->resource->getAttribute('published_at');
        $attributes['created_at'] = $this->resource->getAttribute('created_at');
        $attributes['updated_at'] = $this->resource->getAttribute('updated_at');

        $registry = app(FieldRegistry::class);

        foreach ($this->contentType->apiExposedFields() as $field) {
            $fieldType = $registry->resolve($field['type']);
            $attributes[$field['key']] = $fieldType->toApi(
                $this->resource->getAttribute($field['key']),
                $field['options'] ?? [],
            );
        }

        if ($this->requestedFields !== []) {
            $attributes = array_intersect_key(
                $attributes,
                array_flip(['id', ...$this->requestedFields]),
            );
        }

        foreach ($this->includes as $relationKey) {
            $attributes[$relationKey] = $this->serializeRelation($relationKey, $request);
        }

        return $attributes;
    }

    private function serializeRelation(string $relationKey, Request $request): mixed
    {
        if (! $this->resource->relationLoaded($relationKey)) {
            return null;
        }

        $related = $this->resource->getRelation($relationKey);

        if ($related instanceof EloquentCollection) {
            return $related->map(fn (Model $item): array => $this->serializeRelated($item, $request))->all();
        }

        return $related instanceof Model ? $this->serializeRelated($related, $request) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRelated(Model $related, Request $request): array
    {
        $relatedType = ContentType::forModelClass($related::class);

        if (! $relatedType instanceof ContentType || $relatedType->module_id === null) {
            return ['id' => $related->getKey()];
        }

        return (new self($related, $relatedType))->toArray($request);
    }
}
