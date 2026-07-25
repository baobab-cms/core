@props([
    'name',
    'title' => null,
    'expectedText' => null,
    'open' => false,
])

<x-baobab::modal :name="$name" :open="$open">
    <div x-data="{ typed: '' }">
        @if ($title)
            <h2 class="font-display text-base font-semibold text-foreground">{{ $title }}</h2>
        @endif

        @isset($description)
            <div class="mt-2 text-sm text-muted">{{ $description }}</div>
        @endisset

        @if ($expectedText)
            <div class="mt-4">
                <label class="text-xs text-muted">
                    {{ __('baobab::admin.components.confirm_placeholder', ['text' => $expectedText]) }}
                </label>
                <input
                    type="text"
                    x-model="typed"
                    placeholder="{{ $expectedText }}"
                    class="mt-1 w-full rounded-md border border-border px-2 py-1 text-sm"
                >
            </div>
        @endif

        <div
            class="mt-4"
            @if ($expectedText)
                x-bind:class="{ 'pointer-events-none opacity-50': typed !== @js($expectedText) }"
            @endif
        >
            {{ $slot }}
        </div>

        <div class="mt-4 flex justify-end">
            <x-baobab::button type="button" variant="ghost" x-on:click="show = false">
                {{ __('baobab::admin.components.close') }}
            </x-baobab::button>
        </div>
    </div>
</x-baobab::modal>
