<?php

declare(strict_types=1);

namespace Baobab\Admin\Branding\Http\Controllers;

use Baobab\Branding\Actions\UpdateBrandingSettings;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Support\DesignTokenSchema;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;

/**
 * Écran de réglages de marque (spec-admin.md §11.1, étendu spec 18 §8, Pass A) :
 * logo, favicon admin, couleur primaire, et — nouveau — les groupes Couleurs
 * et Typographie du vocabulaire de design tokens. Accès gouverné par la
 * permission `baobab.system.branding.manage` au niveau de la route
 * (routes/admin.php).
 */
final class BrandingController
{
    public function index(): View
    {
        $setting = BrandingSetting::current()->load(['logo', 'favicon']);

        return view('baobab::admin.branding.index', [
            'setting' => $setting,
            'colors' => array_merge(DesignTokenSchema::CORE_DEFAULTS['colors'], $setting->tokens['colors'] ?? []),
            'fonts' => array_merge(DesignTokenSchema::CORE_DEFAULTS['fonts'], $setting->tokens['fonts'] ?? []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $colorKeys = DesignTokenSchema::GROUPS['colors'];
        $fontKeys = DesignTokenSchema::GROUPS['fonts'];

        $rules = [
            'logo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'favicon_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tokens' => ['nullable', 'array'],
            'tokens.colors' => ['nullable', 'array'],
            'tokens.fonts' => ['nullable', 'array'],
        ];

        foreach ($colorKeys as $key) {
            $rules["tokens.colors.{$key}"] = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        foreach ($fontKeys as $key) {
            $rules["tokens.fonts.{$key}"] = ['nullable', 'string', 'max:255'];
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->after(fn (ValidatorContract $validator) => $this->rejectUnknownTokens($validator, $request));

        $validated = $validator->validate();

        app(UpdateBrandingSettings::class)($validated);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.branding.updated')]);

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
