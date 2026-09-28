<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Models;

use Baobab\Modules\Models\Module;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $key
 * @property string $table_name
 * @property bool $is_addressable
 * @property int|null $module_id
 * @property int $version
 * @property array<string, mixed> $blueprint
 */
class ContentType extends Model
{
    /**
     * Mémoïse `self::all()` pour `forModelClass()` — invalidée à chaque
     * écriture (suivi n° 364) : appelée une à trois fois **par entrée rendue**
     * (`EntryLink`, `Field\Auto`), elle interrogeait la table entière à chaque
     * fois plutôt qu'une fois par requête, sur une table qui change rarement
     * en cours de rendu.
     *
     * @var Collection<int, self>|null
     */
    private static ?Collection $cachedAll = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'table_name',
        'is_addressable',
        'module_id',
        'version',
        'blueprint',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_addressable' => 'boolean',
            'blueprint' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static function (): void {
            self::$cachedAll = null;
        });

        static::deleted(static function (): void {
            self::$cachedAll = null;
        });
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * FQCN du modèle Eloquent généré (convention de ContentTypeModuleGenerator :
     * namespace `Modules\{Key}`, classe `Models\{Key}`).
     */
    public function modelClass(): string
    {
        return "Modules\\{$this->key}\\Models\\{$this->key}";
    }

    /**
     * Le Content Type propriétaire d'une classe de modèle Eloquent générée
     * (relation inverse de `modelClass()`) — utilisé pour retrouver le
     * préfixe d'URL d'une entrée dont on n'a que l'instance (ex. résolution
     * d'un item de menu pointant vers un contenu, spec 10 §2.2). Aucune
     * colonne dédiée à interroger : le nombre de Content Types reste modeste,
     * un balayage en mémoire suffit.
     *
     * @param  class-string<Model>  $class
     */
    public static function forModelClass(string $class): ?self
    {
        return (self::$cachedAll ??= self::all())
            ->first(fn (self $contentType): bool => $contentType->modelClass() === $class);
    }

    /**
     * Répertoire du module généré (convention de ContentTypeModuleGenerator :
     * racine `baobab.content_types.modules_path`, dossier `content-{slug}`).
     */
    public function moduleDir(): string
    {
        $dirSlug = Str::kebab(Str::plural($this->key));

        return rtrim((string) config('baobab.content_types.modules_path'), '/')."/content-{$dirSlug}";
    }

    /**
     * Workflow de validation à un niveau activé (spec 09 §5) — utilisé par
     * ContentStateMachine pour n'ouvrir submit/pending/approve/reject que sur
     * les types qui l'ont explicitement demandé dans leur blueprint.
     */
    public function workflowEnabled(): bool
    {
        return (bool) ($this->blueprint['workflow'] ?? false);
    }

    /**
     * Colonne de convention optionnelle `unpublish_at` (spec 09 §4) telle que
     * déclarée au blueprint — activée par défaut. C'est l'intention, pas
     * l'état réel de la table : utilisée par ContentTypeModuleGenerator au
     * moment de la génération (la colonne n'existe pas encore, décider sur
     * sa seule présence en base serait toujours faux). Le code qui lit/écrit
     * sur un type déjà construit doit passer par unpublishAtColumnExists().
     */
    public function unpublishAtEnabled(): bool
    {
        return (bool) ($this->blueprint['unpublish_at'] ?? true);
    }

    /**
     * Comme unpublishAtEnabled(), mais vérifie en plus que la colonne existe
     * réellement sur la table déjà construite — un type construit avant
     * l'introduction de cette option ne l'a pas (EvolveContentType ne
     * rattrape pas les colonnes de convention, suivi n° 37). Sans cette
     * double vérification, le formulaire admin tenterait d'écrire une
     * colonne inexistante et l'enregistrement échouerait en base. Utilisée
     * par ContentController (injection du champ dans le formulaire) et
     * ArchiveDueContentEntries (planification).
     */
    public function unpublishAtColumnExists(): bool
    {
        return $this->unpublishAtEnabled() && Schema::hasColumn($this->table_name, 'unpublish_at');
    }

    /**
     * Quota de révisions (spec 09 §6) — `null` au blueprint retombe sur le
     * réglage global `baobab.content.revisions_limit`. `0` désactive les
     * révisions sur ce type.
     */
    public function revisionsLimit(): int
    {
        $declared = $this->blueprint['revisions']['limit'] ?? null;

        return $declared ?? (int) config('baobab.content.revisions_limit', 50);
    }

    public function revisionsEnabled(): bool
    {
        return $this->revisionsLimit() !== 0;
    }

    /**
     * @return list<string>
     */
    public function revisionsExcept(): array
    {
        return $this->blueprint['revisions']['except'] ?? [];
    }

    /**
     * Préfixe d'URL public (spec 07 §3, spec 03 §3) — repli kebab-pluriel de
     * la clé si non déclaré au blueprint. Non significatif si
     * `is_addressable` est faux. Utilisé par PublicRouteRegistrar.
     */
    public function urlPrefix(): string
    {
        return $this->blueprint['url_prefix'] ?? Str::kebab(Str::plural($this->key));
    }

    /**
     * Sous-ensemble des champs du blueprint marqués `exposed_in_api` (spec
     * 02 §3, spec 08 §2.1) — seuls ces champs apparaissent dans les
     * Resources REST et sont éligibles à `?filter[]`/`?sort=`. Vrai par
     * défaut : un champ reste exposé tant qu'il n'en est pas retiré
     * explicitement.
     *
     * @return list<array<string, mixed>>
     */
    public function apiExposedFields(): array
    {
        return array_values(array_filter(
            (array) ($this->blueprint['fields'] ?? []),
            static fn (array $field): bool => $field['exposed_in_api'] ?? true,
        ));
    }

    /**
     * Lecture publique sans authentification des entrées `published` de ce
     * type (spec 08 §2.3) — n'a de sens que si `is_addressable` est vrai ;
     * activée par défaut (« désactivable par type »).
     */
    public function publicApiReadEnabled(): bool
    {
        return (bool) ($this->blueprint['public_api_read'] ?? true);
    }

    /**
     * Interrupteur API par Content Type (spec 08 §4.3, M7 point 2 Pass B) —
     * désactivé, le type devient invisible pour l'API (404), indépendamment
     * des permissions. Activé par défaut.
     */
    public function apiEnabled(): bool
    {
        return (bool) ($this->blueprint['api_enabled'] ?? true);
    }

    /**
     * Sous-ensemble des champs du blueprint marqués `searchable` (spec 11
     * §3.1) — **opt-in**, contrairement à `exposed_in_api` (opt-out) : patron
     * exact `ContentTypeBlueprint::searchableFields()`.
     *
     * @return list<array<string, mixed>>
     */
    public function searchableFields(): array
    {
        return array_values(array_filter(
            (array) ($this->blueprint['fields'] ?? []),
            static fn (array $field): bool => $field['searchable'] ?? false,
        ));
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function fieldWeight(array $field): int
    {
        return (int) ($field['weight'] ?? 1);
    }
}
