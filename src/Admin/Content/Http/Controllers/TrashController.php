<?php

declare(strict_types=1);

namespace Baobab\Admin\Content\Http\Controllers;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Support\ContentTrash;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Corbeille globale (spec 09 §8) : agrège les lignes en corbeille de tous
 * les Content Types construits que l'acteur peut supprimer — un aperçu
 * transverse en plus de l'écran par type (`admin/content/{type}?trashed=1`).
 * Contenus uniquement pour l'instant : les médias ont déjà leur propre écran
 * (M4), les menus n'existent pas encore — les y agréger reste un suivi.
 */
final class TrashController
{
    private const ROWS_PER_TYPE = 20;

    public function index(): View
    {
        $actor = $this->actor();
        $groups = [];

        foreach (ContentType::whereNotNull('module_id')->get() as $type) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $type->modelClass();

            if (! class_exists($modelClass) || ! $actor->can('viewAny', $modelClass)) {
                continue;
            }

            $prefix = 'content.'.Str::snake($type->key);

            if (! $actor->can("{$prefix}.delete_any") && ! $actor->can("{$prefix}.delete")) {
                continue;
            }

            $rows = ContentTrash::onlyTrashed($modelClass)->orderByDesc('deleted_at')->limit(self::ROWS_PER_TYPE)->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $slug = Str::kebab(Str::plural($type->key));
            $displayField = $this->displayField($type);

            $groups[] = [
                'slug' => $slug,
                'label' => $type->blueprint['label']['plural'] ?? $type->key,
                'rows' => $rows,
                'displayField' => $displayField,
            ];
        }

        return view('baobab::admin.trash.index', [
            'groups' => $groups,
            'canPurge' => $actor->can('baobab.trash.purge'),
        ]);
    }

    /**
     * Champ affiché à côté de chaque ligne — `title_field` si le type en
     * déclare un (adressable), sinon le premier champ du blueprint, sinon
     * `null` (l'ID seul suffit alors).
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
