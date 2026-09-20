@props(['request'])

<x-baobab::badge :variant="$request->status->badgeVariant()">
    {{ $request->status->label() }}
</x-baobab::badge>
