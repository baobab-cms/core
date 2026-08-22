<?php

declare(strict_types=1);

namespace Baobab\Mail\Support;

/**
 * Le contrat de variables d'un template d'e-mail (spec 13 §3.1), normalisé une
 * fois : le manifeste accepte indifféremment la forme courte (`"nom": "libellé"`)
 * et la forme longue (`"nom": {"label": …, "required": true}`), et personne
 * d'autre n'a à connaître cette dualité.
 *
 * Support partagé — patron `BlueprintPermissions`/`BlueprintFields` (suivi
 * n° 102, n° 104) : `MailTemplateValidator` s'en sert à l'installation pour
 * vérifier les défauts du code, `SaveMailTemplate` à l'enregistrement pour
 * vérifier ce qu'écrit l'admin, et l'écran (Pass A2) pour peupler son menu
 * d'insertion. Trois lecteurs, une seule définition de ce qu'est « la variable
 * `user.name`, requise, intitulée Nom du destinataire ».
 */
final readonly class MailTemplateVariables
{
    /**
     * @param  array<string, string|array{label: string, required?: bool}>  $declaration
     */
    public function __construct(private array $declaration) {}

    /**
     * Nom → libellé affichable, dans l'ordre de déclaration du manifeste.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        $labels = [];

        foreach ($this->declaration as $name => $definition) {
            $labels[$name] = is_array($definition) ? $definition['label'] : $definition;
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->declaration);
    }

    /**
     * @return list<string>
     */
    public function required(): array
    {
        $required = [];

        foreach ($this->declaration as $name => $definition) {
            if (is_array($definition) && ($definition['required'] ?? false)) {
                $required[] = $name;
            }
        }

        return $required;
    }

    /**
     * Variables `required` absentes des placeholders trouvés — la validation
     * bloquante de la spec 13 §3.3 et §7 décision 3.
     *
     * @param  list<string>  $placeholders
     * @return list<string>
     */
    public function missingRequiredIn(array $placeholders): array
    {
        return array_values(array_diff($this->required(), $placeholders));
    }

    /**
     * Placeholders employés mais jamais déclarés — signalés à l'enregistrement
     * (§3.3), jamais à l'installation : un défaut livré par le code n'est pas
     * du texte saisi, et le rendu les traite déjà en warning technique.
     *
     * @param  list<string>  $placeholders
     * @return list<string>
     */
    public function unknownIn(array $placeholders): array
    {
        return array_values(array_diff($placeholders, $this->names()));
    }
}
