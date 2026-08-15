<?php

declare(strict_types=1);

namespace Baobab\Admin\Modules\Http\Controllers;

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\DeactivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Actions\Modules\UninstallModule;
use Baobab\Actions\Modules\UpdateModule;
use Baobab\Actions\Modules\UploadModuleArchive;
use Baobab\Mail\Exceptions\InvalidMailTemplateException;
use Baobab\Modules\Exceptions\IncompatibleModuleException;
use Baobab\Modules\Exceptions\InvalidManifestException;
use Baobab\Modules\Exceptions\InvalidModuleArchiveException;
use Baobab\Modules\Exceptions\ModuleDependencyCycleException;
use Baobab\Modules\Exceptions\ModuleHasActiveDependentsException;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Exceptions\ModuleStillActiveException;
use Baobab\Modules\Exceptions\PermissionRemovalNotConfirmedException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleInventory;
use Baobab\Modules\ModuleUploadPaths;
use Baobab\Notify\Exceptions\InvalidNotificationException;
use Baobab\Themes\Validation\Exceptions\ThemeValidationFailedException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Écran « Modules » (spec-modules §3, M8 point 9, Pass A) — cycle de vie
 * générique en admin : installer, activer, désactiver, désinstaller. Ferme le
 * suivi n° 81 : les quatre Actions existent depuis M1 et n'avaient jusqu'ici
 * que la CLI pour client, ce qui excluait de fait toute persona non-terminal.
 *
 * Adaptateur mince au sens strict : aucune Action écrite ici, aucune règle
 * métier réimplémentée — dépendances, cycles, dépendants actifs et purge sont
 * décidés par la couche d'actions, l'écran ne fait que la déclencher et
 * afficher ce qu'elle répond.
 *
 * Accès gouverné par `baobab.system.modules.manage` (routes/admin.php).
 */
final class ModulesController
{
    public function index(Request $request, ModuleInventory $inventory): View
    {
        return view('baobab::admin.modules.index', [
            'modules' => $inventory->all(),
            // L'activation d'un thème n'est pas celle d'un module (un seul
            // thème actif, synchronisation des emplacements, publication des
            // assets : `ActivateTheme`). L'écran renvoie donc vers « Thèmes »
            // plutôt que d'offrir un bouton qui court-circuiterait la règle.
            'canManageThemes' => (bool) $request->user()?->can('baobab.system.themes.manage'),
        ]);
    }

    public function install(string $vendor, string $slug, InstallModule $action): RedirectResponse
    {
        return $this->run(fn () => $action("{$vendor}/{$slug}"), 'installed');
    }

    public function activate(string $vendor, string $slug, ActivateModule $action): RedirectResponse
    {
        return $this->run(fn () => $action("{$vendor}/{$slug}"), 'activated');
    }

    public function deactivate(string $vendor, string $slug, DeactivateModule $action): RedirectResponse
    {
        return $this->run(fn () => $action("{$vendor}/{$slug}"), 'deactivated');
    }

    public function uninstall(Request $request, string $vendor, string $slug, UninstallModule $action): RedirectResponse
    {
        // Le rollback des migrations détruit les données du module : la spec §3
        // exige une confirmation explicite, jamais un défaut implicite. Même
        // règle pour la suppression des fichiers, ajoutée en Pass B.
        $purge = $request->boolean('purge');
        $deleteFiles = $request->boolean('delete_files');

        return $this->run(
            fn () => $action("{$vendor}/{$slug}", $purge, $deleteFiles),
            $purge ? 'uninstalled_purged' : 'uninstalled',
        );
    }

