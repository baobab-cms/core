<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Generator\StubRenderer;
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
    public function __construct(private readonly FieldRegistry $fields) {}

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
            'form_fields' => $this->formFieldsLiteral($entity, $var),
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
            'rules' => $this->rulesLiteral($entity),
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

                if (in_array($field['type'], ['select', 'radio'], true)) {
                    $choices = (array) ($field['options']['choices'] ?? []);
                    $optionsLiteral = collect($choices)->map(fn (string $choice): string => "'{$choice}' => '{$choice}'")->implode(', ');
                    $line .= ", 'options' => [{$optionsLiteral}]";
                }

                return $line.'],';
            })
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function rulesLiteral(array $entity): string
    {
        return EntityFields::validationRulesLiteral($entity, $this->fields);
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
