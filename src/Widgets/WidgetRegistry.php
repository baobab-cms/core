<?php

declare(strict_types=1);

namespace Baobab\Widgets;

use Baobab\Widgets\Exceptions\UnknownWidgetException;

/**
 * Registre central des widgets (spec 10 §3.1), patron exact
 * `Baobab\ContentTypes\Fields\FieldRegistry`. Les widgets Core sont
 * enregistrés au boot ; l'enregistrement de widgets fournis par un module
 * via le manifest (`ModuleManifest::widgets()`) n'est pas câblé — aucun
 * mécanisme générique manifest → classes n'existe encore dans ce code
 * base, indépendamment des widgets (voir suivi des différés).
 */
final class WidgetRegistry
{
    /** @var array<string, class-string<Widget>> */
    private array $widgets = [];

    /**
     * @param  class-string<Widget>  $widget
     */
    public function register(string $widget): void
    {
        $this->widgets[$widget::key()] = $widget;
    }

    public function has(string $key): bool
    {
        return isset($this->widgets[$key]);
    }

    public function resolve(string $key): Widget
    {
        if (! $this->has($key)) {
            throw UnknownWidgetException::forKey($key);
        }

        return app($this->widgets[$key]);
    }

    /**
     * @return array<string, class-string<Widget>>
     */
    public function all(): array
    {
        return $this->widgets;
    }
}
