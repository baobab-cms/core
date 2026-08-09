<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Studio\Support\BlueprintPermissions;
use Illuminate\Support\Str;

/**
 * Étape 6 — Menus admin (spec-modules §5.2 étape 6, schéma
 * `module-blueprint.schema.json#/properties/menus`).
 *
 * Le bloc est recopié **tel quel** dans le `module.json` produit (Pass A3b) :
 * `route` et `permission` sont des chaînes complètes, pas des noms courts à
 * résoudre comme les hooks ou les widgets. Ce handler ne fait donc que
 * normaliser et écarter le vide.
 *
 * **Profondeur limitée à deux niveaux dans le constructeur graphique**
 * (entrée + sous-entrées), là où le schéma, `InstallModule::persistMenuItems()`
 * et la vue `admin-sidebar-items.blade.php` sont tous trois récursifs sans
 * limite. Raison : une barre latérale à trois niveaux n'est pas une interface
 * qu'on recommande, et le coût d'un constructeur récursif en Alpine sans
 * librairie est sans rapport avec ce qu'il rendrait. Conséquence assumée et
 * **jamais silencieuse** : un brouillon dont le blueprint contient déjà un
 * niveau 3 (édité à la main, ou importé) fait basculer l'écran en lecture
 * seule au lieu d'écraser ce qu'il ne sait pas afficher — cf. `isTooDeep()`.
 */
final class MenusStepHandler implements StudioStepHandler
{
    public function number(): int
    {
        return 6;
    }

    public function label(): string
    {
        return __('baobab::admin.studio.steps.menus');
    }

    public function view(): string
    {
        return 'baobab::admin.studio.steps.menus';
    }

    public function rules(ModuleBlueprintDraft $draft): array
    {
        return [
            'menus' => ['required', 'string'],
        ];
    }

    public function fill(array $validated, array $blueprint): array
    {
        $decoded = json_decode((string) ($validated['menus'] ?? ''), associative: true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw InvalidModuleBlueprintException::malformedJson(json_last_error_msg());
        }

        // Écran en lecture seule (blueprint trop profond pour le constructeur) :
        // il ne soumet rien d'exploitable, on ne touche pas au bloc stocké.
        if (self::isTooDeep($blueprint)) {
            return $blueprint;
        }

        $items = $this->normalizeItems($decoded, allowChildren: true);

        if ($items === []) {
            unset($blueprint['menus']);

            return $blueprint;
        }

        $blueprint['menus'] = ['admin' => $items];

        return $blueprint;
    }

    public function initialValues(array $blueprint): array
    {
        return [
            'menus' => $blueprint['menus']['admin'] ?? [],
        ];
    }

    /**
     * Deux catalogues, tous deux dérivés de ce que les étapes précédentes ont
     * réellement produit : les **noms de routes admin** que l'étape 4 va
     * générer (seules les entités dont la surface admin est active en ont
     * une) et les **chaînes de permission** de l'étape 3. Saisir une entrée de
     * menu revient donc à choisir parmi ce qui existera, pas à taper de
     * mémoire une chaîne que rien ne vérifiera avant l'installation.
     */
    public function viewData(array $blueprint): array
    {
        $slug = BlueprintPermissions::slug($blueprint);

        $routes = [];
        $permissions = [];

        foreach ($blueprint['entities'] ?? [] as $entity) {
            $key = (string) ($entity['key'] ?? '');

            if ($key === '') {
                continue;
            }

            if ((bool) ($entity['routes']['admin'] ?? true)) {
                $routes[] = 'admin.'.$slug.'.'.Str::snake(Str::plural($key)).'.index';
            }

            if ((bool) ($blueprint['permissions']['auto_crud'] ?? true)) {
                foreach (BlueprintPermissions::crudEntries($slug, $key) as $entry) {
                    $permissions[] = $entry['key'];
                }
            }
        }

        foreach ($blueprint['permissions']['custom'] ?? [] as $custom) {
            $entity = (string) ($custom['entity'] ?? '');
            $action = (string) ($custom['key'] ?? '');

            if ($entity !== '' && $action !== '') {
                $permissions[] = BlueprintPermissions::prefix($slug, $entity).'.'.$action;
            }
        }

        return [
            'menuRouteChoices' => $routes,
            'menuPermissionChoices' => $permissions,
            'menusTooDeep' => self::isTooDeep($blueprint),
        ];
    }

    public function valuesFromOldInput(array $old, array $values): array
    {
        $submitted = $old['menus'] ?? null;

        if (! is_string($submitted)) {
            return $values;
        }

        $decoded = json_decode($submitted, associative: true);

        if (! is_array($decoded)) {
            return $values;
        }

        return ['menus' => $decoded];
    }

    /**
     * Vrai dès qu'une entrée porte un petit-enfant — le constructeur ne rend
     * que deux niveaux et refuserait de perdre le troisième.
     *
     * @param  array<string, mixed>  $blueprint
     */
    public static function isTooDeep(array $blueprint): bool
    {
        foreach ($blueprint['menus']['admin'] ?? [] as $item) {
            foreach ($item['children'] ?? [] as $child) {
                if (($child['children'] ?? []) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Une entrée sans libellé est ignorée : c'est la seule clé requise par le
     * schéma, et une ligne ajoutée puis laissée vide ne doit pas faire échouer
     * l'étape (patron `parseAuthors()`/étape 3).
     *
     * @param  array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeItems(array $items, bool $allowChildren): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $entry = ['label' => $label];

            foreach (['icon', 'route', 'permission'] as $key) {
                $value = trim((string) ($item[$key] ?? ''));

                if ($value !== '') {
                    $entry[$key] = $value;
                }
            }

            if (($item['order'] ?? '') !== '' && is_numeric($item['order'])) {
                $entry['order'] = (int) $item['order'];
            }

            if ($allowChildren) {
                $children = $this->normalizeItems((array) ($item['children'] ?? []), allowChildren: false);

                if ($children !== []) {
                    $entry['children'] = $children;
                }
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }
}
