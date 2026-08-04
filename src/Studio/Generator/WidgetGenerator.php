<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Generator\StubRenderer;
use Illuminate\Support\Str;

/**
 * Génère la classe `Widget` + la vue Blade déclarées au bloc `widgets` d'un
 * blueprint Wizard Studio (spec-modules §5.2 étape 7) — concept de module,
 * pas d'entité, patron `HookListenerGenerator`. Contrairement aux écouteurs
 * de hooks (squelette vide à compléter à la main), `settingsSchema()` est
 * entièrement dérivable des `settings_fields` déclarés — la classe générée
 * est donc fonctionnelle dès la génération ; seule la vue reste
 * volontairement « de base » (rendu générique des réglages), à affiner à la
 * main si un champ a besoin d'un rendu spécifique.
 */
final class WidgetGenerator
{
    /**
     * @param  array<string, mixed>  $widget
     */
    public function widgetClass(array $widget, string $namespace, string $viewNamespace): string
    {
        return (new StubRenderer)->render(StudioStubs::path('widget-class'), [
            'namespace' => $namespace,
            'class_name' => (string) $widget['class_name'],
            'key' => (string) $widget['key'],
            'label' => addslashes((string) $widget['label']),
            'settings_schema' => $this->settingsSchemaPhp((array) ($widget['settings_fields'] ?? [])),
            'view' => "{$viewNamespace}::widgets.{$this->viewName($widget)}",
            'cache_ttl' => array_key_exists('cache_ttl', $widget) && $widget['cache_ttl'] !== null
                ? (string) (int) $widget['cache_ttl']
                : 'null',
        ]);
    }

    /**
     * @param  array<string, mixed>  $widget
     */
    public function widgetView(array $widget): string
    {
        return (new StubRenderer)->render(StudioStubs::path('widget-view'), [
            'label' => addslashes((string) $widget['label']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $widget
     */
    public function viewName(array $widget): string
    {
        return Str::kebab((string) $widget['class_name']);
    }

    /**
     * Rend `settings_fields` en tableau PHP littéral, tel quel — même forme
     * que celle attendue par `Widget::settingsSchema()`, aucune
     * transformation de type de champ nécessaire (contrairement aux
     * migrations, où `FieldType::columnDefinition()` traduit chaque type en
     * colonne SQL).
     *
     * @param  array<int, mixed>  $fields
     */
    private function settingsSchemaPhp(array $fields): string
    {
        if ($fields === []) {
            return '[]';
        }

        $items = collect($fields)
            ->map(fn (array $field): string => $this->phpLiteral($field, 12))
            ->implode(",\n");

        return "[\n{$items},\n        ]";
    }

    private function phpLiteral(mixed $value, int $indent): string
    {
        $pad = str_repeat(' ', $indent);
        $innerPad = str_repeat(' ', $indent + 4);

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $isList = array_is_list($value);

            $entries = collect($value)
                ->map(function (mixed $item, int|string $key) use ($innerPad, $isList): string {
                    $rendered = $this->phpLiteral($item, strlen($innerPad));

                    return $isList
                        ? "{$innerPad}{$rendered}"
                        : "{$innerPad}'{$key}' => {$rendered}";
                })
                ->implode(",\n");

            return "[\n{$entries},\n{$pad}]";
        }

        return match (true) {
            is_string($value) => "'".addslashes($value)."'",
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            default => (string) $value,
        };
    }
}
