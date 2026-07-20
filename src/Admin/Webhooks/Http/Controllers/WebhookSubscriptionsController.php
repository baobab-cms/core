<?php

declare(strict_types=1);

namespace Baobab\Admin\Webhooks\Http\Controllers;

use Baobab\Webhooks\Actions\CreateWebhookSubscription;
use Baobab\Webhooks\Actions\DeleteWebhookSubscription;
use Baobab\Webhooks\Actions\UpdateWebhookSubscription;
use Baobab\Webhooks\Models\WebhookSubscription;
use Baobab\Webhooks\Support\WebhookEventCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Écran « Webhooks » (spec 08 §5). Accès gouverné par
 * `baobab.system.webhooks.manage` (routes/admin.php), patron exact
 * `RedirectsController`.
 */
final class WebhookSubscriptionsController
{
    public function index(): View
    {
        $subscriptions = WebhookSubscription::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('baobab::admin.webhooks.index', [
            'subscriptions' => $subscriptions,
            'columns' => $this->columns(),
        ]);
    }

    public function create(): View
    {
        return view('baobab::admin.webhooks.create', [
            'events' => $this->eventOptions(),
            // Pré-rempli plutôt que laissé à l'admin à inventer — l'admin
            // reste libre de le remplacer par un secret déjà connu du côté
            // récepteur. Un aller-retour sur cette même route régénère.
            'generatedSecret' => Str::random(64),
        ]);
    }

    public function store(Request $request, CreateWebhookSubscription $action): RedirectResponse
    {
        $action($this->validatedForCreate($request));

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.webhooks.created')]);

        return redirect()->route('admin.webhooks.index');
    }

    public function edit(WebhookSubscription $subscription): View
    {
        return view('baobab::admin.webhooks.edit', [
            'subscription' => $subscription,
            'events' => $this->eventOptions($subscription),
        ]);
    }

    public function update(WebhookSubscription $subscription, Request $request, UpdateWebhookSubscription $action): RedirectResponse
    {
        $action($subscription, $this->validatedForUpdate($request));

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.webhooks.updated')]);

        return redirect()->route('admin.webhooks.index');
    }

    public function destroy(WebhookSubscription $subscription, DeleteWebhookSubscription $action): RedirectResponse
    {
        $action($subscription);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.webhooks.deleted')]);

        return redirect()->route('admin.webhooks.index');
    }

    /**
     * @return array{url: string, secret: string, events: list<string>, is_active: bool}
     */
    private function validatedForCreate(Request $request): array
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'secret' => ['required', 'string', 'min:16'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', WebhookEventCatalog::all())],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'url' => $validated['url'],
            'secret' => $validated['secret'],
            'events' => $validated['events'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    /**
     * Le formulaire d'édition laisse le secret vide pour le conserver
     * inchangé — jamais réaffiché en clair après création.
     *
     * @return array{url: string, secret?: string, events: list<string>, is_active: bool}
     */
    private function validatedForUpdate(Request $request): array
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'secret' => ['nullable', 'string', 'min:16'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', WebhookEventCatalog::all())],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data = [
            'url' => $validated['url'],
            'events' => $validated['events'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];

        if (! empty($validated['secret'])) {
            $data['secret'] = $validated['secret'];
        }

        return $data;
    }

    /**
     * Calcule l'état coché de chaque événement du catalogue (jamais dans la
     * vue elle-même — patron CLAUDE.md, les vues admin n'exécutent aucune
     * logique). `old('events', ...)` couvre le rechargement du formulaire
     * après une erreur de validation.
     *
     * @return list<array{name: string, checked: bool}>
     */
    private function eventOptions(?WebhookSubscription $subscription = null): array
    {
        /** @var list<string> $selected */
        $selected = old('events', $subscription->events ?? []);

        return array_map(
            fn (string $event): array => ['name' => $event, 'checked' => in_array($event, $selected, true)],
            WebhookEventCatalog::all(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            ['key' => 'url', 'label' => __('baobab::admin.webhooks.column_url')],
            [
                'key' => 'events',
                'label' => __('baobab::admin.webhooks.column_events'),
                'render' => fn (WebhookSubscription $subscription) => Str::limit(implode(', ', $subscription->events), 60),
            ],
            [
                'key' => 'is_active',
                'label' => __('baobab::admin.webhooks.column_active'),
                'render' => fn (WebhookSubscription $subscription) => $subscription->is_active ? __('baobab::admin.webhooks.active_yes') : __('baobab::admin.webhooks.active_no'),
            ],
            ['key' => 'consecutive_failures', 'label' => __('baobab::admin.webhooks.column_failures')],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (WebhookSubscription $subscription) => view('baobab::admin.webhooks.partials.row-actions', ['subscription' => $subscription])->render(),
            ],
        ];
    }
}
