<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Baobab\Studio\Generator\Profiles\ContentTypeProfile;
use Baobab\Studio\Generator\StudioRelationDefinitionGenerator;
use Baobab\Studio\Relations\StudioRelationTargetResolver;
use Illuminate\Support\Str;

/**
 * Transforme un `ContentType` persisté (blueprint validé, `table_name` dérivé —
 * M3 point 1a) en un vrai module Laravel sur disque : `module.json`, migration,
 * modèle, policy, provider, fragment GraphQL (spec 02 §1.2). Ne fait tourner ni
 * la migration ni l'installation — c'est le rôle de `BuildContentType`, qui
 * compose ce générateur avec `InstallModule`/`ActivateModule` (M1, inchangés).
 *
 * **Adaptateur, plus générateur, depuis la Pass A du M8 point 2** (suivi
 * n° 157). Il ne sait plus écrire une migration ni un modèle : il **projette**
 * le Content Type en blueprint de module et confie l'assemblage au moteur
 * commun (`ModuleGenerator`), réglé sur `ContentTypeProfile` — le profil qui
 * porte les conventions de contenu (préfixe `ct_`, socle éditorial du §4.2,
 * policy own/any, GraphQL, aucune surface générée). Ce qui reste ici est
 * exactement ce qui est propre au Content Type : la projection, et les deux
 * régénérations partielles que le cycle de vie du contenu réclame.
 *
 * La signature publique n'a pas bougé — c'est ce qui a permis à ses six
 * appelants (`BuildContentType`, `EvolveContentType`, `CompileGraphqlSchema`,
 * `ContentType`, `ModuleServiceProvider`, `ThemeGenerator`) et à sa suite de
 * tests de rester tels quels au travers de la fusion.
 */
final class ContentTypeModuleGenerator
{
    public function __construct(
        private readonly GeneratedFileChecksums $checksums,
        private readonly ModuleGenerator $modules,
        private readonly FieldRegistry $fields,
        private readonly StudioRelationDefinitionGenerator $relationDefinitions,
        private readonly StudioRelationTargetResolver $relationTargets,
    ) {}

    /**
     * @return string Le "name" (vendor/slug) du module généré, à passer à InstallModule.
     */
    public function __invoke(ContentType $contentType): string
    {
        return $this->generatorFor($contentType)($this->project($contentType));
    }

    /**
     * Régénère uniquement le modèle Eloquent (fillable/casts/relations à jour)
     * — utilisé par `EvolveContentType` (M3 point 4) après une migration
     * incrémentale, sans retoucher `module.json`/policy/provider ni la
     * migration de création d'origine. Passe par le même anti-écrasement par
     * checksum que le reste du générateur.
     */
    public function regenerateModel(ContentType $contentType): void
    {
        $this->rewrite($contentType, ["src/Models/{$contentType->key}.php"]);
    }

    /**
     * Régénère le fragment `.graphql` et son résolveur généré (M7 point 3) —
     * même usage que `regenerateModel()`. Deux appelants : `EvolveContentType`
     * après une évolution de blueprint (le fragment doit refléter les nouveaux
     * champs) et `CompileGraphqlSchema` pour **chaque** type éligible avant de
     * lire son fragment (un Content Type construit avant l'introduction de ce
     * point n'a ni l'un ni l'autre sur disque — bug réel découvert en testant
     * `Book`/`Article`/… en environnement réel, jamais rencontré dans les tests
     * package qui ne construisent que des Content Types frais). Écriture
     * protégée par checksum comme tout le reste : sans effet si le contenu n'a
     * pas changé.
     */
    public function regenerateGraphql(ContentType $contentType): void
    {
        $key = $contentType->key;

        $this->rewrite($contentType, ["graphql/{$key}.graphql", "src/GraphQL/{$key}Resolver.php"]);
    }

    /**
     * Réécrit un sous-ensemble nommé du plan. Le plan est toujours calculé en
     * entier : c'est lui qui garantit que le modèle régénéré est bien celui que
     * produirait une génération complète, et non une seconde version dérivée
     * qui se mettrait à mentir dès qu'un stub change.
     *
     * @param  list<string>  $paths
     */
    private function rewrite(ContentType $contentType, array $paths): void
    {
        $plan = $this->generatorFor($contentType)->plan($this->project($contentType));
        $moduleDir = $contentType->moduleDir();

        foreach ($paths as $path) {
            if (isset($plan[$path])) {
                $this->checksums->write($moduleDir, $path, $plan[$path]);
            }
        }
    }

    private function generatorFor(ContentType $contentType): ModuleGenerator
    {
        return $this->modules->withProfile(new ContentTypeProfile(
            $contentType,
            $this->fields,
            $this->relationDefinitions,
            $this->relationTargets,
        ));
    }

    /**
     * Projette le Content Type en blueprint de module à **une** entité : c'est
     * là toute la traduction entre les deux formats, et elle tient en une
     * méthode parce que le socle commun (champs, relations, table) a toujours
     * eu la même forme des deux côtés — le reste de l'enveloppe de contenu est
     * lu par le profil, directement sur le `ContentType`.
     *
     * Les surfaces sont explicitement coupées : `ContentTypeProfile` le dit
     * déjà par `generatesSurfaces()`, mais un blueprint qui prétendrait le
     * contraire serait un piège pour la première personne qui le relira.
     */
    private function project(ContentType $contentType): ModuleBlueprint
    {
        $key = $contentType->key;
        $dirSlug = Str::kebab(Str::plural($key));

        /** @var array{singular?: string, plural?: string} $label */
        $label = $contentType->blueprint['label'] ?? [];
        $singular = (string) ($label['singular'] ?? $key);
        $plural = (string) ($label['plural'] ?? $key);

        return ModuleBlueprint::fromValidated([
            'identity' => [
                'name' => "content-types/{$dirSlug}",
                'title' => $plural,
                'description' => "Content Type généré : {$singular} / {$plural}.",
                'version' => '1.0.0',
            ],
            'entities' => [[
                'key' => $key,
                'table' => $contentType->table_name,
                'fields' => (array) ($contentType->blueprint['fields'] ?? []),
                'relations' => $this->projectRelations($contentType),
                'routes' => ['admin' => false, 'front' => false, 'api' => false],
            ]],
            'permissions' => ['auto_crud' => false],
        ]);
    }

    /**
     * Une cible de relation de Content Type est nue (`Brand`, `User`) là où le
     * blueprint de module la discrimine (`content_type:Brand`, `core:User`).
     * `StudioRelationTargetResolver` délègue les deux formes préfixées à
     * `RelationTargetResolver` : la cible résolue est donc rigoureusement la
     * même qu'avant la fusion, seule son écriture change.
     *
     * @return list<array<string, mixed>>
     */
    private function projectRelations(ContentType $contentType): array
    {
        $coreModels = RelationTargetResolver::coreModelKeys();

        return array_values(array_map(
            static function (array $relation) use ($coreModels): array {
                $target = (string) $relation['target'];
                $prefix = in_array($target, $coreModels, true) ? 'core' : 'content_type';

                return [...$relation, 'target' => "{$prefix}:{$target}"];
            },
            (array) ($contentType->blueprint['relations'] ?? []),
        ));
    }
}
