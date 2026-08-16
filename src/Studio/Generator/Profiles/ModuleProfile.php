<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator\Profiles;

use Baobab\ContentTypes\Generator\StubRenderer;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\StudioStubs;
use Baobab\Studio\Support\BlueprintPermissions;
use Illuminate\Support\Str;

/**
 * Conventions d'un module généré par le Wizard Studio (spec-modules §5.2) —
 * le profil par défaut du moteur, et celui dont `ContentTypeProfile` hérite.
 *
 * Tout ce qui est écrit ici était, jusqu'à la Pass A du M8 point 2, réparti
 * dans le corps de `ModuleGenerator` : le rassembler derrière un profil est ce
 * qui a permis d'y faire passer aussi les Content Types sans dupliquer une
 * seconde fois le moteur (suivi n° 157).
 */
class ModuleProfile implements GenerationProfile
{
    public function manifestType(): string
    {
        return 'module';
    }

    public function moduleDir(string $name): string
    {
        $dirSlug = Str::slug(str_replace('/', '-', $name));

        return rtrim((string) config('baobab.studio.modules_path'), '/')."/{$dirSlug}";
    }

    public function rootNamespace(string $name, ModuleBlueprint $blueprint): string
    {
        [$vendor, $slug] = explode('/', $name, 2);

        return Str::studly($vendor).'\\'.Str::studly($slug);
    }

    public function providerClass(string $slug, ModuleBlueprint $blueprint): string
    {
        return Str::studly($slug).'ServiceProvider';
    }

    public function permissionPrefix(string $slug, string $entityKey): string
    {
        return BlueprintPermissions::prefix($slug, $entityKey);
    }

    /**
     * Aucun préfixe : les entités du Studio ne sont pas des Content Types, et
     * `ct_` est réservé à ceux-ci (spec 02 §9 décision 1).
     */
    public function pivotPrefix(): string
    {
        return '';
    }

    public function ranksMigrations(): bool
    {
        return true;
    }

    public function leadingColumns(array $entity): string
    {
        $options = $entity['options'] ?? [];

        // L'option n'a jamais fait de la clé primaire un UUID depuis le
        // n° 166 : elle ajoute un identifiant **public** à côté d'elle. Une
        // clé primaire UUID rendrait l'entité invisible aux huit tables
        // polymorphiques du Core, dont les `*_id` sont numériques.
        return implode("\n", array_filter([
            '            $table->id();',
            (bool) ($options['uuid'] ?? false)
                ? "            \$table->uuid('uuid')->unique();"
                : null,
        ]));
    }

    public function trailingColumns(array $entity): string
    {
        $options = $entity['options'] ?? [];

        return implode("\n", array_filter([
            ($options['timestamps'] ?? true) ? '            $table->timestamps();' : null,
            ($options['soft_deletes'] ?? false) ? '            $table->softDeletes();' : null,
        ]));
    }

    public function modelTraits(array $entity): array
    {
        $options = $entity['options'] ?? [];
        $softDeletes = (bool) ($options['soft_deletes'] ?? false);
        $uuid = (bool) ($options['uuid'] ?? false);

        $traits = array_filter([
            $softDeletes ? 'SoftDeletes' : null,
            $uuid ? 'HasUuids' : null,
        ]);

        $imports = array_filter([
            $softDeletes ? 'use Illuminate\\Database\\Eloquent\\SoftDeletes;' : null,
            $uuid ? 'use Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;' : null,
        ]);

        return [
            'imports' => implode("\n", $imports),
            'use' => $traits === [] ? '' : '    use '.implode(', ', $traits).';',
        ];
    }

    public function leadingCasts(array $entity): string
    {
        return '';
    }

    public function leadingFillable(array $entity): array
    {
        return [];
    }

