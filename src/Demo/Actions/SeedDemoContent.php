<?php

declare(strict_types=1);

namespace Baobab\Demo\Actions;

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Actions\SaveContentEntry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Demo\Models\DemoContent;
use Baobab\Media\Actions\UploadMedia;
use Baobab\Menus\Actions\AssignMenuToLocations;
use Baobab\Menus\Actions\CreateMenu;
use Baobab\Menus\Actions\SaveMenuItems;
use Baobab\Menus\Models\MenuAssignment;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Rendering\Actions\UpdateReadingSettings;
use Baobab\Rendering\Models\ReadingSetting;
use Baobab\Users\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Étape 8 de la séquence (spec 15 §4) — pose le contenu de démonstration
 * décrit par la spec 19 §7.2 (M8 point 3, Pass D2a, suivi n° 249).
 *
 * **Ce qu'elle crée, et ce qu'elle modifie.** Elle crée deux Content Types,
 * cinq contenus, leurs images et un menu ; elle **modifie** en plus deux
 * réglages existants — la page d'accueil et l'emplacement `primary`. Le
 * retrait ne peut pas traiter les deux pareil, d'où la valeur d'avant notée
 * au passage : on supprime ce qui a été créé, on restaure ce qui a été
 * modifié (suivi n° 247, arbitrage D-D).
 *
 * **Elle enregistre les modules qu'elle vient de créer.** `bootstrapActiveModules()`
 * n'appelle `ModuleAutoloader::registerFor()` **qu'au boot** : un Content Type
 * construit dans la requête courante n'a donc pas sa classe de modèle
 * chargeable, et `SaveContentEntry` — qui résout `modelClass()` — échouerait
 * dessus. C'est le même geste que font les tests d'admin après chaque
 * `BuildContentType`.
 *
 * **Rejouable après un retrait, sans reconstruire les types.** `RemoveDemoContent`
 * (D2b) laisse `Page` et `Article` en place (arbitrage D-D) : les reconstruire
 * ici lèverait `DuplicateContentTypeException`. Elle les **réutilise** donc, et
 * ne se considère « déjà en place » que si des entrées de démonstration y
 * vivent encore — jamais à la seule existence du type (suivi n° 252).
 */
final class SeedDemoContent
{
    /** Le réglage de lecture, tel que le retrait devra le retrouver. */
    public const SETTING_READING = 'reading';

    /** L'emplacement de menu occupé par la démonstration. */
    public const SETTING_PRIMARY_MENU = 'menu_location.primary';

    public function __construct(
        private readonly BuildContentType $buildContentType,
        private readonly ModuleAutoloader $autoloader,
        private readonly SaveContentEntry $saveEntry,
        private readonly UploadMedia $uploadMedia,
        private readonly CreateMenu $createMenu,
        private readonly SaveMenuItems $saveMenuItems,
        private readonly AssignMenuToLocations $assignMenu,
        private readonly UpdateReadingSettings $updateReading,
    ) {}

    /**
     * @return list<string> lignes affichables, destinées au `StepOutcome`
     */
    public function __invoke(User $actor): array
    {
        $page = $this->resolveType('Page', fn (): string => $this->pageBlueprint());
        $article = $this->resolveType('Article', fn (): string => $this->articleBlueprint());

        // Rejouer par-dessus des entrées déjà présentes créerait un second
        // jeu de contenus sans que rien ne le dise, et les slugs entreraient
        // en collision. Les Content Types, eux, sont de l'infrastructure :
        // ils survivent à un retrait (suivi n° 247, arbitrage D-D) et se
        // réutilisent ici plutôt que de se reconstruire — sans quoi rejouer
        // après un retrait lèverait `DuplicateContentTypeException` (suivi
        // n° 252, révision de la garde livrée en D2a).
        if ($this->isLive($page) || $this->isLive($article)) {
            return ['Le contenu de démonstration est déjà en place.'];
        }

        $home = $this->createEntry($page, $actor, $this->homePage());
        $about = $this->createEntry($page, $actor, $this->aboutPage());

        foreach ($this->articles() as $data) {
            $cover = $this->importCover((string) $data['slug'], $actor);

            if ($cover !== null) {
                $data['cover'] = $cover;
            }

            $this->createEntry($article, $actor, $data);
        }

        $this->buildPrimaryMenu($home, $about);
        $this->useAsHomepage($page, $home);

        return [
            'Content Types créés : Page, Article.',
            'Contenus créés : 2 pages, 3 articles.',
            'Menu « Navigation principale » assigné à l\'emplacement primary.',
        ];
    }

    /**
     * Le Content Type d'une clé donnée, réutilisé s'il existe déjà — d'un
     * retrait précédent ou d'une reprise d'installation — construit sinon.
     *
     * @param  Closure(): string  $blueprintJson  différé : inutile de composer
     *                                            le blueprint quand le type
     *                                            existe déjà
     */
    private function resolveType(string $key, Closure $blueprintJson): ContentType
    {
        $existing = ContentType::where('key', $key)->first();

        if ($existing instanceof ContentType) {
            $module = Module::find($existing->module_id);

            if ($module instanceof Module) {
                $this->autoloader->registerFor($module);
            }

            return $existing;
        }

        return $this->buildType($blueprintJson());
    }

