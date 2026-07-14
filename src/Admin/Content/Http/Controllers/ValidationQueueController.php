<?php

declare(strict_types=1);

namespace Baobab\Admin\Content\Http\Controllers;

use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * File de validation (spec 09 §5, M5 point 3) : agrège, pour chaque Content
 * Type que l'acteur peut modérer (`publish_any`), les soumissions natives
 * (`status = pending`, jamais publiées) et les brouillons de contenu déjà
 * publié soumis à validation (`Revision` `type = pending`, spec 09 §3
 * dernière puce) — mêmes deux mécanismes que le formulaire de contenu, vus
 * en transverse. Filtrable par type/auteur/date, comme demandé par la spec.
 */
final class ValidationQueueController
{
    private const ROWS_PER_TYPE = 20;

    public function index(Request $request): View
    {
        $actor = $this->actor();
        $filters = $request->validate([
            'type' => ['nullable', 'string'],
            'author_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $groups = [];
        $authorIds = [];

        foreach (ContentType::whereNotNull('module_id')->get() as $type) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $type->modelClass();

            if (! class_exists($modelClass) || ! self::canReview($actor, $type)) {
                continue;
            }

            $slug = Str::kebab(Str::plural($type->key));

            if (($filters['type'] ?? null) !== null && $filters['type'] !== $slug) {
                continue;
            }

            $submissions = $this->applyFilters($modelClass::query()->where('status', 'pending'), $filters)
                ->orderByDesc('updated_at')
                ->limit(self::ROWS_PER_TYPE)
                ->get();

            $pendingDrafts = $this->applyFilters(
                Revision::where('revisionable_type', $modelClass)->where('type', 'pending'),
                $filters,
            )
                ->orderByDesc('updated_at')
                ->limit(self::ROWS_PER_TYPE)
                ->with('author')
                ->get();

            if ($submissions->isEmpty() && $pendingDrafts->isEmpty()) {
                continue;
            }

            $authorIds = [...$authorIds, ...$submissions->pluck('author_id')->all(), ...$pendingDrafts->pluck('author_id')->all()];

            $groups[] = [
                'slug' => $slug,
                'label' => $type->blueprint['label']['plural'] ?? $type->key,
                'submissions' => $submissions,
                'pendingDrafts' => $pendingDrafts,
                'displayField' => $this->displayField($type),
            ];
        }

        return view('baobab::admin.review.index', [
            'groups' => $groups,
            'filters' => $filters,
            'authors' => User::whereIn('id', array_unique(array_filter($authorIds)))->orderBy('name')->get(),
        ]);
    }

    /**
     * @template TModel of Model
     *
     * @param  array{type?: string|null, author_id?: int|null, from?: string|null, to?: string|null}  $filters
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (($filters['author_id'] ?? null) !== null) {
            $query->where('author_id', $filters['author_id']);
        }

        if (($filters['from'] ?? null) !== null) {
            $query->whereDate('updated_at', '>=', $filters['from']);
        }

        if (($filters['to'] ?? null) !== null) {
            $query->whereDate('updated_at', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * Réutilisé par l'entrée de menu (spec 04) pour ne l'afficher que si
     * l'acteur peut effectivement modérer au moins un Content Type.
     */
    public static function isVisibleTo(User $actor): bool
    {
        foreach (ContentType::whereNotNull('module_id')->get() as $type) {
            if (self::canReview($actor, $type)) {
                return true;
            }
        }

        return false;
    }

    private static function canReview(User $actor, ContentType $type): bool
    {
        return $actor->can('content.'.Str::snake($type->key).'.publish_any');
    }

    /**
     * Champ affiché à côté de chaque ligne — même règle que TrashController.
     */
    private function displayField(ContentType $type): ?string
    {
        $titleField = $type->blueprint['title_field'] ?? null;

        if ($titleField !== null) {
            return $titleField;
        }

        return $type->blueprint['fields'][0]['key'] ?? null;
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
