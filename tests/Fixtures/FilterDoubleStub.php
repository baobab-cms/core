<?php

declare(strict_types=1);

namespace Baobab\Tests\Fixtures;

final class FilterDoubleStub
{
    public function __invoke(int $value): int
    {
        return $value * 2;
    }
}
