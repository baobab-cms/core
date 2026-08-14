<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\Audit\AuditLogger;
use Baobab\Mail\MailTemplateValidator;
use Baobab\Modules\DependencyResolver;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Exceptions\PermissionRemovalNotConfirmedException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\Models\ModuleMenuItem;
use Baobab\Modules\Models\ModulePermission;
use Baobab\Modules\ModuleDiscovery;
use Baobab\Modules\ModuleManifest;
use Baobab\Notify\NotificationValidator;
use Baobab\Themes\Validation\ThemeValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Relit `module.json` sur disque et remet à jour ce que Baobab en avait retenu
 * (suivi n° 111, Pass B — le manifeste).
 *
 * # Le défaut que cette Action ferme
 *
 * `InstallModule` est le **seul** écrivain de ce que le produit sait d'un module,
 * et il n'écrit qu'une fois. Quatre stockages en dépendent, tous figés à
 * l'installation :
 *
 * 1. la colonne `modules.manifest` — hooks émis et écoutés, widgets,
 *    `media_presets`, notifications, mails, tâches planifiées, `autoload.psr-4`,
 *    et côté thème les tokens, polices et parent ;
 * 2. les colonnes scalaires `modules.title`, `version`, `provider` ;
 * 3. les lignes `module_permissions` ;
 * 4. les lignes `module_menu_items`.
 *
 * Régénérer `module.json` n'en propageait aucun. Le comportement était connu
 * (n° 95) mais tenu pour une curiosité du cycle de vie ; il devient un vrai
 * défaut dès qu'un blueprint se rouvre et se régénère.
 *
 * # Pourquoi elle n'est pas une Action du Studio
 *
 * Le défaut n'a rien de propre au Studio : un module mis à jour par
 * `composer update` est exactement aussi périmé qu'un module régénéré. L'Action
 * vit donc dans `Actions\Modules`, avec le reste du cycle de vie, et compte trois
 * appelants — la régénération Studio, `baobab:module:sync`, et l'écran Modules —
 * tous adaptateurs minces au sens du principe API-first.
 *
 * # Ce qu'elle ne fait pas
 *
 * Elle **ne migre pas le schéma** : c'est `EvolveModuleSchema` (Pass A), et les
 * deux restent séparées parce qu'elles ne s'adressent pas au même objet — l'une
 * à la base de données du module, l'autre à ce que le Core sait de lui. Elle
 * **n'active ni ne désactive** : un module inactif se resynchronise sans changer
 * d'état.
 *
 * Le manifeste relu passe **les mêmes validations qu'à l'installation** : un
 * manifeste devenu invalide doit être refusé ici comme il l'aurait été là-bas,
 * sinon la resynchronisation deviendrait la porte dérobée par laquelle un module
 * incohérent entre en base.
 */
