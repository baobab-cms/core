<?php

declare(strict_types=1);

namespace Baobab\Privacy\Cookies;

/**
 * Catégories de consentement (spec 16 §3.2) — vocabulaire fermé, repris tel
 * quel par le schéma du manifeste (`privacy.cookies[].category`) et par le
 * script `consent.js`. `necessary` n'appelle jamais de consentement : il est
 * affiché, jamais proposé au choix.
 */
enum CookieCategory: string
{
    case Necessary = 'necessary';
    case Functional = 'functional';
    case Analytics = 'analytics';
    case Marketing = 'marketing';

    public function requiresConsent(): bool
    {
        return $this !== self::Necessary;
    }

    public function label(): string
    {
        return __("baobab::privacy.cookies.categories.{$this->value}.label");
    }

    public function description(): string
    {
        return __("baobab::privacy.cookies.categories.{$this->value}.description");
    }
}
