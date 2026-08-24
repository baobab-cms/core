<?php

declare(strict_types=1);

namespace Baobab\Admin\Mail\Http\Controllers;

use Baobab\Mail\Actions\ResendMail;
use Baobab\Mail\Exceptions\MailNotResendableException;
use Baobab\Mail\Exceptions\MailTemplateNotFoundException;
use Baobab\Mail\MailLogStatus;
use Baobab\Mail\Models\MailLogEntry;
use Baobab\Mail\TemplateRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Journal des e-mails (spec 13 §4.2) — **sous-écran de la section E-mails**,
 * à `admin/mails/log` et non à l'`admin/system/mail-log` de la spec : aucun
 * `admin/system/*` n'existe dans le Core, et les deux autres journaux du
 * produit sont eux aussi des sous-écrans de leur section
 * (`redirects/not-found`, `webhooks/{id}/deliveries`). Adresse tranchée et
 * spec amendée le 24 août 2026 (suivi n° 197).
 *
 * **Adaptateur mince : aucune Action écrite ici.** `ResendMail` existe depuis
 * la Pass B1 et porte toute la décision — le contrôleur ne fait que la lui
 * passer et traduire son refus.
 */
final class MailLogController
{
    public function __construct(private readonly TemplateRegistry $templates) {}

    public function index(Request $request): View
    {
        $entries = MailLogEntry::query()
            ->when($request->filled('template_key'), fn ($query) => $query->where('template_key', $request->string('template_key')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            // `like` sur le destinataire, là où les autres filtres sont
            // exacts : on cherche « toutes les adresses d'un domaine » ou
            // « ce que j'ai envoyé à Awa » bien plus souvent qu'une adresse
            // dont on a la graphie exacte sous les yeux.
            ->when($request->filled('recipient'), fn ($query) => $query->where('recipient', 'like', '%'.$request->string('recipient').'%'))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('baobab::admin.mails.log', [
            'entries' => $entries,
            'columns' => $this->columns(),
            'templateOptions' => $this->templateOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    /**
     * Le refus est **rattrapé et rendu en message**, jamais laissé filer vers
     * la page d'exception de Laravel. La cause de loin la plus fréquente —
     * « la source a disparu depuis l'envoi » — n'est pas une panne mais un
     * état du monde qui a changé, et le bouton est déjà grisé quand aucun
     * resolver n'est déclaré. Leçon du suivi n° 147, appliquée d'avance
     * plutôt qu'ajoutée après un incident.
     */
    public function resend(MailLogEntry $entry, ResendMail $resend): RedirectResponse
    {
        try {
            $resend($entry);
        } catch (MailNotResendableException $exception) {
            session()->flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return redirect()->route('admin.mails.log');
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.mail_log.resent', ['recipient' => $entry->recipient])]);

        return redirect()->route('admin.mails.log');
    }

    /**
     * Les clés réellement déclarées, pas celles qui traînent dans le journal :
     * un template retiré avec son module n'est plus filtrable, et c'est juste
     * — on ne peut plus rien en dire, ni le renvoyer.
     *
     * @return array<string, string>
     */
    private function templateOptions(): array
    {
        $options = ['' => __('baobab::admin.mail_log.filter_all')];

        foreach ($this->templates->all() as $declaration) {
            $options[$declaration->key] = $declaration->key;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        $options = ['' => __('baobab::admin.mail_log.filter_all')];

        foreach (MailLogStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            [
                'key' => 'created_at',
                'label' => __('baobab::admin.mail_log.column_date'),
                'render' => fn (MailLogEntry $entry) => $entry->created_at->format('Y-m-d H:i'),
            ],
            [
                'key' => 'template_key',
                'label' => __('baobab::admin.mail_log.column_template'),
            ],
            [
                'key' => 'recipient',
                'label' => __('baobab::admin.mail_log.column_recipient'),
            ],
            [
                'key' => 'subject',
                'label' => __('baobab::admin.mail_log.column_subject'),
            ],
            [
                'key' => 'status',
                'label' => __('baobab::admin.mail_log.column_status'),
                'raw' => true,
                'render' => fn (MailLogEntry $entry) => view('baobab::admin.mails.partials.log-status', ['entry' => $entry])->render(),
            ],
            [
                'key' => 'mailer',
                'label' => __('baobab::admin.mail_log.column_mailer'),
                'render' => fn (MailLogEntry $entry) => $entry->mailer ?? '—',
            ],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (MailLogEntry $entry) => view('baobab::admin.mails.partials.resend-action', [
                    'entry' => $entry,
                    'resendable' => $this->isResendable($entry),
                ])->render(),
            ],
        ];
    }

    /**
     * Un template disparu avec son module n'est pas renvoyable non plus, et
     * `declaration()` lève alors — d'où le rattrapage : le journal survit à la
     * désinstallation de ce qu'il a tracé.
     */
    private function isResendable(MailLogEntry $entry): bool
    {
        try {
            return $this->templates->declaration($entry->template_key)->isResendable();
        } catch (MailTemplateNotFoundException) {
            return false;
        }
    }
}
