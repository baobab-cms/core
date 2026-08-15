<?php

declare(strict_types=1);

namespace Baobab\Studio\Generator;

use Baobab\ContentTypes\Generator\StubRenderer;
use Illuminate\Support\Str;

/**
 * Génère la couche d'actions d'une entité — `Save{Key}` et `Delete{Key}`
 * (suivi n° 141).
 *
 * # Le défaut qu'elle ferme
 *
 * Le CRUD généré écrivait en base depuis les contrôleurs : `{Key}::create(...)`
 * dans celui d'admin, la même chose dans celui de l'API. Deux chemins de code
 * pour une seule opération, ce que le principe 4 interdit — et que la
 * spec-modules §0 étend explicitement au Studio lui-même. Conséquence pour un
 * auteur de module, celle qui a fait surgir le sujet : sa logique métier n'avait
 * **aucun endroit** où se loger sinon le contrôleur, donc dans un fichier généré,
 * donc en conflit de checksum à chaque régénération.
 *
 * # Deux Actions, pas trois
 *
 * `Save{Key}` couvre création et mise à jour, `Delete{Key}` la suppression —
 * exactement `SaveContentEntry`/`DeleteContentEntry`, que le point 2 fusionnera
 * avec ce chemin. Le n° 141 énonçait trois Actions (`Create`/`Update`/`Delete`)
 * tout en donnant les Content Types pour patron ; l'écart a été tranché avec
 * l'utilisateur le 14 août 2026 en faveur du patron réel, générer trois Actions
 * là où le modèle en a deux recréant, en plus petit, l'asymétrie que cette passe
 * existe pour supprimer.
 *
 * # Ce qui n'y entre pas
 *
 * **L'autorisation reste dans les contrôleurs**, qui interrogent la policy avant
 * d'appeler l'Action — c'est ce que fait `ContentController`, et l'interdit
 * « logique own/any hors des policies » l'impose.
 *
 * **L'acteur n'est pas un paramètre.** `AuditLogger::record()` le résout
 * lui-même depuis les guards ; `SaveContentEntry` ne le reçoit que pour poser
 * `author_id`, colonne qu'un blueprint Studio ne déclare pas.
 */
final class EntityActionGenerator
{
    /**
     * @param  array<string, mixed>  $entity
     */
    public function save(array $entity, string $namespace, string $slug): string
    {
        return $this->render('action-save', $entity, $namespace, $slug);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    public function delete(array $entity, string $namespace, string $slug): string
    {
        return $this->render('action-delete', $entity, $namespace, $slug);
    }

    /**
     * Hooks émis par les deux Actions d'une entité, dans l'ordre où on les
     * rencontre. Recopiés tels quels dans le bloc `hooks.emits` du manifeste :
     * un hook émis mais non déclaré serait invisible de `hook:list` et du
     * catalogue d'événements des webhooks, qui lisent l'un et l'autre
     * `manifest['hooks']['emits']`.
     *
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    public function emittedHooks(array $entity, string $slug): array
    {
        $prefix = self::hookPrefix($entity, $slug);

        return ["{$prefix}.saving", "{$prefix}.saved", "{$prefix}.deleted"];
    }

    /**
     * `domaine.objet.evenement` au **singulier** (spec-modules §4.2 :
     * `baobab.user.created`, `shop.order.paid`), là où les permissions de la même
     * entité sont au pluriel (`BlueprintPermissions::prefix()`). Les deux
     * conventions diffèrent dans le Core lui-même — `baobab.content.saved` face à
     * `baobab.system.themes.manage` — et chacune est suivie ici telle qu'elle
     * est.
     *
     * @param  array<string, mixed>  $entity
     */
    public static function hookPrefix(array $entity, string $slug): string
    {
        return $slug.'.'.Str::snake((string) $entity['key']);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function render(string $stub, array $entity, string $namespace, string $slug): string
    {
        $key = (string) $entity['key'];

        return (new StubRenderer)->render(StudioStubs::path($stub), [
            'namespace' => $namespace,
            'key' => $key,
            'var' => Str::camel($key),
            'hook_prefix' => self::hookPrefix($entity, $slug),
        ]);
    }
}
