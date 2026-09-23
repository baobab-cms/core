<?php

declare(strict_types=1);

namespace Baobab\Privacy\Cookies;

/**
 * Résultat de `BuildCookieRegister` : l'ensemble des cookies déclarés
 * (Core + modules actifs), et ce que la bannière en tire.
 */
final readonly class CookieRegister
{
    /**
     * @param  list<CookieDeclaration>  $cookies
     */
    public function __construct(public array $cookies) {}

    /**
     * Catégories soumises au consentement effectivement déclarées, dans
     * l'ordre du vocabulaire. Vide : aucune bannière (spec 16 décision 21).
     *
     * @return list<CookieCategory>
     */
    public function consentCategories(): array
    {
        $declared = array_map(static fn (CookieDeclaration $cookie): CookieCategory => $cookie->category, $this->cookies);

        return array_values(array_filter(
            CookieCategory::cases(),
            static fn (CookieCategory $category): bool => $category->requiresConsent() && in_array($category, $declared, true),
        ));
    }

    public function requiresConsent(): bool
    {
        return $this->consentCategories() !== [];
    }

    /**
     * Empreinte des catégories soumises au consentement : stockée avec le
     * choix du visiteur, elle déclenche la re-sollicitation quand l'ensemble
     * change (spec 16 §3.2). Un cookie de plus dans une catégorie déjà
     * déclarée ne la change pas — la spec dit « catégories », pas « cookies ».
     */
    public function fingerprint(): string
    {
        $keys = array_map(static fn (CookieCategory $category): string => $category->value, $this->consentCategories());

        return substr(hash('sha256', implode(',', $keys)), 0, 12);
    }

    /**
     * @return list<CookieDeclaration>
     */
    public function inCategory(CookieCategory $category): array
    {
        return array_values(array_filter(
            $this->cookies,
            static fn (CookieDeclaration $cookie): bool => $cookie->category === $category,
        ));
    }
}
