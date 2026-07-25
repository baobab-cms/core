<?php

declare(strict_types=1);

namespace Baobab\Admin\Branding\Http\Controllers;

use Baobab\Branding\Actions\ApplyBrandProfile;
use Baobab\Branding\Actions\UpdateBrandingSettings;
use Baobab\Branding\Exceptions\UnknownBrandProfileException;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Models\Font;
use Baobab\Branding\Support\BrandProfileRegistry;
use Baobab\Branding\Support\DesignTokenSchema;
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
    public function index(Request $request, BrandProfileRegistry $registry): View
    {
        $setting = BrandingSetting::current()->load(['logo', 'favicon']);

        $groups = [];

        foreach (DesignTokenSchema::GROUPS as $group => $keys) {
            $groups[$group] = array_merge(DesignTokenSchema::CORE_DEFAULTS[$group], $setting->tokens[$group] ?? []);
        }

        $profiles = $registry->all();
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
