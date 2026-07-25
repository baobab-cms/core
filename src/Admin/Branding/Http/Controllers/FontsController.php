<?php

declare(strict_types=1);

namespace Baobab\Admin\Branding\Http\Controllers;

use Baobab\Branding\Actions\DeleteFont;
use Baobab\Branding\Actions\UploadFont;
use Baobab\Branding\Exceptions\FontInUseException;
use Baobab\Branding\Exceptions\InvalidFontUploadException;
use Baobab\Branding\Models\Font;
use Baobab\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Mutations du registre de polices (spec 18 §5, §8) : accès gouverné par
 * `baobab.system.fonts.manage` au niveau de la route (routes/admin.php) —
 * distincte de `baobab.system.branding.manage` (« uploader un binaire servi
 * publiquement mérite une permission distincte », §8). La liste elle-même
 * est affichée par `BrandingController::index()`, dans le même écran.
 */
final class FontsController
{
    public function store(Request $request, UploadFont $action): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file'],
            'family' => ['required', 'string', 'max:255'],
            'license' => ['nullable', 'string', 'max:255'],
            'license_attested' => ['required', 'accepted'],
            'license_file' => ['nullable', 'file'],
        ]);

        try {
            $action(
                $request->file('file'),
                $validated['family'],
                $validated['license'] ?? null,
                (bool) $validated['license_attested'],
                $this->actor(),
                $request->file('license_file'),
            );
        } catch (InvalidFontUploadException $e) {
            session()->flash('toast', ['type' => 'danger', 'message' => $e->getMessage()]);

            return redirect()->route('admin.branding.index');
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.branding.fonts.uploaded')]);

        return redirect()->route('admin.branding.index');
    }

    public function destroy(Font $font, DeleteFont $action): RedirectResponse
    {
        try {
            $action($font);
        } catch (FontInUseException|RuntimeException $e) {
            session()->flash('toast', ['type' => 'danger', 'message' => $e->getMessage()]);

            return redirect()->route('admin.branding.index');
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.branding.fonts.deleted')]);

        return redirect()->route('admin.branding.index');
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
