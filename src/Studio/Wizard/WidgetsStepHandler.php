<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Studio\Support\BlueprintFields;

/**
 * Étape 7 — Widgets (spec-modules §5.2 étape 7, schéma
 * `module-blueprint.schema.json#/properties/widgets`).
 *
 * `settings_fields` a **exactement la forme de `entities[].fields`** — le
 * moteur le reconnaît déjà (`ModuleBlueprint::validateWidgets()` réutilise
 * `validateFields()` depuis la Pass A3c) ; la saisie suit, via
 * `BlueprintFields::normalize()`, partagée avec l'étape 2.
 *
 * `class_name` est un **nom court** (le blueprint ne connaît pas le namespace
 * du module), résolu en FQCN par `ModuleGenerator` au moment d'écrire le
 * `module.json` — patron `hooks.listens`, contrairement aux menus qui sont
 * recopiés bruts.
 */
final class WidgetsStepHandler implements StudioStepHandler
{
    public function __construct(private readonly FieldRegistry $fields) {}

    public function number(): int
    {
        return 7;
    }

    public function label(): string
    {
        return __('baobab::admin.studio.steps.widgets');
    }

    public function view(): string
    {
        return 'baobab::admin.studio.steps.widgets';
    }

    public function rules(ModuleBlueprintDraft $draft): array
    {
        return [
            'widgets' => ['required', 'string'],
        ];
    }

    public function fill(array $validated, array $blueprint): array
    {
        $decoded = json_decode((string) ($validated['widgets'] ?? ''), associative: true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw InvalidModuleBlueprintException::malformedJson(json_last_error_msg());
        }

        $widgets = [];

        foreach ($decoded as $widget) {
            if (! is_array($widget)) {
                continue;
            }

            $normalized = $this->normalizeWidget($widget);

            if ($normalized !== null) {
                $widgets[] = $normalized;
            }
        }

        if ($widgets === []) {
            unset($blueprint['widgets']);

            return $blueprint;
        }

        $blueprint['widgets'] = $widgets;

        return $blueprint;
    }

    public function initialValues(array $blueprint): array
    {
        return [
            'widgets' => $blueprint['widgets'] ?? [],
        ];
    }

    /**
     * **Aucun choix de zone ici, volontairement.** La spec (§5.2 étape 7)
     * écrit « nom, zone(s) cible(s), champs de configuration », mais le schéma
     * livré en Pass A3c n'a jamais porté de zone — et c'est cohérent : une
     * zone est déclarée par le **thème actif** (`ThemeWidgetZone`, spec 10),
     * pas par le module, et le placement se fait par instance dans
     * `admin/widgets` une fois le module activé. Offrir un sélecteur de zone
     * ici laisserait croire que le blueprint la retient. Divergence
     * pré-existante, signalée au suivi plutôt que rouverte dans cette passe.
     */
    public function viewData(array $blueprint): array
    {
        return [
            'fieldTypes' => array_keys($this->fields->all()),
            'typesNeedingChoices' => BlueprintFields::TYPES_NEEDING_CHOICES,
        ];
    }

    public function valuesFromOldInput(array $old, array $values): array
    {
        $submitted = $old['widgets'] ?? null;

        if (! is_string($submitted)) {
            return $values;
        }

        $decoded = json_decode($submitted, associative: true);

        if (! is_array($decoded)) {
            return $values;
        }

        return ['widgets' => $decoded];
    }

    /**
     * Une ligne sans clé **ni** libellé **ni** nom de classe est une ligne
     * ajoutée puis abandonnée : ignorée. Dès qu'un des trois est rempli,
     * l'entrée part telle quelle vers la cross-validation, qui dira lequel
     * manque.
     *
     * @param  array<string, mixed>  $widget
     * @return array<string, mixed>|null
     */
    private function normalizeWidget(array $widget): ?array
    {
        $key = trim((string) ($widget['key'] ?? ''));
        $label = trim((string) ($widget['label'] ?? ''));
        $className = trim((string) ($widget['class_name'] ?? ''));

        if ($key === '' && $label === '' && $className === '') {
            return null;
        }

        $normalized = [
            'key' => $key,
            'label' => $label,
            'class_name' => $className,
        ];

        // `is_numeric()` suffit : elle écarte déjà la chaîne vide et `null`,
        // c'est-à-dire un champ laissé vide (pas de cache).
        if (is_numeric($widget['cache_ttl'] ?? null)) {
            $normalized['cache_ttl'] = (int) $widget['cache_ttl'];
        }

        $settings = BlueprintFields::normalize($widget['settings_fields'] ?? []);

        if ($settings !== []) {
            $normalized['settings_fields'] = $settings;
        }

        return $normalized;
    }
}
