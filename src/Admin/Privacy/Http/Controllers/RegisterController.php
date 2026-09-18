<?php

declare(strict_types=1);

namespace Baobab\Admin\Privacy\Http\Controllers;

use Baobab\Privacy\Actions\BuildProcessingRegister;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Écran `admin/privacy/register` (spec 16 §2.2) : le registre des traitements
 * généré depuis les déclarations des fournisseurs, consultable et exportable
 * en document HTML imprimable. Adaptateur mince, patron `HealthController` :
 * aucune autorisation ici, tout au middleware `can:` des routes.
 */
final class RegisterController
{
    public function index(BuildProcessingRegister $build): View
    {
        return view('baobab::admin.privacy.register.index', ['register' => $build()]);
    }

    public function export(BuildProcessingRegister $build): Response
    {
        $html = view('baobab::admin.privacy.register.document', [
            'register' => $build(),
            'generatedAt' => now(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="registre-des-traitements-'.now()->format('Y-m-d').'.html"',
        ]);
    }
}
