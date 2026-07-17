<?php

declare(strict_types=1);

namespace Baobab\Tests\Fixtures;

use Baobab\Widgets\Models\WidgetInstance;
use Baobab\Widgets\Widget;
use RuntimeException;

final class ThrowingWidgetStub extends Widget
{
    public static function key(): string
    {
        return 'test.throwing';
    }

    public static function label(): string
    {
        return 'Throwing (test)';
    }

    public function settingsSchema(): array
    {
        return [];
    }

    public function data(WidgetInstance $instance): array
    {
        throw new RuntimeException('boom');
    }

    public function view(): string
    {
        return 'baobab::widgets.error';
    }

    public function cacheTtl(): ?int
    {
        return null;
    }
}
