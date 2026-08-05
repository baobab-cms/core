<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

use Baobab\Studio\Models\ModuleBlueprintDraft;

/**
 * Étape 1 — Identité (spec-modules §5.2 étape 1, schéma
 * `module-blueprint.schema.json#/properties/identity`). `type` n'est jamais
 * un champ du formulaire : le schéma le verrouille à `"module"` (spec 5.5,
 * Studio v1 ne produit que des modules génériques) — `fill()` l'impose donc
 * inconditionnellement, quelle que soit la soumission.
 */
final class IdentityStepHandler implements StudioStepHandler
{
    /** Même pattern que module-blueprint.schema.json#/properties/identity/properties/name. */
    private const NAME_PATTERN = '/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$/';

    private const VERSION_PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(-[0-9A-Za-z.-]+)?$/';

    private const ICON_PATTERN = '/^(bi|fas|far|fab)-[a-z0-9-]+$/';

    public function number(): int
    {
        return 1;
    }

    public function label(): string
    {
        return __('baobab::admin.studio.steps.identity');
    }

    public function view(): string
    {
        return 'baobab::admin.studio.steps.identity';
    }

    public function rules(ModuleBlueprintDraft $draft): array
    {
        return [
            'name' => ['required', 'string', 'regex:'.self::NAME_PATTERN, 'unique:module_blueprints,vendor_slug,'.$draft->id],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'regex:'.self::ICON_PATTERN],
            'version' => ['required', 'string', 'regex:'.self::VERSION_PATTERN],
            'authors' => ['nullable', 'string'],
        ];
    }

    public function fill(array $validated, array $blueprint): array
    {
        $blueprint['identity'] = array_filter([
            'name' => $validated['name'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'authors' => $this->parseAuthors((string) ($validated['authors'] ?? '')),
            'version' => $validated['version'],
            'type' => 'module',
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        $blueprint['identity']['type'] = 'module';

        return $blueprint;
    }

    public function initialValues(array $blueprint): array
    {
        $identity = $blueprint['identity'] ?? [];

        return [
            'name' => $identity['name'] ?? null,
            'title' => $identity['title'] ?? null,
            'description' => $identity['description'] ?? null,
            'icon' => $identity['icon'] ?? null,
            'version' => $identity['version'] ?? '1.0.0',
            'authors' => $this->formatAuthors($identity['authors'] ?? []),
        ];
    }

    public function viewData(): array
    {
        return [];
    }

    /**
     * Champs simples uniquement : `<x-baobab::field.text>` /
     * `<x-baobab::field.textarea>` appliquent déjà `old($name, $value)`,
     * rien à réhydrater ici.
     */
    public function valuesFromOldInput(array $old, array $values): array
    {
        return $values;
    }

    /**
     * Une ligne par auteur : « Nom <email> (url) », email/url optionnels.
     * Patron des listes répétables sans librairie de tri déjà établi pour les
     * menus/widgets (M6) et l'ébauche Studio thèmes (suivi n° 88). Une ligne
     * sans nom exploitable est ignorée plutôt que de faire échouer l'étape.
     *
     * @return list<array{name: string, email?: string, url?: string}>
     */
    private function parseAuthors(string $raw): array
    {
        $authors = [];

        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $email = null;
            $url = null;

            if (preg_match('/<([^>]+)>/', $line, $match) === 1) {
                $email = trim($match[1]);
                $line = trim(str_replace($match[0], '', $line));
            }

            if (preg_match('/\(([^)]+)\)/', $line, $match) === 1) {
                $url = trim($match[1]);
                $line = trim(str_replace($match[0], '', $line));
            }

            $name = trim($line);

            if ($name === '') {
                continue;
            }

            $authors[] = array_filter([
                'name' => $name,
                'email' => $email,
                'url' => $url,
            ], static fn (?string $value): bool => $value !== null);
        }

        return $authors;
    }

    /**
     * @param  list<array<string, mixed>>  $authors
     */
    private function formatAuthors(array $authors): string
    {
        return implode("\n", array_map(static function (array $author): string {
            $line = (string) ($author['name'] ?? '');

            if (! empty($author['email'])) {
                $line .= " <{$author['email']}>";
            }

            if (! empty($author['url'])) {
                $line .= " ({$author['url']})";
            }

            return $line;
        }, $authors));
    }
}
