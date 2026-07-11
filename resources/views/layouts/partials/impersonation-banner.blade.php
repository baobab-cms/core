@php
    $impersonatedUser = session('baobab.impersonator_id') ? auth('baobab')->user() : null;
@endphp

@if ($impersonatedUser)
    <div class="flex items-center justify-between bg-warning px-4 py-2 text-sm text-white">
        <span>
            {{ __('baobab::admin.impersonation.banner', ['name' => $impersonatedUser->name]) }}
        </span>

        <form method="POST" action="{{ route('impersonation.stop') }}">
            @csrf
            <button type="submit" class="underline hover:no-underline">
                {{ __('baobab::admin.impersonation.stop') }}
            </button>
        </form>
    </div>
@endif
