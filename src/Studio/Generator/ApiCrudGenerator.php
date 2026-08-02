<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Generator\StubRenderer;
use Baobab\Studio\Generator\Support\EntityFields;
use Illuminate\Support\Str;

/**
 * Génère l'API REST (`spec-modules §5.2 étape 4) d'une entité de blueprint
 * Wizard Studio — opt-in (`entity.routes.api`, défaut `false`). Contrôleur
 * dédié + `JsonResource` de sérialisation, CRUD complet (contrairement au
 * front, limité à `index`/`show` par la spec). Réutilise le `{Key}Request`
 * généré pour l'admin (mêmes règles de validation, un seul Form Request par
 * entité — `ModuleGenerator` l'écrit dès que l'une des deux surfaces est
 * activée) plutôt que d'en dupliquer un second.
 */
final class ApiCrudGenerator
{
    /**
     * @param  array<string, mixed>  $entity
     */
    public function isEnabled(array $entity): bool
    {
        return (bool) ($entity['routes']['api'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function controller(array $entity, string $namespace): string
    {
        $key = (string) $entity['key'];

        return (new StubRenderer)->render(StudioStubs::path('api-controller'), [
            'namespace' => $namespace,
            'key' => $key,
            'var' => Str::camel($key),
        ]);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function resource(array $entity, string $namespace): string
    {
        return (new StubRenderer)->render(StudioStubs::path('api-resource'), [
            'namespace' => $namespace,
            'key' => (string) $entity['key'],
            'fields' => $this->fieldsLiteral($entity),
        ]);
    }

    /**
     * Fragment de `routes/api.php` pour cette entité — imbriqué par
     * `ModuleGenerator` dans le groupe `api/v1`/`api.v1.` déjà appliqué par
     * `ModuleServiceProvider::loadModuleRoutes()`.
     *
     * @param  array<string, mixed>  $entity
     */
    public function routesFragment(array $entity, string $namespace, string $slug): string
    {
        $key = (string) $entity['key'];
        $var = Str::camel($key);
        $viewPrefix = EntityFields::viewPrefix($entity);
        $controller = "\\{$namespace}\\Http\\Controllers\\Api\\{$key}Controller";

        return <<<PHP
        Route::prefix('{$slug}/{$viewPrefix}')->name('{$slug}.{$viewPrefix}.')->group(function (): void {
            Route::get('/', [{$controller}::class, 'index'])->name('index');
            Route::get('/{{$var}}', [{$controller}::class, 'show'])->name('show');
            Route::post('/', [{$controller}::class, 'store'])->name('store');
            Route::put('/{{$var}}', [{$controller}::class, 'update'])->name('update');
            Route::delete('/{{$var}}', [{$controller}::class, 'destroy'])->name('destroy');
        });
        PHP;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function fieldsLiteral(array $entity): string
    {
        return collect(EntityFields::included($entity))
            ->map(fn (array $field): string => "            '{$field['key']}' => \$this->{$field['key']},")
            ->implode("\n");
    }
}
