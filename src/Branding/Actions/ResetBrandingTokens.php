<?php

declare(strict_types=1);

namespace Baobab\Branding\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Support\ResolveDesignTokens;
use Baobab\Facades\Hook;

/**
 * Réinitialisation **globale** des tokens de marque (spec 18 §8,
 * « Réinitialisation : […] globale (vider les niveaux 3–4) »).
 *
 * Contrairement à la réinitialisation par token (`ResetBrandingToken`), celle-ci
 * n'a aucune ambiguïté de cascade : les niveaux 3 et 4 vivent tous deux dans la
 * colonne `tokens`, et le profil retenu dans `brand_profile` — vider les deux
 * *est* exactement « vider les niveaux 3–4 ». Le site retombe alors sur le
 * `theme.json` du thème actif là où il définit un token, sur les défauts Core
 * partout ailleurs.
 *
 * L'identité (logo, favicon) n'est **pas** touchée : la spec 18 §8 la range
 * hors de la cascade de tokens (« Logo/favicon restent gérés comme
 * aujourd'hui, non concernés par la cascade »), et c'est aussi ce qu'attend
 * quelqu'un qui clique « réinitialiser les couleurs » — pas perdre son logo.
 */
final class ResetBrandingTokens
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CompileDesignTokens $compile,
        private readonly ResolveDesignTokens $resolve,
    ) {}

    public function __invoke(): BrandingSetting
    {
        $setting = BrandingSetting::current();

        $setting->tokens = [];
        $setting->brand_profile = null;
        $setting->save();

        // `primary_color` est l'alias historique lu par les e-mails, qui ne
        // savent pas résoudre une variable CSS (spec 18 §2.4). Il prend la
        // valeur **résolue** et non le défaut Core : un thème actif qui
        // définit sa propre primaire doit la voir appliquée là aussi, sans
        // quoi les e-mails divergeraient du site. Résolu après l'écriture,
        // puisque la cascade lit la ligne qu'on vient d'enregistrer.
        $setting->primary_color = ($this->resolve)()['colors']['primary'];
        $setting->save();

        $this->audit->record('branding.tokens_reset', $setting, []);

        Hook::action('baobab.branding.tokens.saved', $setting);

        ($this->compile)();

        return $setting;
    }
}
