<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory;
use BladeUI\Icons\Svg;
use Closure;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\View\Component;

/**
 * `<x-baobab::icon name="bi-house-door" />` — rend une icône par son nom
 * générique (`{set}-{icon}`, ex. `bi-*` Bootstrap Icons, `fas-*`/`far-*`/
 * `fab-*` FontAwesome) via `BladeUI\Icons\Factory::svg()` directement,
 * jamais `<x-dynamic-component>` : un nom invalide (typo dans un manifest de
 * module, JSON non compilé) replie silencieusement sur `bi-app-indicator`
 * plutôt que de faire planter tout le rendu de la sidebar.
 *
 * `render()` retourne une closure plutôt qu'une vue directe : `$attributes`
 * (ex. `class="h-5 w-5"`) n'est garanti disponible qu'après `withAttributes()`,
 * appelé après `render()` — une vue retournée directement capturerait
 * `$this->svg` alors qu'il est encore `null`. La closure n'est invoquée par
 * Laravel qu'au rendu effectif, une fois les attributs bien attachés.
 */
final class Icon extends Component
{
    private ?Svg $svg = null;

    public function __construct(public ?string $name = null) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withAttributes(array $attributes): static
    {
        parent::withAttributes($attributes);

        if ($this->name === null) {
            return $this;
        }

        $class = (string) $this->attributes->get('class', '');
        $extra = $this->attributes->except('class')->getAttributes();

        try {
            $this->svg = app(Factory::class)->svg($this->name, $class, $extra);
        } catch (SvgNotFound) {
            $this->svg = app(Factory::class)->svg('bi-app-indicator', $class, $extra);
        }

        return $this;
    }

    public function render(): Closure
    {
        return fn (): ViewContract => view('baobab::components.icon', ['svg' => $this->svg]);
    }
}
