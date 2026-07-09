<?php

declare(strict_types=1);

namespace Baobab\Tests\Fixtures;

final class DemoHookListenerStub
{
    public bool $called = false;

    public function __invoke(): void
    {
        $this->called = true;
    }
}
