<?php

use Baobab\Install\Actions\ComposeServerChecklist;
use Baobab\Install\Capability;
use Baobab\Install\ChecklistItem;
use Baobab\Install\HostingProfile;
use Baobab\Install\PhpBinary;
use Baobab\Install\WebServer;
use Illuminate\Filesystem\Filesystem;

/**
 * Spec 15 §7 — la checklist des tâches serveur (Pass C3b1, suivi n° 235).
 *
 * Ce que ces tests éprouvent, c'est la promesse du §4.1 : **gouvernée par le
 * profil**, elle ne propose jamais une manœuvre que l'hébergement détecté
 * interdit, et elle ne tait jamais ce qu'elle ignore. Une checklist qui
 * affirmerait un chemin faux vaudrait moins que pas de checklist du tout : la
 * tâche de cron qu'elle ferait poser échouerait en silence.
 */
beforeEach(function () {
    $this->files = new Filesystem;
    $this->repertoire = sys_get_temp_dir().'/baobab-checklist-'.bin2hex(random_bytes(6));
    $this->composer = new ComposeServerChecklist($this->files);
});

afterEach(function () {
    $this->files->deleteDirectory($this->repertoire);
});

function profil(array $remplacements = []): HostingProfile
{
    return new HostingProfile(
        symlink: $remplacements['symlink'] ?? Capability::Present,
        procOpen: $remplacements['procOpen'] ?? Capability::Present,
        shellAccess: $remplacements['shellAccess'] ?? Capability::Unknown,
        publicIsDocumentRoot: $remplacements['publicIsDocumentRoot'] ?? Capability::Unknown,
        webServer: $remplacements['webServer'] ?? WebServer::Apache,
        argon2id: $remplacements['argon2id'] ?? Capability::Present,
        phpBinary: array_key_exists('phpBinary', $remplacements) ? $remplacements['phpBinary'] : PhpBinary::detect('cli', '/usr/bin/php'),
    );
}

/**
 * @return list<ChecklistItem>
 */
function checklist(array $remplacements = [], array $options = []): array
{
    return (test()->composer)(
        profil($remplacements),
        $options['basePath'] ?? '/home/user/monsite',
        $options['publicPath'] ?? '/home/user/monsite/public',
        $options['appUrl'] ?? 'https://monsite.fr',
        test()->repertoire,
        $options['debug'] ?? false,
        $options['env'] ?? 'production',
    );
}

function item(array $items, string $key): ?ChecklistItem
{
    foreach ($items as $item) {
        if ($item->key === $key) {
            return $item;
        }
    }

    return null;
}

it('rend une ligne de cron complète, prête à coller dans un panneau', function () {
    $cron = item(checklist(), 'cron.scheduler');

    expect($cron?->command)->toBe(
        'cd /home/user/monsite && /usr/bin/php artisan schedule:run >> storage/logs/cron-schedule.log 2>&1'
    );
});

/**
 * Le worker est une **tâche bornée**, pas un démon : c'est ce qui le rend
 * installable là où Supervisor n'existe pas (§7, n° 230).
 */
it('donne le worker en seconde entrée cron, bornée, sur les files du Core', function () {
    $worker = item(checklist(), 'cron.worker');

    expect($worker?->command)->toContain('--stop-when-empty')
        ->and($worker?->command)->toContain('--queue=baobab,baobab-low,default')
        ->and($worker?->command)->toContain('>> storage/logs/cron-queue.log 2>&1');
});

it('ne mentionne Supervisor que là où proc_open existe', function () {
    expect(item(checklist(), 'cron.worker')?->body)->toContain('Supervisor')
        ->and(item(checklist(['procOpen' => Capability::Absent]), 'cron.worker')?->body)
        ->not->toContain('Supervisor');
});

/**
 * B2 du n° 235 : on propose, on n'affirme pas. Un chemin déduit depuis le web
 * porte sa réserve ; constaté en console, il n'en porte aucune.
 */
it('avertit quand le chemin de PHP est déduit et non constaté', function () {
    // Un répertoire de binaires fabriqué pour l'occasion : la détection web
    // regarde le disque, et un test qui interrogerait le PHP de la machine
    // dirait des choses différentes sur un poste et en CI.
    $this->files->ensureDirectoryExists($this->repertoire.'/bin');
    $this->files->put($this->repertoire.'/bin/php', '');

    $devine = PhpBinary::detect('fpm-fcgi', '/usr/sbin/php-fpm', $this->repertoire.'/bin');
    $items = checklist(['phpBinary' => $devine]);

    expect($devine?->confirmed)->toBeFalse()
        ->and(item($items, 'cron.scheduler')?->caveat)->toContain('déduit et non constaté');
});

