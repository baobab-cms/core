<?php

declare(strict_types=1);

namespace Baobab\Branding\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Branding\Exceptions\UnknownDesignTokenException;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Support\BrandProfileRegistry;
use Baobab\Branding\Support\DesignTokenSchema;
use Baobab\Branding\Support\ResolveDesignTokens;
use Baobab\Facades\Hook;

/**
 * Réinitialise **un** token de marque (spec 18 §8, « Réinitialisation : par
 * token »).
 *
 * **Ce que « retomber au niveau inférieur » veut dire ici, et pourquoi ce
 * n'est pas littéralement ce que §3.3 décrit.** La spec présente la cascade
 * avec un niveau 3 (profil) distinct du niveau 4 (surcharges admin), et en
 * déduit que réinitialiser revient à « supprimer la clé, elle retombe
 * naturellement sur le thème/profil ». L'implémentation livrée en Pass B du
 * point 8 a fusionné les deux niveaux dans une seule colonne `tokens` :
 * `ApplyBrandProfile` **copie** les valeurs du profil dedans (narrowing acté,
 * suivi n° 85). Supprimer la clé franchirait donc le profil au lieu d'y
 * retomber, et un site sous profil « Elegant » perdrait silencieusement la
 * valeur d'Elegant en réinitialisant une couleur.
 *
 * Cette Action tient donc la **promesse** de §3.3 plutôt que sa lettre
 * (comportement confirmé par l'utilisateur le 14 août 2026, « la
 * réinitialisation tombe sur le profil sélectionné, on ne tombe sur le Core
 * qu'en cas de nécessité absolue ») :
 *
 * 1. un profil est appliqué et définit ce token → sa valeur est **restaurée** ;
 * 2. sinon → la clé est **supprimée**, et `ResolveDesignTokens` reprend sa
 *    cascade normale : `theme.json` du thème actif s'il le définit, défauts
 *    Core sinon. Le Core n'est donc atteint que si rien d'autre ne répond.
 *
 * Aucun changement de stockage n'est nécessaire : `brand_profile` retient
 * déjà le slug et `BrandProfileRegistry` sait relire la valeur — c'est le
 * mécanisme qui fait déjà fonctionner le badge « modifié » de l'écran.
 */
final class ResetBrandingToken
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BrandProfileRegistry $profiles,
        private readonly CompileDesignTokens $compile,
        private readonly ResolveDesignTokens $resolve,
    ) {}

    public function __invoke(string $group, string $key): BrandingSetting
    {
        if (! in_array($key, DesignTokenSchema::GROUPS[$group] ?? [], true)) {
            throw UnknownDesignTokenException::named($group, $key);
        }

        $setting = BrandingSetting::current();
        $tokens = $setting->tokens ?? [];

        $restored = $this->profileValue($setting->brand_profile, $group, $key);

        if ($restored !== null) {
            $tokens[$group][$key] = $restored;
        } else {
            unset($tokens[$group][$key]);

            if (($tokens[$group] ?? null) === []) {
                unset($tokens[$group]);
            }
        }

        $setting->tokens = $tokens;
        $setting->save();

        // `primary_color` est l'alias historique lu par les e-mails, qui ne
        // savent pas résoudre une variable CSS (spec 18 §2.4) : il suit la
        // couleur primaire partout où elle change, ici comme dans
        // `UpdateBrandingSettings`. Valeur **résolue** et non défaut Core —
        // un thème actif qui définit sa primaire doit la voir appliquée là
        // aussi. Résolue après l'écriture, la cascade lisant la ligne
        // qu'on vient d'enregistrer.
        if ($group === 'colors' && $key === 'primary') {
            $setting->primary_color = ($this->resolve)()['colors']['primary'];
            $setting->save();
        }

        $this->audit->record('branding.token_reset', $setting, [
            'group' => $group,
            'key' => $key,
            'restored_from' => $restored !== null ? 'profile' : 'theme_or_core',
        ]);

        Hook::action('baobab.branding.tokens.saved', $setting);

        ($this->compile)();

        return $setting;
    }

    private function profileValue(?string $slug, string $group, string $key): ?string
    {
        if ($slug === null) {
            return null;
        }

        return $this->profiles->load($slug)[$group][$key] ?? null;
    }
}
