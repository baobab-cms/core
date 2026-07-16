<?php

declare(strict_types=1);

namespace Baobab\Notify;

/**
 * Déclaration résolue d'une notification (spec 11 §6) — Core ou module.
 */
final readonly class NotificationDeclaration
{
    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public string $key,
        public ?string $description,
        public array $channels,
        public ?string $mailTemplate,
        public bool $configurable,
    ) {}
}
