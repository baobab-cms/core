<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\BaobabServiceProvider;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * @param  list<string>  $permissions
 */
function fleetActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Fleet Actor {$counter}",
        'email' => "fleet-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * Génère, installe, active un blueprint garage/fleet (une seule entité Car,
 * champs brand/text requis + status/select) puis rejoue réellement
 * `BaobabServiceProvider::bootstrapActiveModules()` (méthode privée, invoquée
 * par réflexion) — exactement ce qu'un vrai prochain boot ferait maintenant
 * que le module est actif en base, `ActivateModule` ne chargeant jamais le
 * provider dans le processus courant (cf. son docblock). Vérifie le code de
 * production réel, pas une simulation manuelle : révèle le bug corrigé dans
 * `bootstrapActiveModules()` (`register()` seul, sans `boot()` explicite, ne
 * bootait jamais le provider d'un module — invisible tant qu'aucun provider
 * de module n'avait de logique dans `boot()`, cf. suivi n° 91).
 */
function generateInstallAndBootFleetModule(array $blueprintOverrides = []): void
{
    $json = moduleBlueprintJson(array_replace([
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [
                ['key' => 'brand', 'type' => 'text', 'required' => true],
                ['key' => 'status', 'type' => 'select', 'options' => ['choices' => ['draft', 'published']]],
            ],
        ]],
    ], $blueprintOverrides));

    $blueprint = ModuleBlueprint::fromJson($json);

    app(ModuleGenerator::class)($blueprint);

    // Le nom vient du blueprint et non d'une constante : un cas qui a besoin
    // d'entités différentes a besoin d'un **autre module**. Les classes
    // générées (modèle, contrôleur, Form Request) sont autochargées une fois
    // par processus, et PHP ne redéclare pas une classe : régénérer
    // `garage/fleet` avec d'autres champs laisserait en mémoire le contrôleur
    // du cas précédent, et le test mesurerait le mauvais fichier.
    $name = (string) json_decode($json, true)['identity']['name'];

    app(InstallModule::class)($name);
    app(ActivateModule::class)($name);

    $provider = app()->getProvider(BaobabServiceProvider::class);
    (new ReflectionMethod($provider, 'bootstrapActiveModules'))->invoke($provider);
}

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.studio.modules_path' => generatedModulesPath()]);
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('denies the generated admin screens without the entity permission', function () {
    generateInstallAndBootFleetModule();
    $actor = fleetActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.fleet.cars.index'))
        ->assertForbidden();
});

it('lists, creates, updates and deletes entries through the generated admin CRUD', function () {
    generateInstallAndBootFleetModule();
    $actor = fleetActor(['fleet.cars.view', 'fleet.cars.create', 'fleet.cars.update', 'fleet.cars.delete']);
    $this->actingAs($actor, 'baobab');

    $this->get(route('admin.fleet.cars.index'))->assertOk();

    // Régression : le formulaire généré rendait `<x-dynamic-component>` tel
    // quel, sans compiler les champs, quand le `@if`/`@else` choisissant
    // `:checked` vs `:value` vivait À L'INTÉRIEUR de la balise du composant
    // — le compilateur de tags Blade ne tolère aucune directive dans la
    // liste d'attributs. `assertOk()` seul ne l'aurait jamais détecté.
    $this->get(route('admin.fleet.cars.create'))
        ->assertOk()
        ->assertSee('name="brand"', false)
        ->assertSee('name="status"', false)
        ->assertDontSee('x-dynamic-component', false);

    $this->post(route('admin.fleet.cars.store'), [
        'brand' => 'Renault',
        'status' => 'draft',
    ])->assertRedirect(route('admin.fleet.cars.index'));

    expect(DB::table('cars')->count())->toBe(1);
    $car = DB::table('cars')->first();
    expect($car->brand)->toBe('Renault')
        ->and($car->status)->toBe('draft');

    $this->get(route('admin.fleet.cars.edit', $car->id))
        ->assertOk()
        ->assertSee('name="brand"', false)
        ->assertSee('value="Renault"', false);

    $this->put(route('admin.fleet.cars.update', $car->id), [
        'brand' => 'Peugeot',
        'status' => 'published',
    ])->assertRedirect(route('admin.fleet.cars.index'));

    expect(DB::table('cars')->first()->brand)->toBe('Peugeot');

    $this->delete(route('admin.fleet.cars.destroy', $car->id))
        ->assertRedirect(route('admin.fleet.cars.index'));

    expect(DB::table('cars')->count())->toBe(0);
});

