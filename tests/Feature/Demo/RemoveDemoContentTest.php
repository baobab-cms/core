<?php

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Demo\Actions\RemoveDemoContent;
use Baobab\Demo\Actions\SeedDemoContent;
use Baobab\Demo\Actions\SeedDemoForm;
use Baobab\Forms\Models\Form;
use Baobab\Media\Models\Media;
use Baobab\Menus\Models\Menu;
use Baobab\Menus\Models\MenuAssignment;
use Baobab\Rendering\Actions\UpdateReadingSettings;
use Baobab\Rendering\Models\ReadingSetting;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * « Supprimer le contenu de démonstration » (spec 03 §7, spec 19 §7.3) —
 * M8 point 3, Pass D2b, suivi n° 252.
 *
 * Ce que ces tests gardent : le retrait est définitif sur les créations,
 * conditionnel sur les réglages — il ne restaure que ce que la démonstration
 * porte encore —, et laisse toujours les Content Types en place.
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

it('ne fait rien s\'il n\'y a aucun contenu de démonstration', function () {
    $lignes = app(RemoveDemoContent::class)();

    expect($lignes)->toBe(['Aucun contenu de démonstration à retirer.']);
});

it('purge les contenus, les images et le menu, et laisse les Content Types en place', function () {
    app(SeedDemoContent::class)($this->acteur);

    $page = ContentType::where('key', 'Page')->firstOrFail();
    $article = ContentType::where('key', 'Article')->firstOrFail();
    /** @var class-string<Model> $pageModel */
    $pageModel = $page->modelClass();
    /** @var class-string<Model> $articleModel */
    $articleModel = $article->modelClass();

    $lignes = app(RemoveDemoContent::class)();

    expect($lignes)->toBe(['Contenu de démonstration retiré.'])
        ->and($pageModel::query()->count())->toBe(0)
        ->and($pageModel::withTrashed()->count())->toBe(0)
        ->and($articleModel::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(Menu::query()->count())->toBe(0)
        // Ce que l'arbitrage D-D protège : les types restent.
        ->and(ContentType::where('key', 'Page')->exists())->toBeTrue()
        ->and(ContentType::where('key', 'Article')->exists())->toBeTrue();
});

it('restaure la page d\'accueil quand elle porte encore ce que la démonstration y avait posé', function () {
    $avant = ReadingSetting::current()->mode;

    app(SeedDemoContent::class)($this->acteur);
    app(RemoveDemoContent::class)();

    $reglage = ReadingSetting::current();

    expect($reglage->mode)->toBe($avant)
        ->and($reglage->page_content_type_key)->toBeNull()
        ->and($reglage->page_entry_id)->toBeNull();
});

it('ne touche pas à la page d\'accueil si l\'administrateur l\'a changée depuis', function () {
    app(SeedDemoContent::class)($this->acteur);

    // L'administrateur reprend la main sur son site.
    app(UpdateReadingSettings::class)(['mode' => 'latest_posts', 'posts_content_type_key' => null]);

    app(RemoveDemoContent::class)();

    $reglage = ReadingSetting::current();

    expect($reglage->mode)->toBe('latest_posts');
});

it('restaure l\'emplacement primary quand il porte encore le menu de la démonstration', function () {
    app(SeedDemoContent::class)($this->acteur);

    expect(MenuAssignment::where('location_key', 'primary')->exists())->toBeTrue();

    app(RemoveDemoContent::class)();

    expect(MenuAssignment::where('location_key', 'primary')->exists())->toBeFalse();
});

it('ne touche pas à l\'emplacement primary si l\'administrateur y a assigné un autre menu', function () {
    app(SeedDemoContent::class)($this->acteur);

    $menuAdmin = Menu::create(['name' => 'Menu de l\'administrateur']);
    MenuAssignment::where('location_key', 'primary')->delete();
    MenuAssignment::create(['location_key' => 'primary', 'menu_id' => $menuAdmin->id]);

    app(RemoveDemoContent::class)();

    expect(MenuAssignment::where('location_key', 'primary')->first()?->menu_id)->toBe($menuAdmin->id)
        // Le menu de la démonstration, lui, a bien été retiré — seule sa
        // trace sur `primary` a été respectée, pas sa suppression.
        ->and(Menu::where('name', 'Navigation principale')->exists())->toBeFalse();
});

/**
 * Le formulaire n'est pas un Content Type (spec 14 §1) : sans une branche
 * dédiée, la boucle générique de `RemoveDemoContent` ne trouverait aucun type
 * pour lui via `ContentType::forModelClass()` et se contenterait de retirer
 * sa marque, laissant le formulaire orphelin — jamais purgé.
 */
it('supprime le formulaire de contact de démonstration', function () {
    app(SeedDemoForm::class)($this->acteur);

    app(RemoveDemoContent::class)();

    expect(Form::where('slug', 'contact')->exists())->toBeFalse();
});

it('rend le seeder de formulaire rejouable après un retrait', function () {
    app(SeedDemoForm::class)($this->acteur);
    app(RemoveDemoContent::class)();

    $lignes = app(SeedDemoForm::class)($this->acteur);

    expect($lignes)->not->toBe(['Le formulaire de démonstration est déjà en place.'])
        ->and(Form::where('slug', 'contact')->count())->toBe(1);
});

it('rend le seeder rejouable après un retrait — le cycle complet promis par la spec 19 §7.3', function () {
    app(SeedDemoContent::class)($this->acteur);
    app(RemoveDemoContent::class)();

    $lignes = app(SeedDemoContent::class)($this->acteur);

    $page = ContentType::where('key', 'Page')->firstOrFail();
    /** @var class-string<Model> $pageModel */
    $pageModel = $page->modelClass();

    expect($lignes)->not->toBe(['Le contenu de démonstration est déjà en place.'])
        ->and($pageModel::query()->where('slug', 'accueil')->exists())->toBeTrue()
        // Un seul type, jamais reconstruit : deux appels à BuildContentType
        // sur la même clé lèveraient DuplicateContentTypeException.
        ->and(ContentType::where('key', 'Page')->count())->toBe(1);
});
