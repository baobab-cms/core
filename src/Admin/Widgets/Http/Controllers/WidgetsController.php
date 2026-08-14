<?php

declare(strict_types=1);

namespace Baobab\Admin\Widgets\Http\Controllers;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\Themes\Models\ThemeWidgetZone;
use Baobab\Widgets\Actions\CreateWidgetInstance;
use Baobab\Widgets\Actions\DeleteWidgetInstance;
use Baobab\Widgets\Actions\ReorderWidgetInstance;
use Baobab\Widgets\Actions\UpdateWidgetInstance;
use Baobab\Widgets\Models\WidgetInstance;
use Baobab\Widgets\Widget;
use Baobab\Widgets\WidgetRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Écran « Widgets » (spec 10 §3.3) — instances par zone, réglages générés
 * depuis `settingsSchema()` (même mécanisme que les Content Types, spec 02
 * §3). Accès gouverné par `baobab.widgets.manage` (routes/admin.php) ; le
 * widget « HTML personnalisé » exige en plus `baobab.widgets.unsafe_html`,
 * vérifié dans les Actions d'écriture.
 */
final class WidgetsController
{
    public function __construct(
        private readonly WidgetRegistry $widgets,
        private readonly FieldRegistry $fields,
    ) {}

    public function index(): View
    {
        $activeInstances = WidgetInstance::where('is_active', true)->orderBy('order')->get();
        $usedZoneKeys = $activeInstances->pluck('zone_key')->unique();

        $zones = ThemeWidgetZone::where('is_active', true)
            ->orWhereIn('key', $usedZoneKeys)
            ->orderBy('label')
            ->get();

        return view('baobab::admin.widgets.index', [
            'zones' => $zones,
            'instancesByZone' => $activeInstances->groupBy('zone_key'),
            'inactive' => WidgetInstance::where('is_active', false)->orderBy('order')->get(),
            'widgetLabels' => $this->widgetLabels(),
        ]);
    }

    public function create(Request $request): View
    {
        $requestedKey = $request->query('widget_key');
        $widgetKey = is_string($requestedKey) && $this->widgets->has($requestedKey) ? $requestedKey : null;
        $fields = $widgetKey !== null ? $this->widgets->resolve($widgetKey)->settingsSchema() : [];

        return view('baobab::admin.widgets.create', [
            'widgetLabels' => $this->widgetLabels(),
            'widgetKey' => $widgetKey,
            'zoneKey' => $request->query('zone_key'),
            'zones' => ThemeWidgetZone::where('is_active', true)->orderBy('label')->get(),
            'fields' => $this->withResolvedValues($fields, []),
        ]);
    }

    public function store(Request $request, CreateWidgetInstance $action): RedirectResponse
    {
        $widgetKey = $request->string('widget_key')->toString();
        abort_unless($this->widgets->has($widgetKey), 422, 'Widget inconnu.');

        $widget = $this->widgets->resolve($widgetKey);

        $validated = $request->validate([
            'zone_key' => ['required', 'string'],
            'widget_key' => ['required', 'string'],
            'visibility' => ['nullable', 'string'],
            ...$this->settingsRules($widget),
        ]);

        $action(
            $validated['zone_key'],
            $widgetKey,
            $this->settingsFromValidated($widget, $validated),
            $validated['visibility'] ?? 'everyone',
        );

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.widgets.created')]);

        return redirect()->route('admin.widgets.index');
    }

    public function edit(WidgetInstance $instance): View
    {
        $widget = $this->widgets->resolve($instance->widget_key);

        return view('baobab::admin.widgets.edit', [
            'instance' => $instance,
            'widgetLabel' => $widget::label(),
            'fields' => $this->withResolvedValues(
                $this->withCurrentValue($widget->settingsSchema(), $instance->settings ?? []),
                $instance->settings ?? [],
            ),
            'zones' => ThemeWidgetZone::where('is_active', true)
                ->orWhere('key', $instance->zone_key)
                ->orderBy('label')
                ->get(),
        ]);
    }

    public function update(WidgetInstance $instance, Request $request, UpdateWidgetInstance $action): RedirectResponse
    {
        $widget = $this->widgets->resolve($instance->widget_key);

        $validated = $request->validate([
            'zone_key' => ['required', 'string'],
            'visibility' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            ...$this->settingsRules($widget),
        ]);

        $action(
            $instance,
            $this->settingsFromValidated($widget, $validated),
            $validated['zone_key'],
            $validated['visibility'],
            (bool) ($validated['is_active'] ?? false),
        );

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.widgets.updated')]);

        return redirect()->route('admin.widgets.index');
    }

    public function destroy(WidgetInstance $instance, DeleteWidgetInstance $action): RedirectResponse
    {
        $action($instance);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.widgets.deleted')]);

        return redirect()->route('admin.widgets.index');
    }

    public function moveUp(WidgetInstance $instance, ReorderWidgetInstance $action): RedirectResponse
    {
        $action($instance, 'up');

        return redirect()->route('admin.widgets.index');
    }

    public function moveDown(WidgetInstance $instance, ReorderWidgetInstance $action): RedirectResponse
    {
        $action($instance, 'down');

        return redirect()->route('admin.widgets.index');
    }

    /**
     * @return array<string, string>
     */
    private function widgetLabels(): array
    {
        $labels = [];

        foreach ($this->widgets->all() as $key => $class) {
            $labels[$key] = $class::label();
        }

        return $labels;
    }

    /**
     * @return array<string, list<string>>
     */
    private function settingsRules(Widget $widget): array
    {
        $rules = [];

        foreach ($widget->settingsSchema() as $field) {
            $fieldType = $this->fields->resolve($field['type']);
            $typeRules = $fieldType->rules($field['key'], $field['options'] ?? []);

            $rules[$field['key']] = array_merge(($field['required'] ?? false) ? ['required'] : ['nullable'], $typeRules);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function settingsFromValidated(Widget $widget, array $validated): array
    {
        $settings = [];

        foreach ($widget->settingsSchema() as $field) {
            $settings[$field['key']] = $validated[$field['key']] ?? $field['default'] ?? null;
        }

        return $settings;
    }

    /**
     * La valeur à afficher pour chaque champ : celle enregistrée, sinon le
     * défaut du schéma.
     *
     * Résolue ici et non dans le partiel, où elle vivait dans un `@php`
     * inline (suivi n° 138) — c'est le même geste que `ContentController`,
     * dont ce formulaire reprend déjà le patron.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function withResolvedValues(array $fields, array $settings): array
    {
        foreach ($fields as &$field) {
            $key = $field['key'] ?? null;

            $field['value'] = is_string($key) && array_key_exists($key, $settings)
                ? $settings[$key]
                : ($field['default'] ?? null);
        }

        return $fields;
    }

    /**
     * Une valeur courante absente des choix actifs d'un champ `select` (ex.
     * emplacement de menu devenu orphelin) reste sélectionnable — patron
     * `MenusController::edit()` (locations orphelines assignées).
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function withCurrentValue(array $fields, array $settings): array
    {
        foreach ($fields as &$field) {
            if ($field['type'] !== 'select') {
                continue;
            }

            $current = $settings[$field['key']] ?? null;

            if ($current !== null && ! array_key_exists($current, $field['choice_options'])) {
                $field['choice_options'][$current] = $current;
                $field['options']['choices'][] = $current;
            }
        }

        return $fields;
    }
}
