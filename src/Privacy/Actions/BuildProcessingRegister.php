<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Modules\Models\Module;
use Baobab\Privacy\PrivacyRegistry;
use Baobab\Privacy\ProcessingRegister;
use Illuminate\Support\Facades\File;

/**
 * Génère le registre des traitements depuis les déclarations des fournisseurs
 * (spec 16 §2.2). Lecture seule : les adaptateurs (écran, export HTML,
 * `baobab:privacy:register`) l'appellent, aucun ne recompose sa propre vue.
 *
 * **Signal « module sans déclaration »** (spec §2.2, cadrage Pass A) —
 * heuristique volontairement simple : un module *actif* de type `module`
 * (ni Content Type, dont les tables relèvent de `core.content_authorship`,
 * ni thème) qui possède au moins une migration et dont aucun fournisseur
 * n'est enregistré sous sa clé (`{slug}` ou `{slug}.*`, `slug` étant la
 * partie après le `/` du nom du module). Un signal, jamais un blocage.
 */
final class BuildProcessingRegister
{
    public function __construct(private readonly PrivacyRegistry $registry) {}

    public function __invoke(): ProcessingRegister
    {
        $declarations = [];
        $recipients = [];

        foreach ($this->registry->all() as $key => $provider) {
            $declaration = $provider->describe();
            $declarations[$key] = $declaration;

            foreach ($declaration->externalServices as $service) {
                $recipients[$service] = $service;
            }
        }

        return new ProcessingRegister(
            declarations: $declarations,
            recipients: array_values($recipients),
            undeclaredModules: $this->undeclaredModules(array_keys($declarations)),
        );
    }

    /**
     * @param  list<string>  $providerKeys
     * @return list<string>
     */
    private function undeclaredModules(array $providerKeys): array
    {
        $undeclared = [];

        foreach (Module::query()->where('status', 'active')->where('type', 'module')->orderBy('name')->get() as $module) {
            if (File::glob($module->path.'/database/migrations/*.php') === []) {
                continue;
            }

            $slug = str_contains($module->name, '/') ? explode('/', $module->name, 2)[1] : $module->name;

            $declared = array_filter(
                $providerKeys,
                static fn (string $key): bool => $key === $slug || str_starts_with($key, $slug.'.'),
            );

            if ($declared === []) {
                $undeclared[] = $module->name;
            }
        }

        return $undeclared;
    }
}
