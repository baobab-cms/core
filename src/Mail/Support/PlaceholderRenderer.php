<?php

declare(strict_types=1);

namespace Baobab\Mail\Support;

use Baobab\Support\Logger;

/**
 * Langage de placeholders des templates d'e-mails (spec 13 §3.3) — volontairement
 * pauvre, jamais du Blade compilé depuis la base : `{{ chemin.vers.variable }}`
 * (notation pointée, échappée) et `{{# if chemin }} … {{/ if }}` (bloc
 * conditionnel sur la présence de la variable, pas d'imbrication, pas de
 * boucle, pas d'expression — la spec l'exclut explicitement). Une variable
 * absente des données de rendu est rendue vide et journalisée en warning
 * technique (spec 12 §9) — la validation « la variable est-elle déclarée
 * pour ce template » est du ressort de `MailTemplateValidator`, en amont.
 */
final class PlaceholderRenderer
{
    private const CONDITIONAL_PATTERN = '/\{\{#\s*if\s+([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\s*\}\}(.*?)\{\{\/\s*if\s*\}\}/s';

    private const INSERTION_PATTERN = '/\{\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\s*\}\}/';

    public function __construct(private readonly Logger $logger) {}

    /**
     * Chemins de placeholders `{{ path }}` présents dans un texte — réutilisé
     * par `MailTemplateValidator` pour vérifier qu'une variable `required`
     * apparaît bien dans le défaut d'un template (spec 13 §3.1, §3.3).
     *
     * @return list<string>
     */
    public function placeholdersIn(string $template): array
    {
        preg_match_all(self::INSERTION_PATTERN, $template, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Toutes les variables **citées** par un texte : insertions et sujets de
     * blocs conditionnels. Distinct de `placeholdersIn()` à dessein, et les
     * deux ont chacun leur usage dans la validation d'un template personnalisé
     * (spec 13 §3.3) : une variable `required` doit être réellement
     * *insérée* — la tester dans un `{{# if }}` ne garantit pas qu'elle
     * apparaisse — tandis qu'une variable *inconnue* est fautive où qu'elle
     * soit citée, y compris dans la condition d'un bloc.
     *
     * @return list<string>
     */
    public function variablesIn(string $template): array
    {
        preg_match_all(self::CONDITIONAL_PATTERN, $template, $conditionals);

        return array_values(array_unique([
            ...$this->placeholdersIn($template),
            ...$conditionals[1],
        ]));
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(string $template, array $variables): string
    {
        $withConditionals = preg_replace_callback(
            self::CONDITIONAL_PATTERN,
            fn (array $match): string => $this->resolve($match[1], $variables) ? $match[2] : '',
            $template,
        ) ?? $template;

        return preg_replace_callback(
            self::INSERTION_PATTERN,
            function (array $match) use ($variables): string {
                $value = $this->resolve($match[1], $variables);

                if ($value === null) {
                    $this->logger->warning('Placeholder e-mail inconnu.', ['path' => $match[1]]);

                    return '';
                }

                return e((string) $value);
            },
            $withConditionals,
        ) ?? $withConditionals;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function resolve(string $path, array $variables): mixed
    {
        $value = $variables;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
