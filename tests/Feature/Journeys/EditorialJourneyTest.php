<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Audit\Models\AuditEntry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

/**
 * M9 point 1, brique 1 (suivi n° 363) — le parcours éditorial de bout en bout.
 *
 * Chaque couche de ce parcours a ses propres tests ; aucun ne les enchaînait.
 * Or les défauts que les recettes du jalon ont trouvés se logeaient tous à une
 * frontière entre deux couches, jamais au milieu de l'une d'elles. Ce fichier
 * joue donc le chemin qu'un site réel emprunte, avec trois personnes et rien
 * que des requêtes HTTP : un développeur bâtit le type, une autrice écrit,
 * un relecteur publie — puis on regarde ce que voit le public, sur le site et
 * par l'API, et on vérifie que le retrait de la publication le referme.
 *
 * Aucune Action n'est appelée directement : c'est la promesse du principe
 * API-first que ces adaptateurs minces mènent bien aux mêmes Actions, dans
 * l'ordre où un éditeur les rencontre.
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * @param  list<string>  $permissions
 */
function journeyActor(string $name, array $permissions): User
{
    $user = User::create([
        'name' => $name,
        'email' => strtolower($name).'@journey.test',
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('takes an entry from its creation in the admin to the public site and the API, then closes it again on unpublish', function () {
    // `actingAs()` reste en vigueur pour toutes les requêtes suivantes : sans ceci, le
    // « public » qui suit la saisie d'une autrice serait en réalité elle, et verrait
    // ses propres brouillons.
    $visitor = function () {
        app('auth')->forgetGuards();

        return $this;
    };

    $developer = journeyActor('Developer', ['baobab.system.content_types.manage']);

    // 1. Le développeur bâtit le type par le builder : adressable, avec workflow,
    //    lisible publiquement par l'API.
    $this->actingAs($developer, 'baobab')
        ->post('/admin/content-types', [
            'key' => 'JourneyArticle',
            'label_singular' => 'Article de parcours',
            'label_plural' => 'Articles de parcours',
            'is_addressable' => '1',
            'title_field' => 'headline',
            'workflow' => '1',
            'public_api_read' => '1',
            'fields' => json_encode([
                ['key' => 'headline', 'type' => 'text', 'required' => true, 'unique' => false, 'indexed' => false, 'exposed_in_api' => true],
            ]),
            'relations' => json_encode([]),
        ])
        ->assertRedirect();

    $type = ContentType::where('key', 'JourneyArticle')->first();
    expect($type)->not->toBeNull()
        ->and($type->module_id)->not->toBeNull();

    // Une vraie requête suivante démarrerait le site avec ce module actif ; ce test
    // n'a qu'un seul démarrage, donc on rejoue ce que celui-ci ferait : rendre la
    // classe du type (et sa policy) chargeable.
    app(ModuleAutoloader::class)->registerFor(Module::findOrFail($type->module_id));

    // Les permissions du type naissent avec lui : on ne peut les accorder qu'après.
    $author = journeyActor('Author', [
        'content.journey_article.view',
        'content.journey_article.create',
        'content.journey_article.update',
    ]);
    $reviewer = journeyActor('Reviewer', [
        'content.journey_article.view',
        'content.journey_article.publish_any',
    ]);

    // 2. L'autrice saisit l'entrée dans l'admin : elle naît brouillon, et rien
    //    n'est visible du public ni de l'API.
    $this->actingAs($author, 'baobab')
        ->post('/admin/content/journey-articles', [
            'headline' => 'Le baobab de la place',
            'slug' => 'le-baobab-de-la-place',
        ])
        ->assertRedirect(route('admin.content.index', ['contentType' => 'journey-articles']));

    $entry = $type->modelClass()::where('slug', 'le-baobab-de-la-place')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe('draft')
        ->and($entry->author_id)->toBe($author->id);

    $visitor()->get('/journey-articles/le-baobab-de-la-place')->assertNotFound();
    $visitor()->getJson('/api/v1/content/journey-articles')->assertOk()->assertJsonCount(0, 'data');

    // 3. Elle la soumet ; le relecteur l'approuve. Chacun n'a que sa permission.
    $this->actingAs($author, 'baobab')
        ->post("/admin/content/journey-articles/{$entry->getKey()}/transition/submit")
        ->assertRedirect();
    expect($entry->fresh()->status)->toBe('pending');

    $visitor()->get('/journey-articles/le-baobab-de-la-place')->assertNotFound();

    $this->actingAs($author, 'baobab')
        ->post("/admin/content/journey-articles/{$entry->getKey()}/transition/approve")
        ->assertForbidden();
    expect($entry->fresh()->status)->toBe('pending');

    $this->actingAs($reviewer, 'baobab')
        ->post("/admin/content/journey-articles/{$entry->getKey()}/transition/approve")
        ->assertRedirect();

    $entry = $entry->fresh();
    expect($entry->status)->toBe('published')
        ->and($entry->published_at)->not->toBeNull();

    // 4. Le public la voit, sur la page de l'entrée, dans l'archive et par l'API.
    $visitor()->get('/journey-articles/le-baobab-de-la-place')
        ->assertOk()
        ->assertSee('Le baobab de la place');

    $visitor()->get('/journey-articles')
        ->assertOk()
        ->assertSee('le-baobab-de-la-place');

    $visitor()->getJson('/api/v1/content/journey-articles')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.headline', 'Le baobab de la place')
        ->assertJsonPath('meta.pagination.total', 1);

    // 5. Le relecteur la dépublie : le site et l'API la referment.
    $this->actingAs($reviewer, 'baobab')
        ->post("/admin/content/journey-articles/{$entry->getKey()}/transition/unpublish")
        ->assertRedirect();
    expect($entry->fresh()->status)->not->toBe('published');

    $visitor()->get('/journey-articles/le-baobab-de-la-place')->assertNotFound();
    $visitor()->getJson('/api/v1/content/journey-articles')->assertOk()->assertJsonCount(0, 'data');

    // 6. Chaque étape éditoriale a laissé sa trace dans le journal d'audit, sous
    //    le nom de la personne qui l'a accomplie — c'est ce qui rend le parcours
    //    opposable, pas seulement observable.
    $trail = AuditEntry::query()
        ->where('action', 'like', 'content.%')
        ->orderBy('id')
        ->get(['action', 'actor_id'])
        ->map(fn (AuditEntry $entry): array => [$entry->action, (int) $entry->actor_id])
        ->all();

    expect($trail)->toBe([
        ['content.created', $author->id],
        ['content.submitted', $author->id],
        ['content.approved', $reviewer->id],
        ['content.unpublished', $reviewer->id],
    ]);
});
