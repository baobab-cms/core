<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Demo\Actions\SeedDemoContent;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/**
 * Écran « Contenu de démonstration » (spec 03 §7, M8 point 3, Pass D3) —
 * adaptateur mince sur `RemoveDemoContent` (Pass D2b, suivi n° 252). Ce que
 * ces tests gardent : la permission dédiée gouverne les deux routes, l'écran
 * distingue présent/absent, et le retrait se voit réellement en base.
 */
function demoContentActor(bool $withPermission = true): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Demo Content Actor {$counter}",
        'email' => "demo-content-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    if ($withPermission) {
        app(GrantPermission::class)($user, 'baobab.system.demo_content.manage');
    }

    return $user;
}

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('denies both routes without baobab.system.demo_content.manage', function () {
    $actor = demoContentActor(false);

    $this->actingAs($actor, 'baobab')->get(route('admin.demo-content.index'))->assertForbidden();
    $this->actingAs($actor, 'baobab')->delete(route('admin.demo-content.destroy'))->assertForbidden();
});

it('montre l\'état absent quand rien n\'a été posé', function () {
    $this->actingAs(demoContentActor(), 'baobab')
        ->get(route('admin.demo-content.index'))
        ->assertOk()
        ->assertSee(__('baobab::admin.demo_content.absent_title'))
        ->assertDontSee(__('baobab::admin.demo_content.remove_action'));
});

it('montre l\'état présent et le bouton de retrait quand du contenu a été posé', function () {
    $actor = demoContentActor();
    app(SeedDemoContent::class)($actor);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.demo-content.index'))
        ->assertOk()
        ->assertSee(__('baobab::admin.demo_content.present_title'))
        ->assertSee(__('baobab::admin.demo_content.remove_action'));
});

it('retire le contenu de démonstration sur HTTP', function () {
    $actor = demoContentActor();
    app(SeedDemoContent::class)($actor);

    $article = ContentType::where('key', 'Article')->firstOrFail();
    /** @var class-string<Model> $articleModel */
    $articleModel = $article->modelClass();

    expect($articleModel::query()->count())->toBe(3);

    $this->actingAs($actor, 'baobab')
        ->delete(route('admin.demo-content.destroy'))
        ->assertRedirect(route('admin.demo-content.index'))
        ->assertSessionHas('toast');

    expect($articleModel::query()->count())->toBe(0);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.demo-content.index'))
        ->assertOk()
        ->assertSee(__('baobab::admin.demo_content.absent_title'));
});
