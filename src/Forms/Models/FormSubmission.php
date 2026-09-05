<?php

declare(strict_types=1);

namespace Baobab\Forms\Models;

use Baobab\Forms\FormSubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une soumission (spec 14 §6.1). `form_version` fige la version du blueprint
 * au moment de l'envoi ; `blueprint_snapshot` fige les champs eux-mêmes
 * (ajouté en Pass B5, spec 14 §6.1 : « les libellés d'époque sont résolus
 * depuis la version archivée du blueprint ») — une soumission ancienne
 * s'affiche avec les libellés d'époque, jamais recalculée contre le
 * formulaire courant.
 *
 * @property int $id
 * @property int $form_id
 * @property int $form_version
 * @property list<array<string, mixed>>|null $blueprint_snapshot
 * @property array<string, mixed> $payload
 * @property Carbon|null $consent_at
 * @property string|null $ip
 * @property FormSubmissionStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FormSubmission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'form_version',
        'blueprint_snapshot',
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
            'blueprint_snapshot' => 'array',
            'payload' => 'array',
            'consent_at' => 'datetime',
            'status' => FormSubmissionStatus::class,
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
