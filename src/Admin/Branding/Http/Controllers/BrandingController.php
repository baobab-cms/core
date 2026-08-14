<?php

declare(strict_types=1);

namespace Baobab\Admin\Branding\Http\Controllers;

use Baobab\Branding\Actions\ApplyBrandProfile;
use Baobab\Branding\Actions\ResetBrandingToken;
use Baobab\Branding\Actions\ResetBrandingTokens;
use Baobab\Branding\Actions\UpdateBrandingSettings;
use Baobab\Branding\Exceptions\UnknownBrandProfileException;
use Baobab\Branding\Exceptions\UnknownDesignTokenException;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Models\Font;
use Baobab\Branding\Support\BrandProfileRegistry;
use Baobab\Branding\Support\DesignTokenSchema;
use Baobab\Branding\Support\ResolveDesignTokens;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;

/**
 * Écran de réglages de marque (spec-admin.md §11.1, étendu spec 18 §8).
 * Pass A couvrait 2 groupes sur 8 (Couleurs, Typographie-familles) ; Pass B
 * complète les 8 groupes du vocabulaire (Typographie-échelle, Surfaces) et
 * ajoute Profil (§7.2) et Polices (§5, section affichée seulement si l'acteur
 * a `baobab.system.fonts.manage` — les mutations elles-mêmes vivent dans
 * `FontsController`, sous cette permission distincte).
 */
