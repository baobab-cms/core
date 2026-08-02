<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Generator\StubRenderer;
use Baobab\Studio\Generator\Support\EntityFields;
use Illuminate\Support\Str;

/**
 * Génère les routes front (`index`/`show`, spec-modules §5.2 étape 4) d'une
 * entité de blueprint Wizard Studio — opt-in (`entity.routes.front`, défaut
 * `false`), contrairement à l'admin. Contrôleur dédié, pas le moteur
 * générique des Content Types (spec §5.1 : le Studio génère du code lisible,
 * pas de magie).
 *
 * URL sous `/{slug}/{entités}` (comme l'admin) plutôt que des URLs nues
 * (`/cars`) : deux modules Studio déclarant chacun une entité `Car`
 * colliseraient sinon sur la même route. La collision avec la route
 * publique générique du Core (`{prefix}/{slug?}`, spec 03) est résolue en
 * amont par l'ordre d'enregistrement (`bootstrapActiveModules()` avant
 * `registerPublicRoutes()`, cf. commentaire dans `BaobabServiceProvider::boot()`) —
 * pas par ce générateur lui-même.
 */
final class FrontCrudGenerator
{
    /**
     * @param  array<string, mixed>  $entity
     */
    public function isEnabled(array $entity): bool
    {
        return (bool) ($entity['routes']['front'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function controller(array $entity, string $namespace, string $slug): string
    {
        $key = (string) $entity['key'];

        return (new StubRenderer)->render(StudioStubs::path('front-controller'), [
            'namespace' => $namespace,
            'key' => $key,
            'var' => Str::camel($key),
            'view_namespace' => $slug,
            'view_prefix' => EntityFields::viewPrefix($entity),
        ]);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function indexView(array $entity, string $slug): string
    {
        return (new StubRenderer)->render(StudioStubs::path('front-index'), [
            'title' => EntityFields::title($entity),
            'route_name_prefix' => $this->routeNamePrefix($entity, $slug),
            'row_summary' => $this->rowSummary($entity),
        ]);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function showView(array $entity, string $slug, string $var): string
    {
        return (new StubRenderer)->render(StudioStubs::path('front-show'), [
            'title' => EntityFields::title($entity),
            'route_name_prefix' => $this->routeNamePrefix($entity, $slug),
            'fields' => $this->fieldsLiteral($entity, $var),
        ]);
    }

    /**
     * Fragment de `routes/web.php` pour cette entité — imbriqué par
     * `ModuleGenerator`, aucun middleware d'authentification (route
     * publique).
     *
     * @param  array<string, mixed>  $entity
     */
    public function routesFragment(array $entity, string $namespace, string $slug): string
    {
        $key = (string) $entity['key'];
        $var = Str::camel($key);
        $viewPrefix = EntityFields::viewPrefix($entity);
        $controller = "\\{$namespace}\\Http\\Controllers\\Front\\{$key}Controller";

        return <<<PHP
        Route::prefix('{$slug}/{$viewPrefix}')->name('{$slug}.{$viewPrefix}.')->group(function (): void {
            Route::get('/', [{$controller}::class, 'index'])->name('index');
            Route::get('/{{$var}}', [{$controller}::class, 'show'])->name('show');
        });
        PHP;
    }

    /**
     * Aperçu des champs d'une ligne dans la liste (`front-index`) — mêmes
     * champs que `fieldsLiteral()` (fiche détail), condensés sur une seule
     * ligne plutôt qu'en `<dt>/<dd>`, à l'intérieur du lien vers la fiche.
     *
     * @param  array<string, mixed>  $entity
     */
    private function rowSummary(array $entity): string
    {
        return collect(EntityFields::included($entity))
            ->map(fn (array $field): string => '{{ $row->'.$field['key'].' }}')
            ->implode(' &middot; ');
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function fieldsLiteral(array $entity, string $var): string
    {
        return collect(EntityFields::included($entity))
            ->map(function (array $field) use ($var): string {
                $key = (string) $field['key'];
                $label = Str::headline($key);

                return "        <dt>{$label}</dt>\n        <dd>{{ \${$var}->{$key} }}</dd>";
            })
            ->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function routeNamePrefix(array $entity, string $slug): string
    {
        return "{$slug}.".EntityFields::viewPrefix($entity);
    }
}
