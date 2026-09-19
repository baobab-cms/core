<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\EraseOutcome;
use Baobab\Privacy\EraseReport;
use Baobab\Privacy\PersonalDataExport;
use Baobab\Privacy\Subject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * `core.content_authorship` (spec 16 §2.1) : les contenus signés — la
 * colonne `author_id` de chaque table `ct_*`. Un sujet sans compte n'en a
 * jamais. Un type dont la classe générée n'est pas chargeable (module
 * inactif) est ignoré : sa table n'est pas interrogeable de façon sûre.
 */
final class ContentAuthorshipProvider extends CoreProvider
{
    public function key(): string
    {
        return 'core.content_authorship';
    }

    public function describe(): DataDeclaration
    {
        return new DataDeclaration(
            title: __('baobab::privacy.content_authorship.title'),
            nature: __('baobab::privacy.content_authorship.nature'),
            purpose: __('baobab::privacy.content_authorship.purpose'),
            legalBasis: __('baobab::privacy.content_authorship.legal_basis'),
            retention: __('baobab::privacy.content_authorship.retention'),
        );
    }

    /**
     * Le contenu éditorial appartient au site (spec 16 §4.3) : rien n'est
     * supprimé, sa suppression éventuelle est une décision éditoriale séparée.
     * L'auteur est déjà le fantôme, puisque le compte est anonymisé en place.
     */
    public function erase(Subject $subject): EraseReport
    {
        $userId = $this->userIdOf($subject);
        $count = 0;

        foreach (ContentType::query()->get() as $contentType) {
            $class = $contentType->modelClass();

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $count += $class::query()->withoutGlobalScope(SoftDeletingScope::class)
                ->where('author_id', $userId)->count();
        }

        return new EraseReport(EraseOutcome::Retained, $count, __('baobab::privacy.erasure.content_authorship_note'));
    }

    /**
     * Les entrées signées par le sujet, par content type. Le contenu appartient
     * au site (spec 16 §2.1, `retention`) : l'export le rend au titre de
     * l'accès, sans que l'effacement ait à le supprimer.
     */
    public function export(Subject $subject): PersonalDataExport
    {
        $userId = $this->userIdOf($subject);
        $byType = [];

        foreach (ContentType::query()->get() as $contentType) {
            $class = $contentType->modelClass();

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $rows = $class::query()->withoutGlobalScope(SoftDeletingScope::class)
                ->where('author_id', $userId)->get();

            if ($rows->isNotEmpty()) {
                $byType[$contentType->key] = $rows->map(static fn (Model $row): array => $row->toArray())->all();
            }
        }

        return new PersonalDataExport(['content' => $byType]);
    }

    public function locate(Subject $subject): bool
    {
        $userId = $this->userIdOf($subject);

        if ($userId === null) {
            return false;
        }

        foreach (ContentType::query()->get() as $contentType) {
            $class = $contentType->modelClass();

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            // Corbeille comprise : un contenu supprimé reste signé tant qu'il n'est pas purgé.
            $query = $class::query()->withoutGlobalScope(SoftDeletingScope::class);

            if ($query->where('author_id', $userId)->exists()) {
                return true;
            }
        }

        return false;
    }
}
