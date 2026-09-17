<?php

declare(strict_types=1);

namespace Baobab\Queue\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un job échoué (table native Laravel `failed_jobs`, spec 12 §3.2) — pas de
 * migration à écrire, la table existe depuis le squelette. `uuid` est
 * l'identifiant public utilisé par `queue:retry`/`queue:forget`, jamais
 * `id` (l'auto-incrément interne).
 *
 * @property int $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $payload
 * @property string $exception
 * @property Carbon $failed_at
 */
class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    /**
     * Jamais écrit via Eloquent (les Actions passent par `queue:retry`/
     * `queue:forget`) — pas de risque de mass-assignment non maîtrisée,
     * juste pratique pour construire une instance en test.
     *
     * @var list<string>
     */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    /**
     * `displayName` du payload standard Laravel (mis à la mise en file) —
     * repli sur `job` (la classe du handler) si absent, jamais un JSON brut
     * affiché à l'écran.
     */
    public function jobName(): string
    {
        $decoded = json_decode($this->payload, true);

        if (! is_array($decoded)) {
            return __('baobab::admin.queues.unknown_job');
        }

        return (string) ($decoded['displayName'] ?? $decoded['job'] ?? __('baobab::admin.queues.unknown_job'));
    }

    /**
     * Première ligne de la trace, tronquée — la trace complète n'a pas sa
     * place dans une colonne de tableau, patron `MailLogStatus`/`log-status.blade.php`
     * (raison de l'échec sous la pastille, jamais une colonne à elle).
     */
    public function exceptionSummary(): string
    {
        $firstLine = strtok($this->exception, "\n");

        return $firstLine !== false ? mb_strimwidth($firstLine, 0, 200, '…') : '';
    }
}