final class BrandingController
{
    public function index(Request $request, BrandProfileRegistry $registry, ResolveDesignTokens $resolver): View
    {
        $setting = BrandingSetting::current()->load(['logo', 'favicon']);

        $groups = [];

        foreach (DesignTokenSchema::GROUPS as $group => $keys) {
            $groups[$group] = array_merge(DesignTokenSchema::CORE_DEFAULTS[$group], $setting->tokens[$group] ?? []);
        }

        $profiles = $registry->all();
        $cards = $this->tokenCards($groups, $setting, $registry, $resolver);
        $profileModified = $setting->brand_profile !== null
            && ($setting->tokens ?? []) !== $registry->load($setting->brand_profile);

        $canManageFonts = (bool) $request->user()?->can('baobab.system.fonts.manage');
        $registeredFonts = $canManageFonts ? Font::query()->orderBy('family')->get() : null;

        $fontUsage = [];

        foreach ($registeredFonts ?? [] as $font) {
            foreach ($groups['fonts'] as $value) {
                if (str_contains($value, $font->family)) {
                    $fontUsage[$font->id] = true;

                    break;
                }
            }
        }

        return view('baobab::admin.branding.index', [
            'setting' => $setting,
            'colors' => $groups['colors'],
            'fonts' => $groups['fonts'],
            'text' => $groups['text'],
            'leading' => $groups['leading'],
            'weight' => $groups['weight'],
            'radius' => $groups['radius'],
            'spacing' => $groups['spacing'],
            'shadow' => $groups['shadow'],
            'profiles' => $profiles,
            'profileSwatches' => $this->profileSwatches($profiles),
            'profileOptions' => $this->profileOptions($profiles, $setting->brand_profile),
            'cards' => $cards,
            'currentProfile' => $setting->brand_profile,
            'profileModified' => $profileModified,
            'canManageFonts' => $canManageFonts,
            'registeredFonts' => $registeredFonts,
            'fontUsage' => $fontUsage,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'logo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'favicon_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tokens' => ['nullable', 'array'],
        ];

        foreach (DesignTokenSchema::GROUPS as $group => $keys) {
            $rules["tokens.{$group}"] = ['nullable', 'array'];

            foreach ($keys as $key) {
                $rules["tokens.{$group}.{$key}"] = $group === 'colors'
                    ? ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']
                    : ['nullable', 'string', 'max:255'];
            }
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->after(fn (ValidatorContract $validator) => $this->rejectUnknownTokens($validator, $request));

        $validated = $validator->validate();

        app(UpdateBrandingSettings::class)($validated);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.branding.updated')]);

        return redirect()->route('admin.branding.index');
    }

    public function applyProfile(Request $request, ApplyBrandProfile $action): RedirectResponse
    {
        $validated = $request->validate([
            'profile' => ['required', 'string'],
        ]);

        try {
            $action($validated['profile']);
        } catch (UnknownBrandProfileException) {
            session()->flash('toast', ['type' => 'danger', 'message' => __('baobab::admin.branding.unknown_profile')]);

            return redirect()->route('admin.branding.index');
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.branding.profile_applied')]);

        return redirect()->route('admin.branding.index');
    }

    /**
     * Réinitialisation d'un token (spec 18 §8). Le groupe et la clé viennent
     * du formulaire, donc du dehors : l'Action les revalide contre le
     * vocabulaire et lève une exception typée, rattrapée ici pour être
     * traduite — jamais servie brute à l'utilisateur (patron n° 113).
     */
    /**
     * Une carte par token : sa valeur courante, la valeur sur laquelle il
     * retomberait, et s'il est **surchargé** (spec 18 §8, suivi n° 149).
     *
     * La référence est celle que `ResetBrandingToken` restaurerait — profil
     * appliqué s'il définit le token, sinon la cascade sans surcharges admin
     * (thème actif, puis défauts Core), lue par `ResolveDesignTokens::
     * baseline()`. Une seule définition du « niveau inférieur » pour l'Action
     * et pour l'écran : sans quoi la carte pourrait annoncer une
     * réinitialisation qui ne rend pas la valeur promise.
     *
     * `overridden` gouverne deux choses à l'écran : le contour de la carte, et
     * l'affichage même du bouton « Réinitialiser », qui n'a rien à faire là
     * où il n'aurait aucun effet.
     *
     * @param  array<string, array<string, string>>  $groups
     * @return array<string, list<array{key: string, value: string, baseline: string, overridden: bool, reset: string}>>
     */
    private function tokenCards(
        array $groups,
        BrandingSetting $setting,
        BrandProfileRegistry $registry,
        ResolveDesignTokens $resolver,
    ): array {
        $baseline = $resolver->baseline();
        $profile = $setting->brand_profile === null ? [] : $registry->load($setting->brand_profile);

        $cards = [];

        foreach ($groups as $group => $values) {
            foreach ($values as $key => $value) {
                $reference = $profile[$group][$key] ?? $baseline[$group][$key] ?? $value;

                $cards[$group][] = [
                    'key' => $key,
                    'value' => $value,
                    'baseline' => $reference,
                    'overridden' => $value !== $reference,
                    'reset' => route('admin.branding.reset-token', ['group' => $group, 'key' => $key]),
                ];
            }
        }

        return $cards;
    }

    /**
     * Les options du menu de profils.
     *
     * **Une option vide en tête tant qu'aucun profil n'est appliqué**, et elle
     * n'est pas cosmétique : un `<select>` dont aucune option ne correspond à
     * la valeur courante affiche sa **première**, si bien que l'écran annonçait
     * « Corporate » sur un site dont `brand_profile` est `null` — un état qui
     * n'existait pas (relevé en vérification navigateur de la Pass A, suivi
     * n° 149). La valeur vide échoue la validation `required` du contrôleur :
     * appliquer un profil reste un choix explicite.
     *
     * @param  array<string, array{label: string, tokens: array<string, array<string, string>>}>  $profiles
     * @return array<string, string>
     */
    private function profileOptions(array $profiles, ?string $current): array
    {
        $options = [];

        if ($current === null) {
            $options[''] = __('baobab::admin.branding.profile_none');
        }

        foreach ($profiles as $slug => $profile) {
            $options[$slug] = $profile['label'];
        }

        return $options;
    }

    /**
     * Les couleurs représentatives de chaque profil, pour l'« aperçu des
     * pastilles » qu'exige la spec 18 §8 et que la section Profil n'avait
     * jamais rendu (suivi n° 149) — on choisissait un preset par son nom, sans
     * voir ce qu'il change.
     *
     * Quatre couleurs suffisent à reconnaître un profil, et ce sont celles que
     * l'œil lit en premier : primaire, secondaire, accent, fond. Résolues ici
     * plutôt que dans la vue, qui n'a pas à connaître le vocabulaire.
     *
     * @param  array<string, array{label: string, tokens: array<string, array<string, string>>}>  $profiles
     * @return array<string, list<string>>
     */
    private function profileSwatches(array $profiles): array
    {
        $swatches = [];

        foreach ($profiles as $slug => $profile) {
            $colors = $profile['tokens']['colors'] ?? [];

            $swatches[$slug] = array_values(array_filter([
                $colors['primary'] ?? null,
                $colors['secondary'] ?? null,
                $colors['accent'] ?? null,
                $colors['background'] ?? null,
            ]));
        }

        return $swatches;
    }

    public function resetToken(string $group, string $key, ResetBrandingToken $action): RedirectResponse
    {
        try {
            $action($group, $key);
        } catch (UnknownDesignTokenException) {
            session()->flash('toast', ['type' => 'danger', 'message' => __('baobab::admin.branding.unknown_token')]);

            return redirect()->route('admin.branding.index');
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.branding.token_reset')]);

        return redirect()->route('admin.branding.index');
    }

    public function resetTokens(ResetBrandingTokens $action): RedirectResponse
    {
        $action();

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.branding.tokens_reset')]);

        return redirect()->route('admin.branding.index');
    }

    /**
     * Vocabulaire fermé (spec 18 §2.3) : tout groupe ou clé hors
     * `DesignTokenSchema::GROUPS` — `dark` inclus — est rejeté avec un
     * message explicite plutôt que silencieusement ignoré.
     */
    private function rejectUnknownTokens(ValidatorContract $validator, Request $request): void
    {
        /** @var array<string, array<string, string>> $tokens */
        $tokens = (array) $request->input('tokens', []);

        foreach ($tokens as $group => $values) {
            if (! array_key_exists($group, DesignTokenSchema::GROUPS)) {
                $validator->errors()->add('tokens', __('baobab::admin.branding.unknown_token_group', ['group' => $group]));

                continue;
            }

            foreach (array_keys((array) $values) as $key) {
                if (! in_array($key, DesignTokenSchema::GROUPS[$group], true)) {
                    $validator->errors()->add('tokens', __('baobab::admin.branding.unknown_token_key', ['group' => $group, 'key' => $key]));
                }
            }
        }
    }
}
