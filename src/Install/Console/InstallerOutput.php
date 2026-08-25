<?php

declare(strict_types=1);

namespace Baobab\Install\Console;

use Illuminate\Console\OutputStyle;

/**
 * La surface console de l'installateur (spec 15 §5, suivi n° 211).
 *
 * « Je ne veux pas quelque chose d'ennuyant » — et la réponse n'est pas un
 * spinner. La direction visuelle prescrit pour toute opération longue une
 * **progression réelle par étape**, en interdisant de la remplacer par une
 * barre abstraite : on regarde Baobab faire, pas un décor tourner.
 *
 * **Muet en mode non interactif**, sans exception. Ce mode sert la CI et le
 * provisioning d'agence, où l'ornement est du bruit dans un journal.
 *
 * Les couleurs passent par les balises de Symfony plutôt que par des
 * séquences ANSI écrites à la main : c'est lui qui décide de décorer ou non,
 * et il les retire tout seul derrière `--no-ansi`, un tube ou un terminal
 * sans couleur. Une séquence écrite à la main s'afficherait en clair.
 */
final class InstallerOutput
{
    /** Vert de console, et non le vert de marque — voir le docblock de `banner()`. */
    private const GREEN = '#1E9079';

    private const GOLD = '#E9A13B';

    private const MUTED = 'gray';

    private ?string $current = null;

    public function __construct(
        private readonly OutputStyle $output,
        private readonly bool $quiet = false,
    ) {}

    public function title(string $version): void
    {
        if ($this->quiet) {
            return;
        }

        $this->output->newLine();
        $this->output->writeln('  <fg='.self::GREEN.';options=bold>Baobab CMS '.$version.'</> <fg='.self::MUTED.'>— installation</>');
        $this->output->newLine();
    }

    /**
     * Une étape commence. Rien n'est écrit tant qu'elle n'a pas rendu son
     * verdict : une ligne qui s'affiche puis se réécrit sur elle-même ne
     * survit pas à une sortie redirigée dans un fichier, où le retour chariot
     * laisse les deux versions bout à bout.
     */
    public function step(string $label): void
    {
        $this->current = $label;
    }

    /**
     * L'étape est passée. Le détail est ce qui rend la progression réelle :
     * une base, un nombre de migrations, un e-mail — jamais un pourcentage
     * calculé sur rien.
     */
    public function done(?string $detail = null): void
    {
        if ($this->quiet || $this->current === null) {
            return;
        }

        $this->output->writeln(sprintf(
            '   <fg=%s>OK</>   <options=bold>%-16s</> <fg=%s>%s</>',
            self::GREEN,
            $this->current,
            self::MUTED,
            $detail ?? '',
        ));

        $this->current = null;
    }

    /**
     * Le mot de passe forgé, seul moment de sa vie où l'afficher a un sens :
     * il n'est stocké nulle part en clair et ne sera plus jamais relisible.
     *
     * L'or de marque est employé ici et nulle part ailleurs dans cet écran —
     * la discipline d'accent veut qu'il désigne une chose à la fois, et c'est
     * celle-ci que l'utilisateur doit recopier avant de fermer son terminal.
     */
    public function generatedPassword(string $password): void
    {
        if ($this->quiet) {
            return;
        }

        $this->output->writeln(
            '  <fg='.self::MUTED.'>Mot de passe généré :</> <fg='.self::GOLD.';options=bold>'.$password.'</>'
            .' <fg='.self::MUTED.'>(non récupérable)</>'
        );
    }

    /**
     * La bannière, à la **fin** et jamais au début (suivi n° 211).
     *
     * Une enseigne triomphale affichée avant la vérification des prérequis se
     * ferait suivre d'un échec sur un PHP trop ancien — le pire moment pour
     * paraître content de soi.
     *
     * Le vert n'est pas celui de la marque, et c'est mesuré : `#1E7A54` ne
     * rend que 3,70:1 sur un fond de terminal noir, sous le seuil AA, parce
     * qu'il a été calibré pour une administration **claire**. `#1E9079` est le
     * même vert tiré vers le teal et éclairci — 4,96:1. Adapter une teinte à
     * un fond inversé n'est pas trahir la marque (suivi n° 209, n° 211).
     */
    public function banner(): void
    {
        if ($this->quiet) {
            return;
        }

        $this->output->newLine();

        foreach (self::art() as $line) {
            // L espace avant la fermeture n est pas cosmétique : plusieurs
            // lignes du dessin se terminent par un antislash, et Symfony lit
            // `\<` comme un chevron échappé. Collée, la balise ne se ferme
            // jamais et s imprime en clair — visible en recette.
            $this->output->writeln('  <fg='.self::GREEN.'>'.$line.' </>');
        }

        $this->output->newLine();
    }

    public function line(string $text): void
    {
        if ($this->quiet) {
            return;
        }

        $this->output->writeln($text);
    }

    /**
     * ASCII pur, 60 colonnes, 8 lignes — sous le plafond de 80 du §5.
     *
     * Remplissage en `#` : le logo est un appareillage de maçonnerie, et un
     * mot bâti en blocs pleins en est la transposition directe. Aucun arbre
     * n'est dessiné, l'interdit d'illustration végétale s'appliquant ici comme
     * ailleurs.
     *
     * Nowdoc et non tableau de chaînes : les antislashs de fin de ligne y sont
     * littéraux, sans échappement à maintenir à chaque retouche du dessin.
     *
     * @return list<string>
     */
    private static function art(): array
    {
        return explode("\n", <<<'ART'
#######\   ######\   ######\  #######\   ######\  #######\
##  __##\ ##  __##\ ##  __##\ ##  __##\ ##  __##\ ##  __##\
## |  ## |## /  ## |## /  ## |## |  ## |## /  ## |## |  ## |
#######\ |######## |## |  ## |#######\ |######## |#######\ |
##  __##\ ##  __## |## |  ## |##  __##\ ##  __## |##  __##\
## |  ## |## |  ## |## |  ## |## |  ## |## |  ## |## |  ## |
#######  |## |  ## | ######  |#######  |## |  ## |#######  |
\_______/ \__|  \__| \______/ \_______/ \__|  \__|\_______/
ART);
    }
}
