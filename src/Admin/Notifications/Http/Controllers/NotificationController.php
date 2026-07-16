<?php

declare(strict_types=1);

namespace Baobab\Admin\Notifications\Http\Controllers;

use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
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
                ...$this->describe($notification),
                'created_at' => $this->createdAt($notification)?->format('d/m/Y H:i'),
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
            'id' => $notification->getKey(),
            ...$this->describe($notification),
            'created_at' => $this->createdAt($notification)?->diffForHumans(),
        ]);

        return response()->json([
            'count' => $actor->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }

    public function markRead(DatabaseNotification $notification): Response
    {
        $actor = $this->actor();

        /** @var string|null $notifiableType */
        $notifiableType = $notification->getAttribute('notifiable_type');
        /** @var int|string|null $notifiableId */
        $notifiableId = $notification->getAttribute('notifiable_id');

        abort_unless(
            $notifiableType === $actor->getMorphClass() && $notifiableId === $actor->getKey(),
            403,
        );

        $notification->markAsRead();

        return response()->noContent();
    }

    /**
     * @return array{description: string, url: string|null, read: bool}
     */
    private function describe(DatabaseNotification $notification): array
    {
        /** @var array{key: string, description?: string, data?: array<string, mixed>} $data */
        $data = $notification->getAttribute('data');

        return [
            'description' => $data['description'] ?? $data['key'],
            'url' => $data['data']['url'] ?? null,
            'read' => $notification->getAttribute('read_at') !== null,
        ];
    }

    private function createdAt(DatabaseNotification $notification): ?Carbon
    {
        /** @var Carbon|null $createdAt */
        $createdAt = $notification->getAttribute('created_at');

        return $createdAt;
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
