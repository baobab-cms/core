<?php

declare(strict_types=1);

namespace Baobab\Admin\Sidebar;

final class SidebarItem
{
    /** @param list<self> $children */
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly ?string $icon,
        public readonly ?string $url,
        public readonly int $order,
        public readonly array $children = [],
        public readonly bool $isActive = false,
    ) {}
}
