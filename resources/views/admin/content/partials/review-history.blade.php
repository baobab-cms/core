<x-baobab::card class="mb-6" :header="__('baobab::admin.content.review_history_title')">
    <ul class="divide-y divide-border">
        @foreach ($reviewHistory as $entry)
            <li class="py-3 text-sm">
                <p class="font-medium text-foreground">
                    {{ __('baobab::admin.content.review_history_action.'.$entry->action) }}
                    <span class="ml-2 text-xs text-muted">
                        {{ $entry->actor->name ?? __('baobab::admin.content.revision_system_author') }}
                        · {{ $entry->created_at->format('d/m/Y H:i') }}
                    </span>
                </p>
                @if (($entry->data['comment'] ?? null) !== null)
                    <p class="mt-1 text-muted">{{ $entry->data['comment'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</x-baobab::card>