final class SyncModuleManifest
{
    public function __construct(
        private readonly ModuleDiscovery $discovery,
        private readonly DependencyResolver $dependencies,
        private readonly MailTemplateValidator $mailTemplates,
        private readonly NotificationValidator $notifications,
        private readonly ThemeValidator $themes,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{
     *     manifest_changed: bool,
     *     permissions: array{added: list<string>, updated: list<string>, removed: list<string>},
     *     menu_items: int,
     * }
     *
     * @throws ModuleNotFoundException
     * @throws PermissionRemovalNotConfirmedException
     */
    public function __invoke(Module $module, bool $confirmDestructive = false): array
    {
        $discovered = $this->discovery->scan()->get($module->name);

        if ($discovered === null) {
            throw ModuleNotFoundException::named($module->name);
        }

        $manifest = $discovered->manifest;

        $this->assertValid($manifest, $discovered->path, $module);

        $declared = $this->declaredPermissions($manifest);
        $existing = $module->permissions()->get()->keyBy('key');

        /** @var list<string> $removed */
        $removed = array_values(array_diff($existing->keys()->all(), array_keys($declared)));

        // Vérifié **avant la première écriture** : une resynchronisation refusée
        // ne doit rien avoir changé (patron `EvolveModuleSchema`, Pass A).
        if ($removed !== [] && ! $confirmDestructive) {
            throw PermissionRemovalNotConfirmedException::forPermissions($removed);
        }

        $manifestChanged = $module->manifest !== $manifest->toArray();

        $report = DB::transaction(function () use ($module, $manifest, $discovered, $declared, $existing, $removed): array {
            $module->update([
                'title' => $manifest->title(),
                'version' => $manifest->version(),
                'provider' => $manifest->provider(),
                'source' => $discovered->source,
                'path' => $discovered->path,
                'manifest' => $manifest->toArray(),
            ]);

            $permissions = $this->syncPermissions($module, $declared, $existing, $removed);
            $menuItems = $this->syncMenuItems($module, $manifest->adminMenuItems());

            return ['permissions' => $permissions, 'menu_items' => $menuItems];
        });

        $result = ['manifest_changed' => $manifestChanged, ...$report];

        // Aucun hook émis ici, et c'est délibéré. La spec-modules §4 énumère un
        // tableau **fermé** de quatre étapes de cycle de vie et de leurs hooks
        // (`installed`, `activated`, `deactivated`, `uninstalled`) ; la
        // resynchronisation en serait une cinquième, et le nommage d'un point
        // d'extension public est une décision de spec, pas d'implémentation —
        // même arbitrage qu'au suivi n° 126, où un `baobab.theme.deactivated`
        // inventé avait été écarté au profit d'un hook déjà documenté.
        // Proposition consignée au suivi n° 155. L'audit, lui, est de la tenue
        // de registre interne et n'engage aucune API publique.
        $this->audit->record('module.manifest_synced', $module, $result);

        return $result;
    }

    /**
     * Mêmes gardes qu'`InstallModule`, à une près : le cycle de dépendances se
     * vérifie contre les **autres** modules, celui-ci étant déjà en base et
     * apparaîtrait sinon comme son propre antécédent.
     */
    private function assertValid(ModuleManifest $manifest, string $path, Module $module): void
    {
        $this->dependencies->assertCoreCompatible($manifest);
        $this->dependencies->assertNoCycle(
            $manifest->name(),
            $manifest->requiresModules(),
            Module::where('id', '!=', $module->id)->get(),
        );
        $this->mailTemplates->assertValid($manifest, $path);
        $this->notifications->assertValid($manifest);

        if ($manifest->type() === 'theme') {
            $this->themes->assertValid($manifest, $path);
        }
    }

    /**
     * @return array<string, array{key: string, label: string, default_roles?: list<string>}>
     */
    private function declaredPermissions(ModuleManifest $manifest): array
    {
        $declared = [];

        foreach ($manifest->permissions() as $permission) {
            $declared[$permission['key']] = $permission;
        }

        return $declared;
    }

    /**
     * Les permissions se réconcilient clé par clé plutôt qu'en table rase : une
     * ligne `module_permissions` recréée changerait d'`id`, et une permission
     * Spatie recréée perdrait ses attributions — un simple changement de libellé
     * révoquerait des droits.
     *
     * @param  array<string, array{key: string, label: string, default_roles?: list<string>}>  $declared
     * @param  Collection<string, ModulePermission>  $existing
     * @param  list<string>  $removed
     * @return array{added: list<string>, updated: list<string>, removed: list<string>}
     */
    private function syncPermissions(Module $module, array $declared, $existing, array $removed): array
    {
        $added = [];
        $updated = [];

        foreach ($declared as $key => $permission) {
            $current = $existing->get($key);

            if ($current === null) {
                ModulePermission::create([
                    'module_id' => $module->id,
                    'key' => $key,
                    'label' => $permission['label'],
                    'default_roles' => $permission['default_roles'] ?? null,
                ]);

                $added[] = $key;

                continue;
            }

            $changes = [
                'label' => $permission['label'],
                'default_roles' => $permission['default_roles'] ?? null,
            ];

            if ($current->label !== $changes['label'] || $current->default_roles !== $changes['default_roles']) {
                $current->update($changes);
                $updated[] = $key;
            }
        }

        if ($removed !== []) {
            ModulePermission::where('module_id', $module->id)->whereIn('key', $removed)->delete();

            // La permission Spatie porte les attributions : la retirer les
            // révoque en cascade. Patron `UninstallModule::purgePermissions()`,
            // mais ici seulement sur confirmation explicite.
            Permission::whereIn('name', $removed)->where('guard_name', 'baobab')->delete();
        }

        // Un module actif doit voir ses permissions nouvelles utilisables tout
        // de suite : `ActivateModule` les crée côté Spatie à l'activation, et
        // une permission déclarée après coup n'y passerait jamais.
        if ($module->status === 'active') {
            foreach ($added as $key) {
                Permission::findOrCreate($key, 'baobab');
            }
        }

        return ['added' => $added, 'updated' => $updated, 'removed' => $removed];
    }

    /**
     * Table rase, à l'inverse des permissions — et la différence est délibérée.
     * Une entrée de menu ne porte **rien** qu'on puisse perdre : ni attribution,
     * ni référence externe à son `id`. La hiérarchie parent/enfant, elle, rend
     * une réconciliation clé par clé nettement plus coûteuse qu'elle ne vaut,
     * une entrée n'ayant même pas de clé stable — seulement un libellé et une
     * route.
     *
     * @param  list<array<string, mixed>>  $items
     * @return int Nombre d'entrées écrites.
     */
    private function syncMenuItems(Module $module, array $items): int
    {
        ModuleMenuItem::where('module_id', $module->id)->delete();

        return $this->persistMenuItems($module, $items);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function persistMenuItems(Module $module, array $items, ?int $parentId = null): int
    {
        $written = 0;

        foreach ($items as $item) {
            $menuItem = ModuleMenuItem::create([
                'module_id' => $module->id,
                'parent_id' => $parentId,
                'label' => $item['label'],
                'icon' => $item['icon'] ?? null,
                'route' => $item['route'] ?? null,
                'route_params' => $item['route_params'] ?? null,
                'permission' => $item['permission'] ?? null,
                'order' => $item['order'] ?? 0,
            ]);

            $written++;

            if (! empty($item['children'])) {
                /** @var list<array<string, mixed>> $children */
                $children = $item['children'];
                $written += $this->persistMenuItems($module, $children, $menuItem->id);
            }
        }

        return $written;
    }
}
