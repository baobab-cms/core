<?php

declare(strict_types=1);

namespace Baobab\Mail\Models;

use Baobab\Mail\MailLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Une ligne du journal des e-mails (spec 13 §4.1). Le nom porte `Entry` parce
 * que `MailLog` désignerait le journal et non l'une de ses lignes — même
 * précaution qu'entre `MailTemplate` (le template résolu) et
 * `MailTemplateOverride` (la personnalisation), en Pass A1.
 *
 * `body` reste nul sauf si `baobab.mail.log_body` est activé (§4.1) : le
 * journal prouve qu'un e-mail est parti, il n'en archive pas le contenu.
 *
 * @property int $id
 * @property string $template_key
 * @property string $recipient
 * @property string $subject
 * @property MailLogStatus $status
 * @property string|null $error
 * @property string|null $mailer
 * @property string|null $body
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MailLogEntry extends Model
{
    protected $table = 'mail_log';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'template_key',
        'recipient',
        'subject',
        'status',
        'error',
        'mailer',
        'body',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MailLogStatus::class,
            'sent_at' => 'datetime',
        ];
    }
}
