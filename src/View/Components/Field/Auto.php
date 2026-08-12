<?php

declare(strict_types=1);

namespace Baobab\View\Components\Field;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Support\FieldDisplay;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;

/**
 * `<x-baobab::field.auto :entry="$entry" role="body" />` (spec 19 §5.10,
 * amendement n° 17) — le pont entre ce qu'un template de thème **reçoit** et
 * ce que le catalogue d'affichage sait rendre.
 *
 * **Pourquoi ce composant existe.** Un template ne reçoit du Core que `entry`
 * et `title` (spec 03 §4) : il ne connaît ni le blueprint, ni le type des
 * champs, et l'interdit de requête Eloquent en vue lui ferme la porte.
 * Élargir la charge utile du filtre public `baobab.render.data` ferait
 * dépendre tout module du blueprint pour un simple besoin d'affichage (suivi
 * n° 122). La résolution vit donc ici, en PHP, comme pour `Menu`,
 * `WidgetZone` et `GalleryDisplay`.
 *
 * **Les rôles plutôt qu'une liste de champs.** §5.4 exige un noyau explicite
 * — titre, date, contenu — et §5.10 la consommation de tout le catalogue par
 * un composant unique ; les deux se recouvrent, et le blueprint ne déclare
 * nulle part quel champ est « le contenu ». Le template déclare donc ce qu'il
 * veut voir et dans quel ordre, sans jamais connaître une clé de champ. Les
 * règles de résolution, elles, sont dans `FieldDisplay`, partagé avec
 * l'admin.
 *
 * **L'asymétrie de dispatch est absorbée ici.** Quinze composants d'affichage
 * prennent `:value`, `gallery-display` prend `:entry` + `field` (le champ ne
 * stocke aucune colonne propre). Cette bifurcation vivait dupliquée dans
 * `ThemeGenerator::fieldBlock()` ; un futur type de champ à la même forme n'a
 * désormais qu'un seul endroit à corriger.
 */
final class Auto extends Component
{
    public const ROLES = ['image', 'body', 'excerpt', 'rest', 'relations'];

    /**
     * Rôle `excerpt` : le corps débarrassé de ses balises et tronqué. Une
     * carte de liste ne transporte ainsi jamais l'article entier — et le
     * `line-clamp` qui aurait pu l'imiter en CSS s'applique mal à du HTML
     * riche, où un titre ou une image comptent pour une ligne.
     */
    public ?string $excerpt = null;

    /**
     * Champ unique des rôles `image` et `body`, `null` si le type n'en porte
     * pas ou si la valeur est vide — un rôle sans matière ne rend rien, il ne
     * laisse pas de trou (§5.9).
     *
     * @var array{component: string, attributes: ComponentAttributeBag}|null
     */
    public ?array $field = null;

    /**
     * Champs du rôle `rest`, dans l'ordre de déclaration du blueprint.
     *
     * @var list<array{key: string, label: string, component: string, attributes: ComponentAttributeBag}>
     */
    public array $fields = [];

    /**
     * Taxonomies du rôle `relations` : les entrées liées, titrées et liées à
     * leur page quand leur type est adressable.
     *
     * @var list<array{label: string, terms: list<array{title: string, url: string|null}>}>
     */
    public array $taxonomies = [];

    public function __construct(
        public Model $entry,
        public string $role = 'rest',
        public int $limit = 160,
    ) {
        $contentType = ContentType::forModelClass($entry::class);

        if ($contentType === null) {
            // Une entrée qui n'appartient à aucun Content Type connu (modèle
            // Core, fixture de test) n'a pas de blueprint : le composant ne
            // rend rien plutôt que de deviner.
            return;
        }

        $blueprint = $contentType->blueprint;

        match ($this->role) {
            'image' => $this->field = $this->single(FieldDisplay::image($blueprint)),
            'body' => $this->field = $this->single(FieldDisplay::body($blueprint)),
            'excerpt' => $this->excerpt = $this->resolveExcerpt($blueprint),
            'relations' => $this->taxonomies = $this->resolveTaxonomies($blueprint),
            default => $this->fields = $this->resolveRest($blueprint),
        };
    }

    public function render(): View
    {
        return view('baobab::components.field.auto');
    }

