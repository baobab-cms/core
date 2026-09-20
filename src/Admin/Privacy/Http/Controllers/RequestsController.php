<?php

declare(strict_types=1);

namespace Baobab\Admin\Privacy\Http\Controllers;

use Baobab\Privacy\Actions\CreatePrivacyRequest;
use Baobab\Privacy\Actions\RevealPrivacyRequestPassword;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\Support\SubjectResolver;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * Écran `admin/privacy/requests` (spec 16 §4) : liste des demandes, nouvelle
 * demande d'export, détail avec le téléchargement et la révélation unique du
 * mot de passe. Adaptateur mince, patron `ExportController` : aucune
 * autorisation ici, tout au middleware `can:` des routes.
 */
final class RequestsController
{
    public function index(): View
    {
        return view('baobab::admin.privacy.requests.index', [
            'requests' => PrivacyRequest::query()->latest('id')->limit(50)->get(),
            'columns' => $this->columns(),
        ]);
    }

    public function store(Request $request, SubjectResolver $resolver, CreatePrivacyRequest $create): RedirectResponse
    {
        $validated = $request->validate(['subject' => ['required', 'string', 'max:255']]);

        $subject = $resolver->resolve($validated['subject']);

        if ($subject === null) {
            return back()->withInput()->withErrors(['subject' => __('baobab::privacy.export.unknown_subject')]);
        }

        $created = $create($subject, $this->actor(), 'admin');

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.privacy_requests.requested')]);

        return redirect()->route('admin.privacy.requests.show', ['privacyRequest' => $created->uuid]);
    }

    public function show(PrivacyRequest $privacyRequest): View
    {
        return $this->detail($privacyRequest);
    }

    /**
     * Répond par la vue elle-même plutôt que par une redirection : le mot de
     * passe ne transite ainsi par aucun stockage de session, et un
     * rechargement de la page ne le revoit pas — c'est le sens de « lu une
     * seule fois ».
     */
    public function revealPassword(PrivacyRequest $privacyRequest, RevealPrivacyRequestPassword $reveal): View
    {
        $password = $reveal($privacyRequest, $this->actor());

        return $this->detail($privacyRequest->refresh(), $password);
    }

    private function detail(PrivacyRequest $privacyRequest, ?string $revealedPassword = null): View
    {
        return view('baobab::admin.privacy.requests.show', [
            'privacyRequest' => $privacyRequest->load('requester'),
            'revealedPassword' => $revealedPassword,
            'downloadUrl' => $privacyRequest->isDownloadable()
                ? URL::signedRoute('baobab.privacy.export.download', ['privacyRequest' => $privacyRequest->uuid])
                : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            [
                'key' => 'subject',
                'label' => __('baobab::admin.privacy_requests.column_subject'),
                'raw' => true,
                'render' => fn (PrivacyRequest $request): string => '<a href="'.e(route('admin.privacy.requests.show', ['privacyRequest' => $request->uuid])).'" class="text-primary hover:underline">'.e($request->subjectLabel()).'</a>',
            ],
            [
                'key' => 'status',
                'label' => __('baobab::admin.privacy_requests.column_status'),
                'raw' => true,
                'render' => fn (PrivacyRequest $request): string => view('baobab::admin.privacy.requests.partials.status-badge', ['request' => $request])->render(),
            ],
            [
                'key' => 'created_at',
                'label' => __('baobab::admin.privacy_requests.column_date'),
                'render' => fn (PrivacyRequest $request): string => $request->created_at->format('Y-m-d H:i'),
            ],
            [
                'key' => 'expires_at',
                'label' => __('baobab::admin.privacy_requests.column_expires'),
                'render' => fn (PrivacyRequest $request): string => $request->expires_at?->format('Y-m-d H:i') ?? '—',
            ],
        ];
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