/**
 * Une entité portant **tous** les types de champ que le générateur expose —
 * la question à laquelle le n° 117 devait répondre, et qu'aucun test ne posait
 * puisque la fixture `garage/fleet` ne connaissait que `text` et `select`.
 *
 * @return array<string, mixed>
 */
function everyFieldTypeEntity(): array
{
    return [
        'identity' => [
            'name' => 'garage/showroom',
            'title' => 'Showroom',
            'version' => '1.0.0',
            'type' => 'module',
        ],
        'entities' => [[
            'key' => 'Vehicle',
            'table' => 'vehicles',
            'fields' => [
                ['key' => 'brand', 'type' => 'text', 'required' => true],
                // `textarea`, `richtext`, `slug`, `decimal` et `integer`
                // produisent des colonnes NOT NULL **quel que soit** `required` :
                // leur `columnDefinition()` ne regarde jamais ce drapeau. Un
                // champ facultatif de ces types échoue donc en base au lieu
                // d'être refusé par la validation, qui le dit `nullable`.
                // Propriété du Core, partagée avec la fiche de Content Type et
                // sans rapport avec le n° 117 : relevée en écrivant ce test et
                // consignée, pas corrigée ici. Les déclarer `required` remet
                // le blueprint en accord avec ce que la table impose.
                ['key' => 'summary', 'type' => 'textarea', 'required' => true],
                ['key' => 'body', 'type' => 'richtext', 'required' => true],
                ['key' => 'reference', 'type' => 'slug', 'required' => true],
                ['key' => 'price', 'type' => 'decimal', 'required' => true],
                ['key' => 'seats', 'type' => 'integer', 'required' => true],
                ['key' => 'available', 'type' => 'boolean'],
                ['key' => 'released_on', 'type' => 'date'],
                ['key' => 'inspected_at', 'type' => 'datetime'],
                ['key' => 'opens_at', 'type' => 'time'],
                ['key' => 'specs', 'type' => 'json'],
                ['key' => 'status', 'type' => 'select', 'options' => ['choices' => ['draft', 'published']]],
                ['key' => 'fuel', 'type' => 'radio', 'options' => ['choices' => ['petrol', 'diesel']]],
                ['key' => 'tags', 'type' => 'multiselect', 'options' => ['choices' => ['city', 'sport']]],
            ],
        ]],
    ];
}

/**
 * Les champs que toute soumission doit porter pour que la ligne parte — ceux
 * dont la colonne refuse le nul. Isolés ici pour que chaque cas ne parle que
 * de ce qu'il mesure.
 *
 * @return array<string, string>
 */
function vehicleRequiredPayload(): array
{
    return [
        'brand' => 'Renault',
        'summary' => 'Une citadine.',
        'body' => '<p>Fiche complète.</p>',
        'reference' => 'renault-5',
        'price' => '18450.90',
        'seats' => '5',
    ];
}

it('renders every declared field type in the generated form', function () {
    generateInstallAndBootFleetModule(everyFieldTypeEntity());
    $actor = fleetActor(['showroom.vehicles.view', 'showroom.vehicles.create']);

    $response = $this->actingAs($actor, 'baobab')
        ->get(route('admin.showroom.vehicles.create'))
        ->assertOk()
        // Un composant introuvable levait `InvalidArgumentException` (n° 117) ;
        // un composant trouvé mais non compilé laissait la balise en clair
        // dans la page (n° 93). Les deux se voient ici.
        ->assertDontSee('x-dynamic-component', false);

    foreach (['brand', 'summary', 'body', 'reference', 'seats', 'price', 'available', 'released_on', 'inspected_at', 'opens_at', 'specs', 'status', 'fuel'] as $key) {
        $response->assertSee('name="'.$key.'"', false);
    }

    $response
        ->assertSee('name="tags[]"', false)
        ->assertSee('type="date"', false)
        ->assertSee('type="datetime-local"', false)
        ->assertSee('type="time"', false)
        ->assertSee('type="radio"', false);
});

