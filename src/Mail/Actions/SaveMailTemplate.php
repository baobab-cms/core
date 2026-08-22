<?php

declare(strict_types=1);

namespace Baobab\Mail\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Mail\Exceptions\InvalidMailTemplateException;
use Baobab\Mail\Models\MailTemplateOverride;
use Baobab\Mail\Support\PlaceholderRenderer;
use Baobab\Mail\TemplateRegistry;

/**
 * Enregistre la personnalisation admin d'un template d'e-mail (spec 13 §3.2).
 *
 * Porte la **validation bloquante** de la spec 13 §7 décision 3 : on ne peut
 * pas sauvegarder un `core.password_reset` privé de son lien de
 * réinitialisation. Elle est ici, dans l'Action, et pas seulement dans un Form
 * Request : l'écran de la Pass A2 n'est qu'un des clients possibles, et la
 * règle protège le template quel que soit l'appelant.
 *
 * `default_snapshot` est (re)capturé à chaque enregistrement : sauvegarder,
 * c'est déclarer avoir vu le défaut courant et écrire sa version en face. Une
 * mise à jour ultérieure du module fera diverger le défaut de cet instantané —
 * c'est cette divergence, et elle seule, que l'écran présentera en diff (§3.2),
 * sans jamais écraser la version de l'admin.
 */
final class SaveMailTemplate
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly PlaceholderRenderer $renderer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{subject: string, body: string, from_address?: string|null, from_name?: string|null}  $data
     */
    public function __invoke(string $key, array $data): MailTemplateOverride
    {
        $declaration = $this->templates->declaration($key);
        $default = $this->templates->default($key);

        $text = $data['subject'].' '.$data['body'];

        $missing = $declaration->variables->missingRequiredIn($this->renderer->placeholdersIn($text));

        if ($missing !== []) {
            throw InvalidMailTemplateException::missingRequiredVariables($key, $missing);
        }

        $unknown = $declaration->variables->unknownIn($this->renderer->variablesIn($text));

        if ($unknown !== []) {
            throw InvalidMailTemplateException::unknownVariables($key, $unknown);
        }

        $override = MailTemplateOverride::updateOrCreate(['key' => $key], [
            'subject' => $data['subject'],
            'body' => $data['body'],
            'from_address' => $data['from_address'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'default_snapshot' => ['subject' => $default->subject, 'body' => $default->body],
        ]);

        $this->audit->record('mail.template_saved', $override, ['key' => $key]);

        return $override;
    }
}
