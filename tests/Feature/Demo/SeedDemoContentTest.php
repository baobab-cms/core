<?php

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Demo\Actions\SeedDemoContent;
use Baobab\Demo\Models\DemoContent;
use Baobab\Media\Models\Media;
use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuAssignment;
use Baobab\Rendering\Models\ReadingSetting;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 15 §4 étape 8 et spec 19 §7.2 — le contenu de démonstration
 * (M8 point 3, Pass D2a, suivi n° 249).
 *
 * Ce que ces tests gardent : ce que le seeder **crée** est marqué, ce qu'il
 * **modifie** garde la trace de sa valeur d'avant, et la distinction entre
 * les deux est la condition du retrait de la D2b.
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);

    $this->acteur = User::create([
        'name' => 'Super Admin',
        'email' => 'admin-demo@exemple.fr',
        'password' => 'secret',
    ]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('pose deux Content Types, cinq contenus et un menu assigné', function () {
    $lignes = app(SeedDemoContent::class)($this->acteur);

    expect(Schema::hasTable('ct_pages'))->toBeTrue()
        ->and(Schema::hasTable('ct_articles'))->toBeTrue();

    $page = ContentType::where('key', 'Page')->firstOrFail();
    $article = ContentType::where('key', 'Article')->firstOrFail();

    /** @var class-string<Model> $pageModel */
    $pageModel = $page->modelClass();
    /** @var class-string<Model> $articleModel */
    $articleModel = $article->modelClass();

    expect($pageModel::query()->count())->toBe(2)
        ->and($articleModel::query()->count())->toBe(3)
        ->and($pageModel::query()->where('slug', 'accueil')->exists())->toBeTrue()
        ->and(Menu::query()->count())->toBe(1)
        ->and(MenuAssignment::where('location_key', 'primary')->exists())->toBeTrue()
        ->and($lignes)->toHaveCount(3);
});

/**
 * Le marquage est ce qui rend le retrait possible : sans lui, on ne saurait
 * plus distinguer un contenu d'exemple d'un contenu écrit par l'utilisateur.
 */
it('marque chaque objet qu\'elle crée', function () {
    app(SeedDemoContent::class)($this->acteur);

    $marques = DemoContent::whereNotNull('demoable_type')->get();

    // 2 Content Types + leurs 2 modules + 2 pages + 3 articles + 1 menu, plus
    // un média par image trouvée. Le total se calcule au lieu d'être écrit :
    // le figer ferait échouer ce test selon que le dépôt porte ses images ou
    // non, alors que ce qu'il garde — « tout ce qui est créé est marqué » —
    // ne dépend pas d'elles.
    $attendu = 10 + Media::query()->count();

    expect($marques)->toHaveCount($attendu)
        ->and($marques->pluck('demoable_type')->unique()->count())->toBeGreaterThan(2);
});

/**
 * La distinction qui porte tout l'arbitrage D-D : un réglage n'est pas une
 * création, et le retrait devra le **restaurer** au lieu de le supprimer.
 */
it('note la valeur d\'avant des réglages qu\'elle modifie', function () {
    $avant = ReadingSetting::current();
    $modeInitial = $avant->mode;

    app(SeedDemoContent::class)($this->acteur);

    $trace = DemoContent::where('setting_key', SeedDemoContent::SETTING_READING)->firstOrFail();

    // Sur une base fraîche le mode vaut `null`, et c'est le cas qui compte :
    // le retrait doit savoir revenir à « aucune page d'accueil choisie », ce
    // qu'il ne peut pas déduire d'une clé absente. La trace doit donc porter
    // la clé, null compris.
    expect($trace->demoable_type)->toBeNull()
        ->and($trace->previous_value)->toHaveKey('mode')
        ->and($trace->previous_value['mode'])->toBe($modeInitial)
        ->and(DemoContent::where('setting_key', SeedDemoContent::SETTING_PRIMARY_MENU)->exists())->toBeTrue();
});

it('règle la page d\'accueil sur la page qu\'elle vient de créer', function () {
    app(SeedDemoContent::class)($this->acteur);

    $page = ContentType::where('key', 'Page')->firstOrFail();
    /** @var class-string<Model> $pageModel */
    $pageModel = $page->modelClass();
    $accueil = $pageModel::query()->where('slug', 'accueil')->firstOrFail();

    $reglage = ReadingSetting::current();

    expect($reglage->mode)->toBe('static_page')
        ->and($reglage->page_content_type_key)->toBe('Page')
        ->and($reglage->page_entry_id)->toBe($accueil->getKey());
});

/**
 * Rejouer par-dessus créerait un second jeu de contenus sans que rien ne le
 * dise, et les slugs entreraient en collision.
 */
it('ne repose rien si le contenu de démonstration est déjà là', function () {
    app(SeedDemoContent::class)($this->acteur);
    $apresPremier = DemoContent::query()->count();

    $lignes = app(SeedDemoContent::class)($this->acteur);

    expect(DemoContent::query()->count())->toBe($apresPremier)
        ->and($lignes)->toBe(['Le contenu de démonstration est déjà en place.']);
});

/**
 * Le cas nominal depuis que le dépôt porte les trois images (spec 19 §7.2) :
 * chaque article reçoit la sienne, importée par le pipeline média réel — et
 * non copiée à la main — donc miniatures et métadonnées comprises.
 */
it('importe l\'image de chaque article et la met en avant', function () {
    app(SeedDemoContent::class)($this->acteur);

    $article = ContentType::where('key', 'Article')->firstOrFail();
    /** @var class-string<Model> $articleModel */
    $articleModel = $article->modelClass();

    $couvertures = $articleModel::query()->pluck('cover');

    expect($couvertures)->toHaveCount(3)
        ->and($couvertures->filter()->count())->toBe(3)
        ->and(Media::whereIn('id', $couvertures->all())->count())->toBe(3);
});

/**
 * Le cas d'une distribution amputée. Il n'est exerçable que parce que le
 * chemin est configurable : le dépôt porte les images, donc pointer ailleurs
 * est la seule façon de vérifier que leur absence reste sans conséquence.
 */
it('produit des articles sans image quand aucun fichier n\'est fourni', function () {
    $vide = sys_get_temp_dir().'/baobab-demo-sans-images-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($vide);
    config(['baobab.demo.assets_path' => $vide]);

    app(SeedDemoContent::class)($this->acteur);

    $article = ContentType::where('key', 'Article')->firstOrFail();
    /** @var class-string<Model> $articleModel */
    $articleModel = $article->modelClass();

    expect($articleModel::query()->count())->toBe(3)
        ->and($articleModel::query()->whereNotNull('cover')->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0);

    File::deleteDirectory($vide);
});