it('saves and re-renders a value for every declared field type', function () {
    generateInstallAndBootFleetModule(everyFieldTypeEntity());
    $actor = fleetActor(['showroom.vehicles.view', 'showroom.vehicles.create', 'showroom.vehicles.update']);
    $this->actingAs($actor, 'baobab');

    $this->post(route('admin.showroom.vehicles.store'), [
        ...vehicleRequiredPayload(),
        'available' => '1',
        'released_on' => '2026-08-13',
        'inspected_at' => '2026-08-13T10:30',
        // Ce qu'un `<input type="time">` envoie réellement : sans secondes.
        'opens_at' => '08:30',
        // Ce qu'une zone de texte envoie : du JSON en chaîne.
        'specs' => '{"doors": 5}',
        'status' => 'draft',
        'fuel' => 'diesel',
        'tags' => ['city'],
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.showroom.vehicles.index'));

    $vehicle = DB::table('vehicles')->first();

    expect($vehicle->seats)->toBe(5)
        ->and((float) $vehicle->price)->toBe(18450.90)
        ->and((bool) $vehicle->available)->toBeTrue()
        ->and((string) $vehicle->opens_at)->toStartWith('08:30')
        ->and(json_decode((string) $vehicle->specs, true))->toBe(['doors' => 5])
        ->and(json_decode((string) $vehicle->tags, true))->toBe(['city'])
        ->and($vehicle->fuel)->toBe('diesel');

    // Le retour au formulaire est l'autre moitié : une valeur enregistrée que
    // son input ne sait pas relire donne un champ vide, sans la moindre erreur.
    $this->get(route('admin.showroom.vehicles.edit', $vehicle->id))
        ->assertOk()
        ->assertSee('value="2026-08-13"', false)
        ->assertSee('value="2026-08-13T10:30"', false)
        ->assertSee('value="08:30:00"', false)
        ->assertSee('&quot;doors&quot;', false);
});

it('clears a boolean and a multiselect that the browser stops sending once emptied', function () {
    generateInstallAndBootFleetModule(everyFieldTypeEntity());
    $actor = fleetActor(['showroom.vehicles.view', 'showroom.vehicles.create', 'showroom.vehicles.update']);
    $this->actingAs($actor, 'baobab');

    $this->post(route('admin.showroom.vehicles.store'), [
        ...vehicleRequiredPayload(),
        'available' => '1',
        'tags' => ['city', 'sport'],
    ])->assertSessionHasNoErrors();

    $vehicle = DB::table('vehicles')->first();

    // Une case décochée et une liste multiple vidée ne postent **rien** : sans
    // normalisation, l'ancienne valeur survivrait à sa propre suppression.
    $this->put(route('admin.showroom.vehicles.update', $vehicle->id), [
        ...vehicleRequiredPayload(),
        'tags' => [''],
    ])->assertSessionHasNoErrors();

    $updated = DB::table('vehicles')->first();

    expect((bool) $updated->available)->toBeFalse()
        ->and(array_filter((array) json_decode((string) $updated->tags, true)))->toBe([]);
});

it('rejects an invalid submission with a validation error, without creating a row', function () {
    generateInstallAndBootFleetModule();
    $actor = fleetActor(['fleet.cars.view', 'fleet.cars.create']);
    $this->actingAs($actor, 'baobab');

    $this->post(route('admin.fleet.cars.store'), [
        'status' => 'draft',
    ])->assertSessionHasErrors('brand');

    expect(DB::table('cars')->count())->toBe(0);
});
