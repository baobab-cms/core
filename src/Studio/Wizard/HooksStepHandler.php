<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Studio\Support\BlueprintPermissions;
use Baobab\Webhooks\Support\WebhookEventCatalog;

/**
 * Étape 8 — Hooks émis et écoutés (spec-modules §5.2 étape 8, schéma
 * `module-blueprint.schema.json#/properties/hooks`).
 *
 * Les deux listes n'ont pas le même statut, et l'écran doit le dire :
 * `emits` est de la **documentation pure** (aucun fichier généré, le bloc
 * est simplement lu par `bootstrapActiveModules()` et par le catalogue
 * d'événements des webhooks) ; `listens` **génère un squelette de classe**
 * sous `src/Hooks/{ClassName}.php`, à compléter à la main.
 *
 * L'autocomplétion des hooks écoutables vient de `WebhookEventCatalog`, et
 * non de `HookRegistry` : cette dernière ne connaît que les hooks ayant déjà
 * un listener enregistré, jamais un catalogue de noms documentés — c'est
 * exactement l'écart que le catalogue avait été construit pour combler
 * (suivi n° 75). Premier consommateur de ce catalogue hors du domaine
 * webhooks.
 */
final class HooksStepHandler implements StudioStepHandler
{
    public function number(): int
    {
        return 8;
    }

    public function label(): string
    {
        return __('baobab::admin.studio.steps.hooks');
    }

    public function view(): string
    {
        return 'baobab::admin.studio.steps.hooks';
    }

    public function rules(ModuleBlueprintDraft $draft): array
    {
        return [
            'emits' => ['nullable', 'string'],
            'listens' => ['required', 'string'],
        ];
    }

    public function fill(array $validated, array $blueprint): array
    {
        $decoded = json_decode((string) ($validated['listens'] ?? ''), associative: true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw InvalidModuleBlueprintException::malformedJson(json_last_error_msg());
        }

        $hooks = array_filter([
            'emits' => $this->parseEmits((string) ($validated['emits'] ?? '')),
            'listens' => $this->normalizeListens($decoded),
        ], static fn (array $block): bool => $block !== []);

        if ($hooks === []) {
            unset($blueprint['hooks']);

            return $blueprint;
        }

        $blueprint['hooks'] = $hooks;

        return $blueprint;
    }

    public function initialValues(array $blueprint): array
    {
        // La vue travaille sur une liste de paires : un objet PHP associatif
        // ne survivrait pas à `x-for` avec un index stable.
        $listens = [];

        foreach ($blueprint['hooks']['listens'] ?? [] as $hook => $className) {
            $listens[] = ['hook' => (string) $hook, 'class_name' => (string) $className];
        }

        return [
            'emits' => implode("\n", $blueprint['hooks']['emits'] ?? []),
            'listens' => $listens,
        ];
    }

    /**
     * `hookCatalogue` alimente une `<datalist>` : le champ reste libre (un
     * module peut écouter un hook d'un autre module que le catalogue ignore),
     * l'autocomplétion n'est qu'une aide. `hookPrefix` est le préfixe suggéré
     * pour les hooks émis — « nommage assisté » de la spec, sans imposer
     * quoi que ce soit.
     */
    public function viewData(array $blueprint): array
    {
        return [
            'hookCatalogue' => WebhookEventCatalog::all(),
            'hookPrefix' => BlueprintPermissions::slug($blueprint),
        ];
    }

    public function valuesFromOldInput(array $old, array $values): array
    {
        $submitted = $old['listens'] ?? null;

        if (! is_string($submitted)) {
            return $values;
        }

        $decoded = json_decode($submitted, associative: true);

        if (! is_array($decoded)) {
            return $values;
        }

        // `emits` est une simple textarea : `<x-baobab::field.textarea>`
        // applique déjà `old()`, il n'y a que `listens` à redécoder.
        return [...$values, 'listens' => $decoded];
    }

    /**
     * Une ligne par hook émis (patron `IdentityStepHandler::parseAuthors()`).
     *
     * @return list<string>
     */
    private function parseEmits(string $raw): array
    {
        $emits = [];

        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            $hook = trim($line);

            if ($hook !== '') {
                $emits[] = $hook;
            }
        }

        return $emits;
    }

    /**
     * Le bloc `listens` est un **objet** hook → nom court de classe, alors que
     * le formulaire manipule une liste de paires : une ligne incomplète est
     * écartée (elle ne peut rien câbler), un hook répété garde la dernière
     * classe saisie — un objet JSON ne peut pas porter deux fois la même clé,
     * autant que ce soit la valeur visible en bas d'écran qui gagne.
     *
     * @param  array<int, mixed>  $listens
     * @return array<string, string>
     */
    private function normalizeListens(array $listens): array
    {
        $normalized = [];

        foreach ($listens as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $hook = trim((string) ($entry['hook'] ?? ''));
            $className = trim((string) ($entry['class_name'] ?? ''));

            if ($hook === '' || $className === '') {
                continue;
            }

            $normalized[$hook] = $className;
        }

        return $normalized;
    }
}
