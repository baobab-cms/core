<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Studio\Support\BlueprintPermissions;

/**
 * Étape 3 — Permissions (spec-modules §5.2 étape 3, schéma
 * `module-blueprint.schema.json#/properties/permissions`).
 *
 * Deux saisies seulement : l'interrupteur `auto_crud` (les quatre permissions
 * `view`/`create`/`update`/`delete` par entité, actif par défaut) et une liste
 * de permissions personnalisées `{entité, action, libellé}`. Les deux voyagent
 * dans **une seule chaîne JSON** (patron étape 2) : la case à cocher pilotant
 * l'aperçu en direct vit de toute façon dans l'état Alpine, et un payload
 * unique fait que `valuesFromOldInput()` réhydrate l'écran entier d'un coup
 * après un refus — y compris une case décochée, que `old()` seul ne sait pas
 * distinguer d'une case absente.
 *
 * L'appartenance d'une permission personnalisée à une entité réellement
 * déclarée est vérifiée par la cross-validation centrale
 * (`ModuleBlueprint::validatePermissions()`), pas ici.
 */
final class PermissionsStepHandler implements StudioStepHandler
{
    public function number(): int
    {
        return 3;
    }

    public function label(): string
    {
        return __('baobab::admin.studio.steps.permissions');
    }

    public function view(): string
    {
        return 'baobab::admin.studio.steps.permissions';
    }

    public function rules(ModuleBlueprintDraft $draft): array
    {
        return [
            'permissions' => ['required', 'string'],
        ];
    }

    public function fill(array $validated, array $blueprint): array
    {
        $decoded = json_decode((string) ($validated['permissions'] ?? ''), associative: true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw InvalidModuleBlueprintException::malformedJson(json_last_error_msg());
        }

        $blueprint['permissions'] = [
            'auto_crud' => (bool) ($decoded['auto_crud'] ?? true),
            'custom' => $this->normalizeCustom($decoded['custom'] ?? []),
        ];

        return $blueprint;
    }

    public function initialValues(array $blueprint): array
    {
        $permissions = $blueprint['permissions'] ?? [];

        return [
            'auto_crud' => $permissions['auto_crud'] ?? true,
            'custom' => $permissions['custom'] ?? [],
        ];
    }

    /**
     * Catalogue d'entités et **préfixe réel** de chacune : l'aperçu montre la
     * chaîne exacte que le manifeste portera (`fleet.cars.view`), dérivée de
     * la même source que le générateur. Le suffixe personnalisé, lui, se
     * compose côté Alpine à la frappe.
     */
    public function viewData(array $blueprint): array
    {
        $slug = BlueprintPermissions::slug($blueprint);

        $entities = [];

        foreach ($blueprint['entities'] ?? [] as $entity) {
            $key = (string) ($entity['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $entities[] = [
                'key' => $key,
                'prefix' => BlueprintPermissions::prefix($slug, $key),
                'crud' => BlueprintPermissions::crudEntries($slug, $key),
            ];
        }

        return ['permissionEntities' => $entities];
    }

    /**
     * Même raison qu'à l'étape 2 : la saisie tient dans une chaîne JSON, sans
     * ce redécodage un blueprint refusé renverrait le formulaire à l'état
     * stocké et effacerait les permissions en cours de saisie.
     */
    public function valuesFromOldInput(array $old, array $values): array
    {
        $submitted = $old['permissions'] ?? null;

        if (! is_string($submitted)) {
            return $values;
        }

        $decoded = json_decode($submitted, associative: true);

        if (! is_array($decoded)) {
            return $values;
        }

        return [
            'auto_crud' => $decoded['auto_crud'] ?? true,
            'custom' => $decoded['custom'] ?? [],
        ];
    }

    /**
     * Une ligne entièrement vide est ignorée plutôt que de faire échouer
     * l'étape (patron `IdentityStepHandler::parseAuthors()`) ; une ligne
     * partiellement remplie est conservée telle quelle, pour que la
     * cross-validation désigne précisément ce qui manque.
     *
     * @return list<array{entity: string, key: string, label: string}>
     */
    private function normalizeCustom(mixed $custom): array
    {
        if (! is_array($custom)) {
            return [];
        }

        $normalized = [];

        foreach ($custom as $permission) {
            if (! is_array($permission)) {
                continue;
            }

            $entry = [
                'entity' => trim((string) ($permission['entity'] ?? '')),
                'key' => trim((string) ($permission['key'] ?? '')),
                'label' => trim((string) ($permission['label'] ?? '')),
            ];

            if ($entry['entity'] === '' && $entry['key'] === '' && $entry['label'] === '') {
                continue;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }
}
