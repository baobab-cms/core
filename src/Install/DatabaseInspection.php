<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Ce que l'étape 2 a constaté de la base (spec 15 §4).
 *
 * Une base occupée n'est pas une erreur : c'est le cas courant du mutualisé,
 * qui n'en donne souvent qu'une. L'installateur avertit et **propose un
 * préfixe**, il ne refuse pas (suivi n° 214).
 */
final readonly class DatabaseInspection
{
    /**
     * @param  list<string>  $tables
     */
    public function __construct(
        public array $tables,
        public string $prefix = '',
    ) {}

    public function isEmpty(): bool
    {
        return $this->tables === [];
    }

    /**
     * Les tables qui gêneraient réellement, préfixe courant appliqué.
     *
     * Avec un préfixe, des tables voisines ne sont plus un conflit : elles
     * cohabitent. C'est tout l'intérêt de la proposition — et c'est pourquoi
     * ce calcul ne peut pas se contenter de compter les tables.
     *
     * @return list<string>
     */
    public function conflicts(): array
    {
        return array_values(array_filter(
            $this->tables,
            fn (string $table): bool => $this->prefix === ''
                ? true
                : str_starts_with($table, $this->prefix),
        ));
    }

    /**
     * Un préfixe libre, **tiré au sort et non dérivé du nom du site**.
     *
     * Le nom d'un site change au moins une fois dans sa vie ; les noms de
     * tables, jamais. Un préfixe `mon_site_` survivrait donc à « Mon Site » et
     * désignerait un site qui ne s'appelle plus ainsi — c'est-à-dire qu'il
     * mentirait, ce qui est pire qu'un préfixe muet. Trois lettres tirées au
     * sort ne prétendent rien, et restent justes indéfiniment.
     *
     * Des lettres seules, jamais de chiffre en tête : un identifiant SQL
     * commençant par un chiffre exige des guillemets partout, et rien dans le
     * Core n'en met.
     *
     * **Un préfixe déjà écrit est rendu tel quel**, et c'est le point le plus
     * important ici : une installation reprise après coupure (§4) doit
     * retrouver *le sien*. En tirer un second créerait un deuxième jeu de
     * tables à côté du premier, sans que rien ne le signale.
     */
    public function suggestPrefix(?string $existing = null): string
    {
        $existing = trim((string) $existing);

        if ($existing !== '') {
            return $existing;
        }

        do {
            $candidate = '';

            for ($i = 0; $i < 3; $i++) {
                $candidate .= chr(random_int(97, 122));
            }

            $candidate .= '_';
        } while ($this->hasTableStartingWith($candidate));

        return $candidate;
    }

    private function hasTableStartingWith(string $prefix): bool
    {
        foreach ($this->tables as $table) {
            if (str_starts_with($table, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
