<?php

declare(strict_types=1);

namespace Baobab\Media\Models;

use Baobab\Media\Conversions\MediaVariantResolver;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property string $file_name
 * @property string $mime_type
 * @property string $source
 * @property string|null $external_url
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $title
 * @property string|null $alt
 * @property string|null $caption
 * @property string|null $description
 * @property string $checksum
 * @property int|null $folder_id
 * @property int|null $author_id
 * @property array<string, mixed> $conversions
 * @property array<string, mixed> $meta
 * @property float|null $focal_x
 * @property float|null $focal_y
 * @property string|null $edited_path
 * @property int|null $edited_width
 * @property int|null $edited_height
 */
class Media extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'disk',
        'path',
        'file_name',
        'mime_type',
        'source',
        'external_url',
        'size',
        'width',
        'height',
        'title',
        'alt',
        'caption',
        'description',
        'checksum',
        'folder_id',
        'author_id',
        'conversions',
        'meta',
        'focal_x',
        'focal_y',
        'edited_path',
        'edited_width',
        'edited_height',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'conversions' => 'array',
            'meta' => 'array',
            'focal_x' => 'float',
            'focal_y' => 'float',
            'edited_width' => 'integer',
            'edited_height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * Modèles qui référencent ce média (spec 06 §5) — matérialisée en table
     * réelle (`media_usages`), alimentée par SyncMediaUsagesFromEntry pour
     * les champs `richtext`.
     *
     * @return HasMany<MediaUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    /**
     * URL de la variante WebP d'un preset (spec 06 §4.1) — génère et persiste
     * à la volée si le preset n'a pas encore de variante (déclaré après
     * coup). Logique dans MediaVariantResolver, cette méthode n'est qu'un
     * point d'accès pratique depuis le modèle.
     */
    public function variantUrl(string $preset): ?string
    {
        return app(MediaVariantResolver::class)->resolve($this, $preset);
    }

    /**
     * Largeur/hauteur de la version courante (éditée si elle existe, sinon
     * l'original) — l'original lui-même (`width`/`height`) n'est jamais
     * remplacé par une édition (spec 06 §4.2, « l'original reste »).
     */
    public function currentWidth(): ?int
    {
        return $this->edited_width ?? $this->width;
    }

    public function currentHeight(): ?int
    {
        return $this->edited_height ?? $this->height;
    }

    /**
     * URL de la version courante (éditée si elle existe, sinon l'original) —
     * vues et Actions ne doivent jamais résoudre de chemin Storage
     * elles-mêmes (spec 06 §1.1).
     */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->edited_path ?? $this->path);
    }

    /**
     * Affichable par une balise <img> (SVG compris — la grille l'affiche
     * tel quel, le navigateur sait le rendre).
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Média externe (spec 06 §7.1) : référence une URL tierce résolue par
     * oEmbed. Le fichier stocké (`path`) est sa vignette, le lecteur embarqué
     * vit dans meta.oembed.html.
     */
    public function isExternal(): bool
    {
        return $this->source === 'external';
    }

    /**
     * HTML du lecteur embarqué renvoyé par le fournisseur oEmbed (iframe).
     * Fournisseurs sur liste blanche uniquement (OEmbedResolver) — jamais du
     * HTML saisi par un utilisateur.
     */
    public function externalEmbedHtml(): ?string
    {
        $html = $this->meta['oembed']['html'] ?? null;

        return is_string($html) ? $html : null;
    }

    /**
     * Éligible à l'éditeur pixel (point focal, recadrage/rotation/
     * retournement) — un SVG n'a pas de sens à redimensionner en pixels,
     * même logique que GenerateMediaConversions::isRasterImage().
     */
    public function isRasterImage(): bool
    {
        return $this->isImage() && $this->mime_type !== 'image/svg+xml';
    }
}
