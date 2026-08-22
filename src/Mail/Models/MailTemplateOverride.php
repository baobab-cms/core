<?php

declare(strict_types=1);

namespace Baobab\Mail\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Personnalisation admin d'un template d'e-mail (spec 13 §3.2), table
 * `mail_templates`. Nommé « Override » et non « MailTemplate » : le template
 * résolu est le DTO `Baobab\Mail\MailTemplate`, cette ligne n'est que ce que
 * l'admin a écrit **par-dessus** le défaut du code. Un template non
 * personnalisé n'a pas de ligne du tout.
 *
 * @property int $id
 * @property string $key
 * @property string $subject
 * @property string $body
 * @property string|null $from_address
 * @property string|null $from_name
 * @property array{subject: string, body: string} $default_snapshot
 */
class MailTemplateOverride extends Model
{
    protected $table = 'mail_templates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'subject',
        'body',
        'from_address',
        'from_name',
        'default_snapshot',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'default_snapshot' => 'array',
    ];
}