    /**
     * Construit un Content Type **et rend son modèle chargeable** dans le
     * processus courant — voir le docblock de la classe.
     */
    private function buildType(string $blueprintJson): ContentType
    {
        $type = ($this->buildContentType)($blueprintJson);

        $module = Module::find($type->module_id);

        if ($module instanceof Module) {
            $this->autoloader->registerFor($module);
            DemoContent::markCreated($module);
        }

        DemoContent::markCreated($type);

        return $type->fresh() ?? $type;
    }

    /**
     * Le contenu de démonstration est-il actuellement posé sous ce type ?
     *
     * Distinct de « le type existe » : un type peut survivre à un retrait
     * (arbitrage D-D) sans qu'aucune entrée de démonstration n'y vive plus.
     */
    private function isLive(ContentType $type): bool
    {
        return DemoContent::where('demoable_type', $type->modelClass())->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createEntry(ContentType $type, User $actor, array $data): Model
    {
        $entry = ($this->saveEntry)($type, $data, $actor);

        DemoContent::markCreated($entry);

        return $entry;
    }

    /**
     * Importe l'image d'un article si elle est présente, et rend son
     * identifiant de média.
     *
     * **L'absence n'est pas une erreur.** Les images sont fournies hors du
     * code (spec 19 §7.2 : « libres de droits ») ; un dépôt qui n'en porte
     * pas encore doit tout de même produire un site de démonstration
     * cohérent, avec des articles sans image mise en avant.
     */
    private function importCover(string $slug, User $actor): ?int
    {
        $path = $this->assetsPath().'/'.$slug.'.jpg';

        if (! is_file($path)) {
            return null;
        }

        $media = ($this->uploadMedia)(
            // Cinquième argument : le fichier ne vient pas d'un envoi HTTP,
            // et sans lui `UploadedFile` refuse de le lire.
            new UploadedFile($path, basename($path), null, null, true),
            $actor,
            ['alt' => 'Illustration de l\'article « '.$slug.' »'],
        );

        DemoContent::markCreated($media);

        return (int) $media->getKey();
    }

    /**
     * Où les images de démonstration sont cherchées.
     *
     * Configurable pour une raison de vérification et non de souplesse : le
     * dépôt **porte** ces images, si bien que le cas « aucune image fournie »
     * — celui d'une distribution amputée, et le seul où l'absence doit rester
     * sans conséquence — ne serait exerçable par aucun test si le chemin
     * était figé.
     */
    private function assetsPath(): string
    {
        $configured = config('baobab.demo.assets_path');

        return is_string($configured) && $configured !== ''
            ? rtrim($configured, '/\\')
            : dirname(__DIR__, 3).'/ressources/demo';
    }

    private function buildPrimaryMenu(Model $home, Model $about): void
    {
        $menu = ($this->createMenu)('Navigation principale', 'Menu posé par le contenu de démonstration.');

        DemoContent::markCreated($menu);

        ($this->saveMenuItems)($menu, [
            ['type' => 'content', 'depth' => 0, 'label' => 'Accueil', 'linkable_type' => $home::class, 'linkable_id' => $home->getKey()],
            ['type' => 'content', 'depth' => 0, 'label' => 'À propos', 'linkable_type' => $about::class, 'linkable_id' => $about->getKey()],
            ['type' => 'archive', 'depth' => 0, 'label' => 'Articles', 'content_type_key' => 'Article'],
        ]);

        DemoContent::markSetting(self::SETTING_PRIMARY_MENU, [
            'menu_id' => MenuAssignment::where('location_key', 'primary')->value('menu_id'),
        ]);

        ($this->assignMenu)($menu, ['primary']);
    }

    private function useAsHomepage(ContentType $page, Model $home): void
    {
        $setting = ReadingSetting::current();

        DemoContent::markSetting(self::SETTING_READING, [
            'mode' => $setting->mode,
            'page_content_type_key' => $setting->page_content_type_key,
            'page_entry_id' => $setting->page_entry_id,
            'posts_content_type_key' => $setting->posts_content_type_key,
        ]);

        ($this->updateReading)([
            'mode' => 'static_page',
            'page_content_type_key' => $page->key,
            'page_entry_id' => $home->getKey(),
        ]);
    }

    private function pageBlueprint(): string
    {
        return (string) json_encode([
            'key' => 'Page',
            'label' => ['singular' => 'Page', 'plural' => 'Pages'],
            'is_addressable' => true,
            'url_prefix' => 'pages',
            'title_field' => 'title',
            'body_field' => 'body',
            'fields' => [
                ['key' => 'title', 'type' => 'text', 'required' => true],
                ['key' => 'body', 'type' => 'richtext'],
            ],
        ]);
    }

    private function articleBlueprint(): string
    {
        return (string) json_encode([
            'key' => 'Article',
            'label' => ['singular' => 'Article', 'plural' => 'Articles'],
            'is_addressable' => true,
            'url_prefix' => 'articles',
            'title_field' => 'title',
            'body_field' => 'body',
            'image_field' => 'cover',
            'fields' => [
                ['key' => 'title', 'type' => 'text', 'required' => true],
                ['key' => 'excerpt', 'type' => 'textarea'],
                ['key' => 'body', 'type' => 'richtext'],
                ['key' => 'cover', 'type' => 'image'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function homePage(): array
    {
        return [
            'title' => 'Bienvenue',
            'slug' => 'accueil',
            'status' => 'published',
            'published_at' => now(),
            'body' => '<p>Ce site tourne sur Baobab, un CMS modulaire construit sur Laravel. '
                .'Cette page, les articles et le menu qui les relie ont été posés par le contenu '
                .'de démonstration, à l\'installation.</p>'
                .'<p>Vous pouvez tout modifier depuis l\'administration, ou retirer l\'ensemble '
                .'en un geste : le site reste alors parfaitement présentable, chaque gabarit du '
                .'thème par défaut ayant son état vide.</p>',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aboutPage(): array
    {
        return [
            'title' => 'À propos',
            'slug' => 'a-propos',
            'status' => 'published',
            'published_at' => now(),
            'body' => '<p>Baobab s\'adresse aux développeurs et aux agences qui veulent un socle '
                .'lisible plutôt qu\'un empilement d\'extensions.</p>'
                .'<p>Sa particularité tient en une phrase : chaque type de contenu possède sa '
                .'propre table, avec ses vraies colonnes et ses vrais types. Pas de table '
                .'fourre-tout où toutes les valeurs se ressemblent — une requête SQL écrite à la '
                .'main reste lisible, et un index fait ce qu\'on attend de lui.</p>',
        ];
    }

    /**
     * Les trois articles. Leur `slug` sert aussi de nom de fichier image :
     * déposer `<slug>.jpg` dans `ressources/demo/` suffit à l'illustrer.
     *
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        return [
            [
                'title' => 'Chaque type de contenu a sa vraie table',
                'slug' => 'des-tables-typees',
                'status' => 'published',
                'published_at' => now()->subDays(2),
                'excerpt' => 'Pourquoi Baobab génère une table par type de contenu au lieu de tout entasser dans une table de méta-données.',
                'body' => '<p>Créer un type de contenu dans Baobab produit une véritable table, '
                    .'avec une colonne par champ et le type SQL qui convient. Une date est une '
                    .'date, un booléen est un booléen.</p>'
                    .'<p>Ce choix a un coût — créer un type exécute une migration — et un '
                    .'bénéfice qui se mesure dès le premier millier de contenus : les requêtes '
                    .'restent simples, les index servent à quelque chose, et un développeur qui '
                    .'ouvre la base comprend ce qu\'il lit.</p>',
            ],
            [
                'title' => 'Modules, thèmes et hooks : trois façons d\'étendre',
                'slug' => 'etendre-baobab',
                'status' => 'published',
                'published_at' => now()->subDays(5),
                'excerpt' => 'Un module ajoute des fonctionnalités, un thème change l\'apparence, un hook s\'insère sans toucher au cœur.',
                'body' => '<p>Un <strong>module</strong> apporte des fonctionnalités et vit dans '
                    .'son propre répertoire. Un <strong>thème</strong> ne décide de rien : il '
                    .'reçoit des données prêtes et les affiche.</p>'
                    .'<p>Entre les deux, les <strong>hooks</strong> permettent de s\'insérer dans '
                    .'ce que fait le Core sans en modifier une ligne — le même principe que les '
                    .'actions et filtres qui ont fait le succès de WordPress, avec des types et '
                    .'un catalogue consultable en ligne de commande.</p>',
            ],
            [
                'title' => 'Installer Baobab sur un hébergement mutualisé',
                'slug' => 'installer-sur-mutualise',
                'status' => 'published',
                'published_at' => now()->subDays(9),
                'excerpt' => 'Sans Composer, sans Node, sans accès SSH : une archive, un gestionnaire de fichiers et cinq minutes.',
                'body' => '<p>Baobab se distribue aussi sous forme d\'archive prête à l\'emploi, '
                    .'dépendances comprises. On la dépose par le gestionnaire de fichiers de son '
                    .'hébergeur, on ouvre le site, et un installateur graphique prend la main.</p>'
                    .'<p>Il vérifie ce que le serveur sait faire plutôt que de supposer, et le '
                    .'dit quand une capacité manque — puis termine en donnant les lignes de '
                    .'tâches planifiées à coller dans le panneau, prêtes à l\'emploi.</p>',
            ],
        ];
    }
}
