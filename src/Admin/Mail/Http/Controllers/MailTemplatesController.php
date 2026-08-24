<?php

declare(strict_types=1);

namespace Baobab\Admin\Mail\Http\Controllers;

use Baobab\Mail\Actions\RestoreMailTemplateDefault;
use Baobab\Mail\Actions\SaveMailTemplate;
use Baobab\Mail\Actions\SendTestMail;
use Baobab\Mail\Exceptions\InvalidMailTemplateException;
use Baobab\Mail\Mailer;
use Baobab\Mail\MailTemplate;
use Baobab\Mail\MailTemplateDeclaration;
use Baobab\Mail\TemplateRegistry;
use Baobab\Support\LineDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Personnalisation des templates d'e-mails (spec 13 §3.2-3.4).
 *
 * **Adaptateur mince, sans aucune Action à lui** : `SaveMailTemplate`,
 * `RestoreMailTemplateDefault` et `SendTestMail` existent déjà et portent
 * validation, persistance et audit. Cet écran n'ajoute que la surface —
 * même rapport que `ContentTypesController` entretient avec
 * `BuildContentType`.
 *
 * Accès gouverné par `baobab.system.mail.templates` (routes/admin.php),
 * aucune policy own/any : un template d'e-mail n'appartient à personne.
 */
final class MailTemplatesController
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly Mailer $mailer,
    ) {}

    /**
     * Un `customisedKeys()` unique plutôt qu'un `find()` par ligne : ce
     * dernier relirait le fichier de défauts de chaque template rien que pour
     * afficher un badge.
     */
    public function index(): View
    {
        $customised = $this->templates->customisedKeys();

        $rows = array_map(fn (MailTemplateDeclaration $declaration): array => [
            'key' => $declaration->key,
            'source' => $declaration->source,
            'description' => $declaration->description,
            'customised' => in_array($declaration->key, $customised, true),
            'drifted' => $this->templates->defaultDrift($declaration->key) !== null,
        ], $this->templates->all());

        return view('baobab::admin.mails.index', ['rows' => $rows]);
    }

    /**
     * Le diff est calculé ici, jamais dans la vue : « les contrôleurs
     * calculent, les vues affichent ». Le marqueur de gauche aussi, pour la
     * même raison — patron `InspectModuleConflicts`.
     */
    public function edit(string $key): View
    {
        $declaration = $this->templates->declaration($key);
        $template = $this->templates->find($key);
        $drift = $this->templates->defaultDrift($key);

        return view('baobab::admin.mails.form', [
            'declaration' => $declaration,
            'template' => $template,
            'variables' => $declaration->variables->labels(),
            'required' => $declaration->variables->required(),
            // Calculé ici et pas dans la vue : composer « Nom <adresse> » à
            // partir de deux clés de configuration est une règle, pas de
            // l'affichage.
            'globalSender' => trim(sprintf(
                '%s <%s>',
                (string) config('mail.from.name'),
                (string) config('mail.from.address'),
            )),
            'drift' => $drift,
            'subjectDiff' => $drift === null ? null : $this->diff($drift->previousSubject, $drift->currentSubject),
            'bodyDiff' => $drift === null ? null : $this->diff($drift->previousBody, $drift->currentBody),
        ]);
    }

    public function update(Request $request, string $key, SaveMailTemplate $save): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $save($key, $data);
        } catch (InvalidMailTemplateException $exception) {
            // La validation bloquante du §3.3 vit dans l'Action, pas dans un
            // Form Request : elle est ramenée ici sur le champ concerné pour
            // que l'intégrateur la lise là où il vient d'écrire, sans perdre
            // sa saisie.
            return back()->withInput()->withErrors(['body' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.mails.edit', $key)
            ->with('status', __('baobab::admin.mails.saved'));
    }

    public function restore(string $key, RestoreMailTemplateDefault $restore): RedirectResponse
    {
        $restore($key);

        return redirect()
            ->route('admin.mails.edit', $key)
            ->with('status', __('baobab::admin.mails.restored'));
    }

    /**
     * L'e-mail tel qu'il partirait, rendu par le même `Mailer::render()` que
     * l'envoi réel — pas une imitation. Renvoyé en document HTML complet
     * (le layout en est un) plutôt qu'encastré dans une page admin : c'est
     * la seule façon d'en voir la vraie largeur et le vrai fond.
     *
     * Sur le contenu **du formulaire**, pas sur la version enregistrée :
     * on prévisualise ce qu'on vient d'écrire.
     */
    public function preview(Request $request, string $key): Response
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $declaration = $this->templates->declaration($key);

        $html = $this->mailer->render(
            new MailTemplate($key, $data['subject'], $data['body']),
            $declaration->sampleValues(),
        );

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Envoi de test sur le template **enregistré**, et non sur la saisie en
     * cours : ce qui part par la queue doit être ce que le site enverra
     * vraiment. L'aperçu, lui, couvre le brouillon.
     */
    public function test(Request $request, string $key, SendTestMail $send): RedirectResponse
    {
        $data = $request->validate(['recipient' => ['required', 'email']]);

        $declaration = $this->templates->declaration($key);

        $send($data['recipient'], $key, $declaration->sampleValues());

        return redirect()
            ->route('admin.mails.edit', $key)
            ->with('status', __('baobab::admin.mails.test_sent', ['recipient' => $data['recipient']]));
    }

    /**
     * @return list<array{type: string, line: string, marker: string}>|null
     */
    private function diff(string $previous, string $current): ?array
    {
        $diff = LineDiff::compare($previous, $current);

        if ($diff === null) {
            return null;
        }

        return array_map(static fn (array $line): array => [
            ...$line,
            'marker' => match ($line['type']) {
                'removed' => '-',
                'added' => '+',
                default => ' ',
            },
        ], $diff);
    }
}
