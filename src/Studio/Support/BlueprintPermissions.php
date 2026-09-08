<?php

declare(strict_types=1);

namespace Baobab\Studio\Support;

use Illuminate\Support\Str;

/**
 * Nommage des permissions d'un module Studio et mapping permission → méthode
 * de policy (spec-modules §5.2 étapes 3 et 5).
 *
 * Source de vérité unique et partagée : `ModuleGenerator` s'en sert pour
 * écrire le bloc `permissions` du `module.json` et les méthodes du
 * `{Key}Policy`, les étapes 3 et 5 du wizard pour **montrer à l'avance** les
 * mêmes chaînes. Sans cette classe, l'écran d'aperçu et le fichier généré
 * dériveraient à la première évolution du nommage — or l'étape 5 est
 * précisément un écran qui ne promet rien d'autre que ce que le générateur
 * écrit (les policies ne se saisissent pas, elles se dérivent).
 *
 * Les méthodes listées par `policyMethods()` sont exactement celles que
 * `resources/stubs/studio/policy.stub` produit — ni plus (pas de
 * `restore`/`forceDelete`, même quand l'entité active `soft_deletes`), ni
 * moins.
 */
final class BlueprintPermissions
{
    /** Action de permission adossée à chaque méthode CRUD du stub de policy. */
    private const CRUD_METHODS = [
        'viewAny' => 'view',
        'view' => 'view',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
    ];

    /** Libellés français des permissions CRUD écrites dans le `module.json` généré. */
    private const CRUD_LABELS = [
        'view' => 'Voir',
        'create' => 'Créer',
        'update' => 'Modifier',
        'delete' => 'Supprimer',
    ];

    /**
     * Slug du module (part droite de `vendor/slug`), racine de toute chaîne de
     * permission. Chaîne vide tant que l'étape 1 n'a rien enregistré.
     *
     * @param  array<string, mixed>  $blueprint
     */
    public static function slug(array $blueprint): string
    {
        $name = (string) ($blueprint['identity']['name'] ?? '');

        return $name === '' ? '' : (string) Str::of($name)->after('/');
    }

    /**
     * `{slug}.{pluriel_snake_de_l_entité}` — `acme/fleet` + `Car` → `fleet.cars`.
     */
    public static function prefix(string $slug, string $entityKey): string
    {
        return $slug.'.'.Str::snake(Str::plural($entityKey));
    }

    /**
     * Les quatre permissions CRUD d'une entité, telles qu'elles seront écrites
     * dans le manifeste du module généré.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function crudEntries(string $slug, string $entityKey): array
    {
        $prefix = self::prefix($slug, $entityKey);

        $entries = [];

        foreach (self::CRUD_LABELS as $action => $label) {
            $entries[] = ['key' => "{$prefix}.{$action}", 'label' => "{$label} : {$entityKey}"];
        }

        return $entries;
    }

    /**
     * Mapping méthode de policy → chaîne de permission pour une entité :
     * les cinq méthodes CRUD du stub, puis une méthode par permission
     * personnalisée déclarée sur cette entité (`publish` → `publish()`).
     *
     * @param  list<array<string, mixed>>  $customPermissions  bloc `permissions.custom` complet, toutes entités confondues
     * @return list<array{method: string, permission: string, custom: bool}>
     */
    public static function policyMethods(string $slug, string $entityKey, array $customPermissions): array
    {
        $prefix = self::prefix($slug, $entityKey);

        $methods = [];

        foreach (self::CRUD_METHODS as $method => $action) {
            $methods[] = ['method' => $method, 'permission' => "{$prefix}.{$action}", 'custom' => false];
        }

        foreach ($customPermissions as $permission) {
            if (($permission['entity'] ?? null) !== $entityKey) {
                continue;
            }

            $key = (string) ($permission['key'] ?? '');

            $methods[] = [
                'method' => Str::camel($key),
                'permission' => "{$prefix}.{$key}",
                'custom' => true,
            ];
        }

        return $methods;
    }
}
