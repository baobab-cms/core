<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleDiscovery;
use Baobab\Themes\Actions\ActivateTheme;
use Throwable;

/**
 * Étape 7 de la séquence (spec 15 §4) — installe et active le thème livré
 * avec la distribution.
 *
 * Sans elle, une installation réussie débouche sur les pages de repli du Core
 * et la bande admin annonce « aucun thème actif ». C'était la raison déclarée
 * de faire passer le M8 point 10 avant le point 3, et rien ne l'honorait :
 * le mot « thème » n'apparaissait nulle part dans l'installateur (suivi
 * n° 247).
 *
 * **Le Core ne nomme jamais un thème en dur.** La dépendance du projet est
 * descendante — thèmes → modules → core — donc le nom vit dans la
 * configuration, jamais dans un `use`. Le défaut désigne le thème que la
 * distribution embarque ; une distribution qui en livre un autre change la
 * clé sans toucher au Core.
 *
 * **Elle ne fait jamais échouer une installation.** Les pages de repli du
 * Core existent précisément pour l'état « aucun thème actif » (spec 19 §5) :
 * perdre une installation par ailleurs valide parce qu'un thème refuse de
 * s'installer serait échanger un site utilisable contre rien. Ce qui n'a pas
 * pu se faire est **dit**, jamais avalé.
 */
final class ActivateDefaultTheme
{
    public function __construct(
        private readonly ModuleDiscovery $discovery,
        private readonly InstallModule $install,
        private readonly ActivateTheme $activate,
    ) {}

    /**
     * @return list<string> lignes affichables, destinées au `StepOutcome`
     */
    public function __invoke(?string $name = null): array
    {
        $active = Module::where('type', 'theme')->where('status', 'active')->first();

        // Une reprise ne réactive pas : l'étape est notée dans l'avancement,
        // mais un thème déjà actif peut aussi venir d'une installation
        // antérieure ou d'un choix fait à la main. On ne défait pas ce choix.
        if ($active instanceof Module) {
            return ['Thème déjà actif : '.$active->title.'.'];
        }

        $name ??= (string) config('baobab.install.default_theme', 'baobab/default-theme');

        if ($this->discovery->scan()->get($name) === null) {
            return [
                'Aucun thème '.$name.' dans cette distribution.',
                'Le site rendra les pages de repli du Core jusqu\'à l\'activation d\'un thème.',
            ];
        }

        try {
            if (Module::where('name', $name)->first() === null) {
                ($this->install)($name);
            }

            $theme = ($this->activate)($name);
        } catch (Throwable $e) {
            return [
                'Le thème '.$name.' n\'a pas pu être activé : '.$e->getMessage(),
                'L\'installation se poursuit ; le site rendra les pages de repli du Core.',
            ];
        }

        return ['Thème activé : '.$theme->title.'.'];
    }
}
