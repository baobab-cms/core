<?php

declare(strict_types=1);

namespace Baobab\Widgets\Actions;

use Baobab\Widgets\Models\WidgetInstance;
use Illuminate\Support\Facades\Cache;

final class DeleteWidgetInstance
{
    public function __invoke(WidgetInstance $instance): void
    {
        Cache::forget("baobab.widget.{$instance->id}");

        $instance->delete();
    }
}
