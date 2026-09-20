<?php

declare(strict_types=1);

namespace Baobab\Privacy\Models;

use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une demande d'exercice des droits RGPD (spec 16 §4, décision 10).
 * `uuid` est l'identifiant public, jamais `id`. `password` est chiffré au
 * repos (cast `encrypted`) et vidé à sa première lecture.
 *
 * @property int $id
 * @property string $uuid
 * @property PrivacyRequestType $type
 * @property PrivacyRequestStatus $status
 * @property int|null $subject_user_id
 * @property string|null $subject_email
 * @property string $origin
 * @property string|null $file_disk
 * @property string|null $file_path
 * @property int|null $file_size
 * @property string|null $password
 * @property Carbon|null $expires_at
 * @property string|null $error_message
 * @property int|null $requested_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
class PrivacyRequest extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'status',
        'subject_user_id',
        'subject_email',
        'origin',
        'file_disk',
        'file_path',
        'file_size',
        'password',
        'expires_at',
        'error_message',
        'requested_by',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => PrivacyRequestType::class,
            'status' => PrivacyRequestStatus::class,
            'password' => 'encrypted',
            'file_size' => 'integer',
            'expires_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function subject(): Subject
    {
        return new Subject($this->subject_user_id, $this->subject_email);
    }

    /** Ce qu'on affiche du sujet : son e-mail, à défaut le compte. */
    public function subjectLabel(): string
    {
        return $this->subject_email ?? '#'.$this->subject_user_id;
    }

    public function isDownloadable(): bool
    {
        return $this->type === PrivacyRequestType::Export
            && $this->status === PrivacyRequestStatus::Completed
            && $this->file_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function hasPendingPassword(): bool
    {
        return $this->isDownloadable() && $this->password !== null;
    }

    /**
     * `HasUuids` ne remplit que la colonne `uuid` — `id` reste l'auto-incrément
     * interne, jamais exposé en route (patron `ExportJob`).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