    /**
     * Relit `module.json` et rafraîchit ce que le Core en avait retenu (suivi
     * n° 111, Pass B) — permissions, menus, hooks, widgets, tous capturés à
     * l'installation et jamais relus depuis.
     *
     * Le geste est offert pour **tout** module installé, pas seulement pour ceux
     * qu'un Studio a produits : un module mis à jour par Composer est exactement
     * aussi périmé, et c'est même le seul cas que ni le Studio ni sa CLI ne
     * couvrent.
     *
     * **Depuis le n° 156, l'écran déclenche `UpdateModule` et non
     * `SyncModuleManifest`** : les migrations en attente d'abord, la
     * resynchronisation ensuite. La spec §3 en fait deux étapes distinctes et
     * `module:sync` garde la seconde seule côté terminal, mais l'écran n'offre
     * que la composée (décision du 14 août 2026, avec l'utilisateur) —
     * resynchroniser sans migrer est un sous-ensemble strict, et personne ne veut
     * délibérément rafraîchir les permissions en laissant le schéma périmé. Un
     * module sans migration en attente reçoit exactement le traitement qu'avait
     * l'ancien bouton « Resynchroniser » : le geste ne se perd pas, il s'élargit.
     *
     * `force` couvre la seule opération irréversible du lot : retirer une
     * permission que le manifeste ne déclare plus révoque les droits déjà
     * accordés. Sans la case, l'Action refuse en les nommant — même parti pris
     * que la purge.
     */
    public function update(Request $request, string $vendor, string $slug, UpdateModule $action): RedirectResponse
    {
        $module = Module::where('name', "{$vendor}/{$slug}")->first();

        if (! $module instanceof Module) {
            session()->flash('toast', [
                'type' => 'error',
                'message' => ModuleNotFoundException::named("{$vendor}/{$slug}")->getMessage(),
            ]);

            return redirect()->route('admin.modules.index');
        }

        return $this->run(
            fn () => $action($module, $request->boolean('force')),
            'updated',
        );
    }

    /**
     * Dépose une archive `.zip` là où la découverte trouvera le module
     * (spec §2). N'installe pas : l'écran le montrera « Sur disque », avec son
     * bouton Installer, comme n'importe quel module déposé à la main.
     */
    public function upload(Request $request, UploadModuleArchive $action): RedirectResponse
    {
        $request->validate([
            // La taille réelle et le format sont vérifiés sur les octets par
            // l'Action ; cette règle-ci ne fait qu'éviter un aller-retour
            // évident, elle n'est pas le garde-fou. La borne passe par
            // `ModuleUploadPaths` : lue directement, une configuration publiée
            // sans le bloc `upload` donnait `max:0`, qui refusait tout.
            'archive' => ['required', 'file', 'max:'.intdiv(ModuleUploadPaths::maxSize(), 1024)],
        ], [], ['archive' => __('baobab::admin.modules.upload_field')]);

        /** @var UploadedFile $archive */
        $archive = $request->file('archive');

        return $this->run(
            fn () => $action((string) $archive->getRealPath()),
            'uploaded',
        );
    }

    /**
     * Les échecs du cycle de vie portent déjà un message destiné à un humain
     * (« Impossible de désinstaller X : désactivez-le d'abord. ») : on l'affiche
     * tel quel plutôt que d'en réécrire un second, forcément divergent.
     *
     * La liste des exceptions est énumérée à dessein, pas remplacée par un
     * `RuntimeException` fourre-tout : une erreur SQL ou un bug de migration
     * doivent rester une vraie erreur, pas un toast rassurant.
     */
    private function run(callable $operation, string $successKey): RedirectResponse
    {
        try {
            $operation();
        } catch (
            ModuleNotFoundException|
            IncompatibleModuleException|
            ModuleDependencyCycleException|
            ModuleHasActiveDependentsException|
            ModuleStillActiveException|
            PermissionRemovalNotConfirmedException|
            InvalidMailTemplateException|
            InvalidNotificationException|
            ThemeValidationFailedException|
            InvalidModuleArchiveException|
            InvalidManifestException $exception
        ) {
            session()->flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return redirect()->route('admin.modules.index');
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => __("baobab::admin.modules.{$successKey}"),
        ]);

        return redirect()->route('admin.modules.index');
    }
}
