<?php

use Baobab\Install\HostingProfile;
use Baobab\Install\Http\Middleware\EnsureNotInstalled;
use Baobab\Install\InstallationState;
use Baobab\Install\InstallSession;
use Baobab\Install\InstallToken;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Spec 15 §6.1 et §6.2 — le socle web du wizard (Pass C1, suivi n° 223).
 *
 * Ce que ces tests éprouvent, c'est **la garde**, pas l'installation : aucune
 * étape du §4 n'est jouée ici. Trois propriétés, dans l'ordre où elles
 * protègent : les routes n'existent pas sur un site installé, le jeton est
 * exigé et jamais divulgué, et une seule session d'installation avance à la
 * fois.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->repertoire = sys_get_temp_dir().'/baobab-install-web-'.bin2hex(random_bytes(6));
    $this->files->ensureDirectoryExists($this->repertoire);

    config()->set('baobab.install.state_path', $this->repertoire);

    // Les liaisons lisent le chemin à la résolution : sans oubli explicite,
    // le test travaillerait sur le répertoire du test précédent.
    app()->forgetInstance(InstallationState::class);
    app()->forgetInstance(InstallToken::class);
});

afterEach(function () {
    $this->files->deleteDirectory($this->repertoire);
});

it('refuse les écrans d\'installation dès qu\'un lock existe', function () {
    $state = new InstallationState($this->files, $this->repertoire);
    $state->markInstalled('1.0.0', HostingProfile::detect($this->repertoire), 'checksum');

    $middleware = new EnsureNotInstalled($state);

    expect(fn () => $middleware->handle(Request::create('/install'), fn () => response('ok')))
        ->toThrow(NotFoundHttpException::class);
});

/**
 * L'assertion qui compte est ici la **négative** : un middleware qui laisserait
 * tout passer ferait aussi passer le test ci-dessus si celui-ci n'existait pas.
 */
it('laisse passer tant qu\'aucun lock n\'existe', function () {
    $middleware = new EnsureNotInstalled(new InstallationState($this->files, $this->repertoire));

    $reponse = $middleware->handle(Request::create('/install'), fn () => response('ok'));

    expect($reponse->getContent())->toBe('ok');
});

it('crée le jeton au premier accès et ne le montre jamais au navigateur', function () {
    $chemin = $this->repertoire.'/install-token.txt';

    expect($this->files->isFile($chemin))->toBeFalse();

    $reponse = $this->get('/install');

    $reponse->assertOk();
    expect($this->files->isFile($chemin))->toBeTrue();

    // §6.2 : « aucune donnée sensible renvoyée au navigateur ». Le chemin est
    // affiché — il aide qui a le serveur et n'apprend rien aux autres — mais
    // la valeur ne doit apparaître nulle part dans la page.
    $jeton = trim($this->files->get($chemin));
    expect($jeton)->not->toBe('');
    $reponse->assertDontSee($jeton);
    $reponse->assertSee('install-token.txt');
});

it('refuse un code faux sans ouvrir de session', function () {
    $this->get('/install');

    $this->post('/install', ['token' => 'pas-le-bon-code'])
        ->assertRedirect()
        ->assertSessionHasErrors('token');

    expect(app(InstallSession::class)->isAuthenticated())->toBeFalse();
});

it('ouvre la session et prend le verrou sur le bon code', function () {
    $this->get('/install');
    $jeton = trim($this->files->get($this->repertoire.'/install-token.txt'));

    $this->post('/install', ['token' => $jeton])
        ->assertRedirect(route('baobab.install.index'));

    expect($this->files->isFile($this->repertoire.'/install-session.json'))->toBeTrue();

    $this->get('/install/start')->assertOk();
});

it('renvoie à la porte quand on vise un écran interne sans session', function () {
    $this->get('/install/start')->assertRedirect(route('baobab.install.gate'));
});

/**
 * §6.2, « verrou exclusif ». Le verrou est simulé en écrivant le fichier d'un
 * autre détenteur : c'est exactement ce que laisserait un second navigateur, et
 * ça évite de dépendre d'une gymnastique de sessions dans le test.
 */