    /**
     * Les deux méthodes qui font de l'`uuid` un identifiant **public** et non
     * une simple colonne (n° 166) :
     *
     * - `uniqueIds()` dit à `HasUuids` quelle colonne remplir. Retourner
     *   `['uuid']` plutôt que la clé primaire est exactement ce qui laisse
     *   `getKeyType()` et `getIncrementing()` intacts — le trait ne les
     *   bascule que si la clé primaire y figure (`HasUniqueStringIds`).
     * - `getRouteKeyName()` fait sortir l'`uuid` sur les routes plutôt que
     *   l'entier. Sans elle, la colonne existerait sans rien protéger.
     */
    public function modelMethods(array $entity): string
    {
        $options = $entity['options'] ?? [];

        if (! (bool) ($options['uuid'] ?? false)) {
            return '';
        }

        return <<<'PHP'

                /**
                 * @return list<string>
                 */
                public function uniqueIds(): array
                {
                    return ['uuid'];
                }

                public function getRouteKeyName(): string
                {
                    return 'uuid';
                }
            PHP;
    }

    public function policy(array $entity, string $namespace, string $permissionPrefix, ModuleBlueprint $blueprint): string
    {
        $key = (string) $entity['key'];
        $var = Str::camel($key);
        $slug = (string) Str::of((string) $blueprint->name())->after('/');

        return (new StubRenderer)->render(StudioStubs::path('policy'), [
            'namespace' => $namespace,
            'key' => $key,
            'var' => $var,
            'permission_prefix' => $permissionPrefix,
            'custom_methods' => $this->customPolicyMethods($key, $var, $slug, $blueprint),
        ]);
    }

    public function providerFiles(string $namespace, string $slug, ModuleBlueprint $blueprint): array
    {
        $providerClass = $this->providerClass($slug, $blueprint);

        return ["src/Providers/{$providerClass}.php" => (new StubRenderer)->render(StudioStubs::path('provider'), [
            'namespace' => $namespace,
            'key' => Str::studly($slug),
            'view_namespace' => $slug,
        ])];
    }

    public function extraEntityFiles(array $entity, string $namespace): array
    {
        return [];
    }

    public function generatesSurfaces(): bool
    {
        return true;
    }

    public function permissions(ModuleBlueprint $blueprint, string $slug): array
    {
        $permissions = [];

        if ($blueprint->autoCrudEnabled()) {
            foreach ($blueprint->entities() as $entity) {
                foreach (BlueprintPermissions::crudEntries($slug, (string) $entity['key']) as $entry) {
                    $permissions[] = $entry;
                }
            }
        }

        foreach ($blueprint->customPermissions() as $custom) {
            $prefix = $this->permissionPrefix($slug, (string) $custom['entity']);
            $permissions[] = ['key' => "{$prefix}.{$custom['key']}", 'label' => (string) $custom['label']];
        }

        return $permissions;
    }

    /**
     * Recopie directe, aucune transformation : contrairement aux hooks (nom
     * court de classe → FQCN), une entrée de menu déclarée au blueprint a déjà
     * la forme exacte attendue par `module.schema.json` /
     * `ModuleManifest::adminMenuItems()`.
     */
    public function menus(ModuleBlueprint $blueprint): ?array
    {
        $admin = $blueprint->adminMenuItems();

        return $admin === [] ? null : ['admin' => $admin];
    }

    /**
     * Méthodes de policy supplémentaires, une par permission personnalisée de
     * l'entité. Le nom de méthode et la chaîne de permission viennent de
     * `BlueprintPermissions` — la même source que l'aperçu de l'étape 5 du
     * wizard, qui ne promet donc jamais autre chose que ce qui est écrit ici.
     */
    private function customPolicyMethods(string $key, string $var, string $slug, ModuleBlueprint $blueprint): string
    {
        return collect(BlueprintPermissions::policyMethods($slug, $key, $blueprint->customPermissions()))
            ->filter(fn (array $method): bool => $method['custom'])
            ->map(fn (array $method): string => "\n    public function {$method['method']}(User \$user, {$key} \${$var}): bool\n    {\n        return \$user->can('{$method['permission']}');\n    }\n")
            ->implode('');
    }
}
