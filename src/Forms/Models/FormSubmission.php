<?php

declare(strict_types=1);

namespace Baobab\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une soumission (spec 14 §6.1). `form_version` fige la version du blueprint
 * au moment de l'envoi — une soumission ancienne s'affiche avec les libellés
 * d'époque, jamais recalculée contre le formulaire courant.
 *
 * @property int $id
 * @property int $form_id
 * @property int $form_version
 * @property array<string, mixed> $payload
 * @property Carbon|null $consent_at
 * @property string|null $ip
 * @property string $status
 */
class FormSubmission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'form_version',
        'payload',
        'consent_at',
        'ip',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'form_version' => 'integer',
            'payload' => 'array',
            'consent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
