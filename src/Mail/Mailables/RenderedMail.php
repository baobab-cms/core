<?php

declare(strict_types=1);

namespace Baobab\Mail\Mailables;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Enveloppe Laravel autour d'un sujet/HTML/texte déjà rendus par
 * `Baobab\Mail\Mailer` (spec 13 §2.3) — pas de vue Blade pour le HTML (déjà
 * inliné), une vue minimale pour le texte (`Content::text()` n'accepte qu'un
 * nom de vue, jamais une chaîne brute).
 */
final class RenderedMail extends Mailable
{
    public function __construct(
        private readonly string $subjectLine,
        private readonly string $htmlBody,
        string $textBody,
    ) {
        $this->with('text', $textBody);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
            text: 'baobab::emails.text',
        );
    }
}
