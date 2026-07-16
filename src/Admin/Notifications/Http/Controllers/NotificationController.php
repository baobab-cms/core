<?php

declare(strict_types=1);

namespace Baobab\Admin\Notifications\Http\Controllers;

use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

/**
 * Centre de notifications (spec 11 §8) — pas de permission dédiée, chacun ne
 * voit/marque que les siennes (scope `own` implicite, filtrage par
 * `notifiable_id`).
 */
final class NotificationController
{
    public function index(): View
    {
        $notifications = $this->actor()->notifications()->latest()->paginate(20)
            ->through(fn (DatabaseNotification $notification) => [
                'description' => $notification->data['description'] ?? $notification->data['key'],
                'url' => $notification->data['data']['url'] ?? null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->format('d/m/Y H:i'),
            ]);

        return view('baobab::admin.notifications.index', ['notifications' => $notifications]);
    }

    /**
     * Rafraîchissement par polling léger (spec 11 §5.1 : pas de Reverb en v1).
     */
    public function poll(): JsonResponse
    {
        $actor = $this->actor();

        $items = $actor->notifications()->latest()->limit(10)->get()->map(fn (DatabaseNotification $notification) => [
            'id' => $notification->id,
            'description' => $notification->data['description'] ?? $notification->data['key'],
            'url' => $notification->data['data']['url'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->diffForHumans(),
        ]);

        return response()->json([
            'count' => $actor->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }

    public function markRead(DatabaseNotification $notification): Response
    {
        $actor = $this->actor();

        abort_unless(
            $notification->notifiable_type === $actor->getMorphClass() && $notification->notifiable_id === $actor->getKey(),
            403,
        );

        $notification->markAsRead();

        return response()->noContent();
    }

    public function markAllRead(): Response
    {
        $this->actor()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