    /**
     * Le corps en texte nu, tronqué proprement. `strip_tags` avant troncature
     * et non l'inverse : couper d'abord produirait une balise ouverte à
     * mi-chemin, donc du HTML cassé dès que l'extrait est rendu tel quel.
     *
     * @param  array<string, mixed>  $blueprint
     */
    private function resolveExcerpt(array $blueprint): ?string
    {
        $field = FieldDisplay::body($blueprint);

        if ($field === null) {
            return null;
        }

        $value = $this->entry->getAttribute((string) ($field['key'] ?? ''));

        if (! is_string($value)) {
            return null;
        }

        // Les balises deviennent des **espaces**, elles ne disparaissent pas :
        // `strip_tags` seul recolle les blocs entre eux (« …titre</h2><p>Une… »
        // donne « titreUne »), ce qui fabrique des mots qui n'existent pas.
        $text = trim(preg_replace('/\s+/u', ' ', (string) preg_replace('/<[^>]*>/', ' ', $value)) ?? '');

        return $text === '' ? null : Str::limit($text, $this->limit);
    }

    /**
     * @param  array<string, mixed>|null  $field
     * @return array{component: string, attributes: ComponentAttributeBag}|null
     */
    private function single(?array $field): ?array
    {
        if ($field === null) {
            return null;
        }

        $rendered = $this->describe($field);

        return $rendered === null ? null : [
            'component' => $rendered['component'],
            'attributes' => $rendered['attributes'],
        ];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return list<array{key: string, label: string, component: string, attributes: ComponentAttributeBag}>
     */
    private function resolveRest(array $blueprint): array
    {
        $resolved = [];

        foreach (FieldDisplay::rest($blueprint) as $field) {
            $rendered = $this->describe($field);

            if ($rendered !== null) {
                $resolved[] = $rendered;
            }
        }

        return $resolved;
    }

    /**
     * Décrit un champ pour la vue : quel composant l'affiche, avec quels
     * attributs. Rend `null` quand la valeur est vide — un champ sans valeur
     * ne produit pas de ligne vide sous une étiquette (§5.9).
     *
     * @param  array<string, mixed>  $field
     * @return array{key: string, label: string, component: string, attributes: ComponentAttributeBag}|null
     */
    private function describe(array $field): ?array
    {
        $key = (string) ($field['key'] ?? '');
        $type = (string) ($field['type'] ?? '');
        $registry = app(FieldRegistry::class);

        if ($key === '' || ! $registry->has($type)) {
            return null;
        }

        // Un champ `gallery` ne stocke aucune colonne propre : ses sélections
        // vivent dans `media_usages`, et son composant prend l'entrée plutôt
        // qu'une valeur. C'est la seule exception du catalogue.
        if ($type === 'gallery') {
            $attributes = new ComponentAttributeBag(['entry' => $this->entry, 'field' => $key]);
        } else {
            $value = $this->entry->getAttribute($key);

            if ($value === null || $value === '' || $value === []) {
                return null;
            }

            $attributes = new ComponentAttributeBag(['value' => $value]);
        }

        return [
            'key' => $key,
            'label' => FieldDisplay::label($field),
            'component' => $registry->resolve($type)->displayComponent(),
            'attributes' => $attributes,
        ];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return list<array{label: string, terms: list<array{title: string, url: string|null}>}>
     */
    private function resolveTaxonomies(array $blueprint): array
    {
        $resolved = [];

        foreach (FieldDisplay::taxonomies($blueprint) as $relation) {
            $key = (string) ($relation['key'] ?? '');
            $method = Str::camel($key);

            if ($key === '' || ! method_exists($this->entry, $method)) {
                continue;
            }

            $related = $this->entry->{$method}();

            if (! $related instanceof BelongsToMany) {
                continue;
            }

            $terms = $this->terms($related, (string) ($relation['target'] ?? ''));

            if ($terms !== []) {
                $resolved[] = ['label' => Str::headline($key), 'terms' => $terms];
            }
        }

        return $resolved;
    }

    /**
     * @param  BelongsToMany<Model, Model>  $related
     * @return list<array{title: string, url: string|null}>
     */
    private function terms(BelongsToMany $related, string $target): array
    {
        $targetType = ContentType::where('key', $target)->first();
        $titleField = null;
        $prefix = null;

        if ($targetType instanceof ContentType) {
            $titleField = FieldDisplay::titleKey($targetType->blueprint);
            $prefix = $targetType->is_addressable ? $targetType->urlPrefix() : null;
        }

        $terms = [];

        foreach ($related->get() as $entry) {
            $title = $titleField === null ? null : $entry->getAttribute($titleField);
            $slug = $entry->getAttribute('slug');

            $terms[] = [
                'title' => (string) ($title ?? $slug ?? ''),
                'url' => $prefix !== null && is_string($slug)
                    ? route('baobab.public.show', ['prefix' => $prefix, 'slug' => $slug])
                    : null,
            ];
        }

        return array_values(array_filter($terms, static fn (array $term): bool => $term['title'] !== ''));
    }
}