it('refuse une seconde installation tant qu\'une autre session tient le verrou', function () {
    $this->get('/install');
    $jeton = trim($this->files->get($this->repertoire.'/install-token.txt'));

    $this->files->put($this->repertoire.'/install-session.json', (string) json_encode([
        'id' => 'un-autre-navigateur',
        'seen_at' => time(),
    ]));

    $this->post('/install', ['token' => $jeton])
        ->assertRedirect()
        ->assertSessionHasErrors('token');
});

/**
 * Un verrou qui n'expire pas condamnerait le site au premier onglet fermé, et
 * la seule issue serait de supprimer un fichier sur le serveur — précisément
 * ce que l'utilisateur d'un mutualisé fait le plus mal.
 */
it('reprend un verrou abandonné passé le délai', function () {
    $this->get('/install');
    $jeton = trim($this->files->get($this->repertoire.'/install-token.txt'));

    $this->files->put($this->repertoire.'/install-session.json', (string) json_encode([
        'id' => 'un-navigateur-parti',
        'seen_at' => time() - 3601,
    ]));

    $this->post('/install', ['token' => $jeton])
        ->assertRedirect(route('baobab.install.index'));
});

it('coupe court quand le verrou a changé de main en cours de route', function () {
    $session = app(InstallSession::class);

    expect($session->claim())->toBeTrue();

    $this->files->put($this->repertoire.'/install-session.json', (string) json_encode([
        'id' => 'quelqu-un-d-autre',
        'seen_at' => time(),
    ]));

    expect($session->touch())->toBeFalse();
});

it('ne rend pas le jeton à qui le demande deux fois', function () {
    $token = app(InstallToken::class);

    $premier = $token->value();
    $second = $token->value();

    expect($second)->toBe($premier)
        ->and(strlen($premier))->toBe(32);
});

it('exige que le code corresponde exactement', function () {
    $token = app(InstallToken::class);
    $jeton = $token->value();

    expect($token->matches($jeton))->toBeTrue()
        ->and($token->matches(' '.$jeton.' '))->toBeTrue()
        ->and($token->matches(substr($jeton, 0, -1)))->toBeFalse()
        ->and($token->matches(''))->toBeFalse();
});

it('efface le jeton sans se plaindre s\'il a déjà disparu', function () {
    $token = app(InstallToken::class);
    $token->value();

    $token->forget();
    $token->forget();

    expect($this->files->isFile($this->repertoire.'/install-token.txt'))->toBeFalse();
});

/**
 * §6.2, « throttling agressif sur `/install/*` ». Cinq essais par minute sur la
 * porte : c'est un secret de 128 bits qu'on y devine, et cinq suffisent à qui
 * recopie un code à la main.
 */
it('coupe la porte après cinq tentatives dans la minute', function () {
    $this->get('/install');

    foreach (range(1, 5) as $ignore) {
        $this->post('/install', ['token' => 'faux']);
    }

    $this->post('/install', ['token' => 'faux'])->assertStatus(429);
});

/**
 * Le 429 par défaut de Laravel est une page nue. Au milieu d'une installation,
 * elle laisse quelqu'un sans savoir s'il a cassé le site, combien de temps
 * attendre, ni où retrouver son code — constaté en recette le 26 août 2026.
 *
 * Ce test verrouille les trois informations, et **l'absence** de la quatrième :
 * le nombre de tentatives restantes ne doit jamais être dit, il ne renseignerait
 * que celui qui tâtonne.
 */
it('explique le refus au lieu de rendre une page nue', function () {
    $this->get('/install');

    foreach (range(1, 5) as $ignore) {
        $this->post('/install', ['token' => 'faux']);
    }

    $reponse = $this->post('/install', ['token' => 'faux']);

    $reponse->assertStatus(429)
        ->assertSee('Trop de tentatives')
        ->assertSee('install-token.txt')
        ->assertSee('seconde', false);

    expect($reponse->headers->get('Retry-After'))->not->toBeNull();
});

/**
 * Le jeton ne doit pas fuir par la page d'erreur : elle en donne le chemin,
 * comme la porte, et jamais la valeur.
 */
it('ne divulgue pas le jeton sur la page de refus', function () {
    $this->get('/install');
    $jeton = trim($this->files->get($this->repertoire.'/install-token.txt'));

    foreach (range(1, 5) as $ignore) {
        $this->post('/install', ['token' => 'faux']);
    }

    $this->post('/install', ['token' => 'faux'])
        ->assertStatus(429)
        ->assertDontSee($jeton);
});
