<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Exceptions\UnknownRelationTargetException;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Generator\StubRenderer;
use Baobab\ContentTypes\Relations\BelongsToRelations;
use Baobab\ContentTypes\Relations\RelationOptions;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Baobab\Studio\Generator\Support\EntityFields;
use Illuminate\Support\Str;

/**
 * Génère le CRUD admin d'une entité de blueprint Wizard Studio (spec-modules
 * §5.2 étape 4, M8 point 1 Pass A2) : contrôleur, Form Request, vues
 * (`index`/`form`) — aucun précédent direct dans le code base (les Content
 * Types partagent un contrôleur générique unique, cf. docblock de
 * `ModuleGenerator`). Les routes elles-mêmes (`routes/admin.php`) sont
 * assemblées par `ModuleGenerator`, pas ici (un seul fichier pour toutes les
 * entités du module, alors que ce générateur travaille entité par entité).
 *
 * **Narrowing assumé** : seuls les types de champ dont le composant de
 * formulaire suit le contrat uniforme `name`/`label`/`value` (+ `options`
 * pour select/radio) sont exposés dans le formulaire généré —
 * `gallery`/`media` (sélecteur de média, hors périmètre de cette passe) et
 * `multiselect` (`FieldType::formComponent()` déclare `baobab::field.multiselect`
 * mais ce composant n'existe pas encore, écart réel du Core découvert en
 * construisant cette passe) sont exclus du formulaire ET de la liste admin
 * générés ; la colonne réelle en base, elle, existe toujours (héritée de la
 * Pass A1), seule son édition via ce CRUD généré est différée.
 */
final class AdminCrudGenerator
{
    public function __construct(
        private readonly FieldRegistry $fields,
        private readonly RelationTargetResolver $coreTargets,
    ) {}

