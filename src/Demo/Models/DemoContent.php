<?php

declare(strict_types=1);

namespace Baobab\Demo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une trace laissée par le contenu de démonstration (spec 03 §7).
 *
 * Deux natures, distinguées par ce qui est renseigné plutôt que par une
 * colonne de type : une **création** porte `demoable`, une **modification**
 * porte `setting_key` et la valeur d'avant. Le retrait supprime les
 * premières et restaure les secondes — et ne restaure que si le réglage
 * porte encore ce que la démonstration y avait mis, sans quoi il écraserait
 * un choix fait depuis (suivi n° 247, arbitrage D-D).
 *
 * @property int $id
 * @property string|null $demoable_type
 * @property int|null $demoable_id
 * @property string|null $setting_key
 * @property array<string, mixed>|null $previous_value
 */
class DemoContent extends Model
{
    protected $table = 'demo_content';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'demoable_type',
        'demoable_id',
        'setting_key',
        'previous_value',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function demoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Note une ligne créée par la démonstration, quelle que soit sa table.
     */
    public static function markCreated(Model $model): self
    {
        return self::create([
            'demoable_type' => $model::class,
            'demoable_id' => $model->getKey(),
        ]);
    }

    /**
     * Note un réglage que la démonstration s'apprête à modifier, avec ce
     * qu'il valait avant.
     *
     * **Le premier passage gagne.** Un seeder rejoué ne doit pas écraser la
     * valeur d'origine par celle qu'il avait lui-même posée : ce serait
     * enregistrer son propre effet comme état antérieur, et le retrait ne
     * saurait plus vers quoi revenir.
     *
     * @param  array<string, mixed>  $previous
     */
    public static function markSetting(string $key, array $previous): self
    {
        return self::firstOrCreate(
            ['setting_key' => $key],
            ['previous_value' => $previous],
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['previous_value' => 'array'];
    }
}
