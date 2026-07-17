<?php

declare(strict_types=1);

namespace Baobab\Widgets;

use Baobab\Widgets\Models\WidgetInstance;

/**
 * Contrat d'un widget (spec 10 §3.1). `settingsSchema()` retourne le même
 * format de tableau blueprint que les champs de Content Type
 * (`['key' => ..., 'type' => ..., ...]`, spec 02 §3) — pas le fluent
 * builder `Field::` illustré par la spec, qui n'existe pas dans ce code
 * base. `data()` porte la requête ; la vue (`view()`) ne fait qu'afficher
 * (spec 03 §3, même séparation que les thèmes).
 */
abstract class Widget
{
    abstract public static function key(): string;

    abstract public static function label(): string;

    /**
     * @return list<array<string, mixed>>
     */
    abstract public function settingsSchema(): array;

    /**
     * @return array<string, mixed>
     */
    abstract public function data(WidgetInstance $instance): array;

    abstract public function view(): string;

    /**
     * TTL en secondes du cache de `data()`, ou `null` pour ne jamais
     * mettre en cache (spec 03.4).
     */
    abstract public function cacheTtl(): ?int;
}
