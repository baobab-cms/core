<?php

declare(strict_types=1);

namespace Baobab\Demo\Actions;

use Baobab\ContentTypes\Actions\PurgeContentEntry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Demo\Models\DemoContent;
use Baobab\Forms\Actions\DeleteForm;
use Baobab\Forms\Models\Form;
use Baobab\Media\Actions\PurgeMedia;
use Baobab\Media\Models\Media;
use Baobab\Menus\Actions\DeleteMenu;
use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuAssignment;
use Baobab\Modules\Models\Module;
use Baobab\Rendering\Actions\UpdateReadingSettings;
use Baobab\Rendering\Models\ReadingSetting;
use Illuminate\Support\Collection;

/**
 * « Supprimer le contenu de démonstration » (spec 03 §7, spec 19 §7.3) —
 * M8 point 3, Pass D2b, suivi n° 252.
 *
 * **Définitif, sans corbeille** (arbitrage D-A, suivi n° 249) : les entrées
 * sont purgées (`PurgeContentEntry`), jamais simplement supprimées — un
 * `slug` resté en corbeille bloquerait le rejeu du seeder par son unicité,
 * ce qui viderait de sens la promesse « réversible » du §7.3.
 *
 * **Les créations partent, les réglages reviennent — seulement s'ils portent
 * encore ce que la démonstration y avait mis.** Un réglage changé depuis par
 * l'utilisateur ne doit jamais être écrasé par un retrait qui n'a plus rien
 * à voir avec lui.
 *
 * **Les Content Types et leurs modules ne partent jamais** (arbitrage D-D) :
 * détruire `Article` détruirait aussi les articles que l'utilisateur y
 * aurait écrits depuis. Leurs marques dans `demo_content` restent donc en
 * place, à titre de provenance — rien ne les consomme aujourd'hui, mais les
 * effacer ferait perdre une information qu'on ne saurait plus reconstruire.
 */
final class RemoveDemoContent
{
    /** @var list<class-string> Marques qui ne se purgent jamais — voir la classe. */
    private const PERSISTENT_MARKS = [Menu::class, Media::class, ContentType::class, Module::class];

    public function __construct(
        private readonly PurgeContentEntry $purgeEntry,
        private readonly PurgeMedia $purgeMedia,
        private readonly DeleteMenu $deleteMenu,
        private readonly UpdateReadingSettings $updateReading,
        private readonly DeleteForm $deleteForm,
    ) {}

    /**
     * @return list<string> lignes affichables
     */
    public function __invoke(): array
    {
        $entryMarks = DemoContent::whereNotNull('demoable_type')
            ->whereNotIn('demoable_type', self::PERSISTENT_MARKS)
            ->get();

        $hasSettings = DemoContent::whereNotNull('setting_key')->exists();

        if ($entryMarks->isEmpty() && ! $hasSettings) {
            return ['Aucun contenu de démonstration à retirer.'];
        }

        $menuMark = DemoContent::where('demoable_type', Menu::class)->first();

        $this->restoreReadingSettings($entryMarks);
        $this->restorePrimaryMenu($menuMark?->demoable_id);

        foreach ($entryMarks as $mark) {
            $entry = $mark->demoable;

            if ($entry instanceof Form) {
                // Un formulaire n'est pas un Content Type (spec 14 §1) : il
                // n'a pas d'entrée dans `ContentType::forModelClass()`, la
                // branche ci-dessous ne le trouverait donc jamais — sans ce
                // cas, la marque partirait en laissant le formulaire orphelin.
                ($this->deleteForm)($entry);
            } elseif ($entry !== null) {
                $type = ContentType::forModelClass($entry::class);

                if ($type !== null) {
                    ($this->purgeEntry)($type, $entry);
                }
            }

            $mark->delete();
        }

        foreach (DemoContent::where('demoable_type', Media::class)->get() as $mark) {
            if ($mark->demoable instanceof Media) {
                ($this->purgeMedia)($mark->demoable);
            }

            $mark->delete();
        }

        if ($menuMark?->demoable instanceof Menu) {
            ($this->deleteMenu)($menuMark->demoable);
        }

        $menuMark?->delete();

        return ['Contenu de démonstration retiré.'];
    }

    /**
     * @param  Collection<int, DemoContent>  $entryMarks
     */
    private function restoreReadingSettings(Collection $entryMarks): void
    {
        $mark = DemoContent::where('setting_key', SeedDemoContent::SETTING_READING)->first();

        if ($mark === null) {
            return;
        }

        $home = $entryMarks->first(function (DemoContent $entryMark): bool {
            $entry = $entryMark->demoable;

            return $entry !== null && $entry->getAttribute('slug') === 'accueil';
        });

        $current = ReadingSetting::current();

        // On ne restaure que si le réglage porte encore ce que la
        // démonstration y avait posé — un mode ou une cible différents
        // signent un choix fait depuis, qu'un retrait ne doit jamais défaire.
        $stillOurs = $home !== null
            && $current->mode === 'static_page'
            && $current->page_content_type_key === 'Page'
            && $current->page_entry_id === $home->demoable_id;

        if ($stillOurs) {
            /** @var array<string, mixed> $previous */
            $previous = $mark->previous_value ?? [];
            ($this->updateReading)($previous);
        }

        $mark->delete();
    }

    private function restorePrimaryMenu(?int $demoMenuId): void
    {
        $mark = DemoContent::where('setting_key', SeedDemoContent::SETTING_PRIMARY_MENU)->first();

        if ($mark === null) {
            return;
        }

        $current = MenuAssignment::where('location_key', 'primary')->first();
        $stillOurs = $demoMenuId !== null && $current?->menu_id === $demoMenuId;

        if ($stillOurs) {
            /** @var array<string, mixed> $previous */
            $previous = $mark->previous_value ?? [];
            $previousMenuId = $previous['menu_id'] ?? null;

            // Restauration par emplacement, pas par menu : `AssignMenuToLocations`
            // remplace tous les emplacements du menu qu'on lui donne, ce qui
            // écraserait les autres emplacements du menu antérieur si on
            // passait par elle pour restaurer un seul emplacement.
            MenuAssignment::where('location_key', 'primary')->delete();

            if (is_int($previousMenuId)) {
                MenuAssignment::create(['location_key' => 'primary', 'menu_id' => $previousMenuId]);
            }
        }

        $mark->delete();
    }
}