    /**
     * @param  array<string, mixed>  $entity
     */
    public function isEnabled(array $entity): bool
    {
        return (bool) ($entity['routes']['admin'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function controller(array $entity, string $namespace, string $slug): string
    {
        $key = (string) $entity['key'];
        $var = Str::camel($key);

        return (new StubRenderer)->render(StudioStubs::path('admin-controller'), [
            'namespace' => $namespace,
            'key' => $key,
            'var' => $var,
            'view_namespace' => $slug,
            'view_prefix' => EntityFields::viewPrefix($entity),
            'route_name_prefix' => $this->routeNamePrefix($entity, $slug),
            'form_fields' => self::joinLines([
                $this->formFieldsLiteral($entity, $var),
                $this->relationFieldsLiteral($entity, $var, $namespace),
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function request(array $entity, string $namespace): string
    {
        return (new StubRenderer)->render(StudioStubs::path('admin-request'), [
            'namespace' => $namespace,
            'key' => (string) $entity['key'],
            'rules' => $this->rulesLiteral($entity, $namespace),
            'json_fields' => EntityFields::keysLiteral($entity, 'json'),
            'boolean_fields' => EntityFields::keysLiteral($entity, 'boolean'),
            'time_fields' => EntityFields::keysLiteral($entity, 'time'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function indexView(array $entity, string $slug): string
    {
        return (new StubRenderer)->render(StudioStubs::path('admin-index'), [
            'title' => EntityFields::title($entity),
            'route_name_prefix' => $this->routeNamePrefix($entity, $slug),
            'column_headers' => $this->columnHeaders($entity),
            'column_cells' => $this->columnCells($entity),
        ]);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function formView(array $entity): string
    {
        return (new StubRenderer)->render(StudioStubs::path('admin-form'), [
            'title' => EntityFields::title($entity),
        ]);
    }

    /**
     * Fragment de `routes/admin.php` pour cette entité — imbriqué par
     * `ModuleGenerator` dans le groupe `admin`/`admin.` déjà appliqué par
     * `ModuleServiceProvider::loadModuleRoutes()`.
     *
     * @param  array<string, mixed>  $entity
     */
    public function routesFragment(array $entity, string $namespace, string $slug): string
    {
        $key = (string) $entity['key'];
        $var = Str::camel($key);
        $viewPrefix = EntityFields::viewPrefix($entity);
        $controller = "\\{$namespace}\\Http\\Controllers\\Admin\\{$key}Controller";

        return <<<PHP
        Route::prefix('{$slug}/{$viewPrefix}')->name('{$slug}.{$viewPrefix}.')->group(function (): void {
            Route::get('/', [{$controller}::class, 'index'])->name('index');
            Route::get('/create', [{$controller}::class, 'create'])->name('create');
            Route::post('/', [{$controller}::class, 'store'])->name('store');
            Route::get('/{{$var}}/edit', [{$controller}::class, 'edit'])->name('edit');
            Route::put('/{{$var}}', [{$controller}::class, 'update'])->name('update');
            Route::delete('/{{$var}}', [{$controller}::class, 'destroy'])->name('destroy');
        });
        PHP;
    }

    /**
     * Assemble des blocs de lignes en ignorant les vides — une entité sans
     * relation ne doit pas produire de ligne blanche au milieu du littéral.
     *
     * @param  list<string>  $blocks
     */
    private static function joinLines(array $blocks): string
    {
        return implode("\n", array_filter($blocks, static fn (string $block): bool => trim($block) !== ''));
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function formFieldsLiteral(array $entity, string $var): string
    {
        return collect(EntityFields::included($entity))
            ->map(function (array $field) use ($var): string {
                $key = (string) $field['key'];
                $component = $this->fields->resolve($field['type'])->formComponent();
                $label = Str::headline($key);
                $line = "            ['key' => '{$key}', 'label' => '{$label}', 'component' => '{$component}', 'value' => \${$var}->{$key}";

                // `multiselect` rejoint la liste avec la création de son
                // composant (n° 117) : sans ses choix, il rendrait une liste
                // vide, ce qui est pire qu'un champ absent.
                if (in_array($field['type'], ['select', 'radio', 'multiselect'], true)) {
                    $choices = (array) ($field['options']['choices'] ?? []);
                    $optionsLiteral = collect($choices)->map(fn (string $choice): string => "'{$choice}' => '{$choice}'")->implode(', ');
                    $line .= ", 'options' => [{$optionsLiteral}]";
                }

                return $line.'],';
            })
            ->implode("\n");
    }

    /**
     * Les lignes de saisie des relations `belongsTo`, ajoutées après les champs
     * (n° 140). Deux différences avec un champ ordinaire, et elles expliquent
     * pourquoi ce n'est pas la même boucle :
     *
     * - la valeur courante se lit sur la **colonne** `{clé}_id`, pas sur la
     *   relation, qui chargerait le modèle lié pour n'en tirer qu'un entier ;
     * - les options ne peuvent pas être un littéral — elles dépendent du contenu
     *   de la table cible au moment de l'affichage. Seule la classe du modèle
     *   est figée ici ; la requête et le choix de la colonne de libellé restent
     *   à l'exécution, dans `RelationOptions`.
     *
     * @param  array<string, mixed>  $entity
     */
    private function relationFieldsLiteral(array $entity, string $var, string $namespace): string
    {
        return collect(BelongsToRelations::from($entity))
            ->map(function (array $relation) use ($var, $namespace): ?string {
                $class = $this->relationModelClass($relation['target'], $namespace);

                if ($class === null) {
                    return null;
                }

                return sprintf(
                    "            ['key' => '%s', 'label' => '%s', 'component' => 'baobab::field.relation', 'value' => \$%s->%s, 'options' => \\%s::for(\\%s::class)],",
                    $relation['column'],
                    addslashes($relation['label']),
                    $var,
                    $relation['column'],
                    RelationOptions::class,
                    $class,
                );
            })
            ->filter()
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function rulesLiteral(array $entity, string $namespace): string
    {
        // Les relations `belongsTo` étaient `fillable` sans être validées
        // (n° 140) : une clé étrangère inventée n'échouait qu'au niveau de la
        // base, en 500, au lieu d'être refusée par le formulaire.
        //
        // `exists:` reçoit la **classe du modèle** et non un nom de table :
        // Laravel la résout lui-même (`ValidatesAttributes::parseTable()`), ce
        // qui évite de recalculer ici un nom de table dont la cible est seule
        // à connaître la forme.
        $relationRules = collect(BelongsToRelations::from($entity))
            ->map(function (array $relation) use ($namespace): ?string {
                $class = $this->relationModelClass($relation['target'], $namespace);

                if ($class === null) {
                    return null;
                }

                $presence = $relation['required'] ? 'required' : 'nullable';
                $escaped = str_replace('\\', '\\\\', $class);

                return "            '{$relation['column']}' => ['{$presence}', 'integer', 'exists:{$escaped},id'],";
            })
            ->filter()
            ->implode("\n");

        return self::joinLines([
            EntityFields::validationRulesLiteral($entity, $this->fields),
            $relationRules,
        ]);
    }

    /**
     * Classe du modèle visé par une relation du Studio. Les cibles y sont
     * **préfixées** (`StudioRelationTargetResolver`), et les deux familles ne se
     * résolvent pas au même moment :
     *
     * - `entity:{Clé}` désigne une entité sœur du même module — sa classe
     *   n'existe qu'à la génération, elle se déduit du namespace ;
     * - `content_type:{Clé}` et `core:{Modèle}` désignent quelque chose de déjà
     *   construit, que le résolveur du Core sait nommer.
     *
     * `null` si la cible est introuvable : la génération ne doit pas échouer
     * ici — le blueprint est validé en amont, et un formulaire sans ce champ
     * vaut mieux qu'un module qui ne se génère plus.
     */
    private function relationModelClass(string $target, string $namespace): ?string
    {
        if (Str::startsWith($target, 'entity:')) {
            return "{$namespace}\\Models\\".Str::after($target, 'entity:');
        }

        if (! Str::startsWith($target, ['content_type:', 'core:'])) {
            return null;
        }

        try {
            return $this->coreTargets->resolve(Str::after($target, ':'))['class'];
        } catch (UnknownRelationTargetException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function columnHeaders(array $entity): string
    {
        return collect(EntityFields::included($entity))
            ->map(fn (array $field): string => '                            <th class="px-3 py-2 font-medium">'.Str::headline((string) $field['key']).'</th>')
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function columnCells(array $entity): string
    {
        return collect(EntityFields::included($entity))
            ->map(function (array $field): string {
                $key = (string) $field['key'];
                $value = $field['type'] === 'boolean'
                    ? "\$row->{$key} ? __('Oui') : __('Non')"
                    : "\$row->{$key}";

                return "                                <td class=\"px-3 py-2 text-foreground\">{{ {$value} }}</td>";
            })
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function routeNamePrefix(array $entity, string $slug): string
    {
        return "admin.{$slug}.".EntityFields::viewPrefix($entity);
    }
}