it('ne propose aucun chemin quand le répertoire des binaires n\'en contient pas', function () {
    $this->files->ensureDirectoryExists($this->repertoire.'/vide');

    expect(PhpBinary::detect('fpm-fcgi', '/usr/sbin/php-fpm', $this->repertoire.'/vide'))->toBeNull();
});

it('ne met aucune réserve sur un chemin constaté en console', function () {
    expect(item(checklist(), 'cron.scheduler')?->caveat)->toBeNull();
});

/**
 * **Un emplacement à compléter plutôt qu'une devinette** : une ligne de cron
 * fausse produit une tâche qui échoue sans rien dire, ce qui est pire que pas
 * de ligne du tout.
 */
it('rend un emplacement à compléter quand PHP reste introuvable', function () {
    $items = checklist(['phpBinary' => null]);

    expect(item($items, 'cron.scheduler')?->command)->toContain('/chemin/vers/php')
        ->and(item($items, 'cron.scheduler')?->caveat)->toContain('panneau');
});

it('écrit la configuration Apache et la nomme, sous Apache', function () {
    $items = checklist(['webServer' => WebServer::Apache]);
    $config = item($items, 'server.config');

    expect($config?->file)->toContain('baobab-htaccess.conf')
        ->and($config?->file)->not->toContain('nginx')
        ->and($this->files->get($this->repertoire.'/baobab-htaccess.conf'))->toContain('X-Content-Type-Options');
});

it('écrit la configuration Nginx, et pas de .htaccess, sous Nginx', function () {
    $items = checklist(['webServer' => WebServer::Nginx]);

    expect(item($items, 'server.config')?->file)->toContain('baobab-nginx.conf')
        ->and(item($items, 'server.config')?->file)->not->toContain('htaccess')
        ->and($this->files->get($this->repertoire.'/baobab-nginx.conf'))->toContain('/home/user/monsite/public');
});

/**
 * Serveur inconnu : les deux formes, et on le dit. Proposer au hasard ferait
 * coller une configuration que le serveur ignore en silence — l'utilisateur
 * croirait son site durci sans qu'il le soit.
 */
it('écrit les deux formes quand le serveur reste inconnu', function () {
    $items = checklist(['webServer' => WebServer::Unknown]);

    expect(item($items, 'server.config')?->file)->toContain('htaccess')
        ->and(item($items, 'server.config')?->file)->toContain('nginx')
        ->and(item($items, 'server.config')?->body)->toContain('les deux formes');
});

/**
 * Le stockage des médias sert des fichiers, il n'en exécute aucun — la règle
 * non négociable de la spec 06 §3.2.
 */
it('interdit l\'exécution de code dans le stockage, dans les deux formats', function () {
    checklist(['webServer' => WebServer::Unknown]);

    expect($this->files->get($this->repertoire.'/baobab-htaccess.conf'))->toContain('RemoveHandler')
        ->and($this->files->get($this->repertoire.'/baobab-nginx.conf'))->toContain('deny all');
});

it('rappelle HTTPS seulement quand le site est en clair', function () {
    expect(item(checklist(), 'https'))->toBeNull()
        ->and(item(checklist([], ['appUrl' => 'http://monsite.fr']), 'https'))->not->toBeNull();
});

/**
 * `APP_DEBUG` en ligne n'est pas une gêne, c'est une divulgation : la moindre
 * erreur affiche le contenu du `.env`.
 */
it('avertit sur APP_DEBUG quand le site est public', function () {
    $avertissement = item(checklist([], ['debug' => true]), 'app.debug');

    expect($avertissement?->warning)->toBeTrue()
        ->and($avertissement?->command)->toContain('APP_DEBUG=false');
});

/**
 * L'heuristique se trompe dans le sens prudent : elle avertit un peu trop,
 * jamais trop peu. Un poste de développement n'est pas un site en ligne.
 */
it('ne confond pas un poste de développement avec un site public', function () {
    foreach (['http://localhost', 'http://monsite.test', 'http://127.0.0.1:8000'] as $url) {
        expect(item(checklist([], ['debug' => true, 'appUrl' => $url]), 'app.debug'))->toBeNull();
    }
});

/**
 * B3 du n° 235 : pas de lien mort. Aucune documentation publique n'existe, et
 * un lien vers rien sur l'écran de fin donne l'impression d'un produit
 * inachevé au moment précis où l'on veut rassurer.
 */
it('ne renvoie vers aucune documentation tant qu\'il n\'y en a pas', function () {
    $premiers = item(checklist(), 'first-steps');

    expect($premiers?->body)->toContain('/admin')
        ->and($premiers?->body)->not->toContain('documentation');
});
