<?php

declare(strict_types=1);

namespace Baobab\Studio\Models;

use Baobab\Modules\Models\Module;
use Baobab\Studio\Blueprint\BlueprintMigrations;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Brouillon de blueprint de module en cours de saisie dans le Wizard Studio
 * (spec-modules §5.4) — distinct du value object `ModuleBlueprint` exactement
 * comme `ContentType` (Eloquent) l'est de `ContentTypeBlueprint`. Contrairement
 * à `ContentType`, ce modèle existe **avant** toute génération : `module_id`
 * et `generated_at` ne sont renseignés qu'une fois le module effectivement
 * généré et installé (Pass B).
 *
 * @property int $id
 * @property string $vendor_slug
 * @property string $title
 * @property int $blueprint_version
 * @property int $current_step
 * @property array<string, mixed> $blueprint
 * @property int|null $module_id
 * @property Carbon|null $generated_at
 */
class ModuleBlueprintDraft extends Model
{
    protected $table = 'module_blueprints';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'vendor_slug',
        'title',
        'blueprint_version',
        'current_step',
        'blueprint',
        'module_id',
        'generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blueprint' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function isGenerated(): bool
    {
        return $this->generated_at !== null;
    }

    /**
     * Applique les migrateurs de blueprint enregistrés (décision 5, spec 01
     * §7.1) avant toute lecture — no-op tant qu'aucun migrateur n'est
     * enregistré (v1).
     *
     * @return array<string, mixed>
     */
    public function migratedBlueprint(): array
    {
        return app(BlueprintMigrations::class)->migrate($this->blueprint);
    }

    /**
     * Blueprint courant sous forme de value object, validé de façon
     * permissive (état de brouillon — cf. `ModuleBlueprint::fromDraftJson()`).
     */
    public function asBlueprint(): ModuleBlueprint
    {
        return ModuleBlueprint::fromDraftJson((string) json_encode($this->migratedBlueprint()));
    }

    /**
     * Répertoire du module une fois généré (convention Pass B : racine
     * `baobab.studio.modules_path`, un seul niveau pour rester découvrable par
     * le glob `modules/*` — même contrainte que `ContentType::moduleDir()`).
     */
    public function moduleDir(): string
    {
        $dirSlug = Str::slug(str_replace('/', '-', $this->vendor_slug));

        return rtrim((string) config('baobab.studio.modules_path'), '/')."/{$dirSlug}";
    }
}
