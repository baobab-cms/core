@props([
    'method' => 'POST',
])

@php
    $spoofedMethods = ['PUT', 'PATCH', 'DELETE'];
    $httpMethod = strtoupper($method);
@endphp

<form
    method="{{ in_array($httpMethod, $spoofedMethods, true) ? 'POST' : $httpMethod }}"
    {{ $attributes->except('method') }}
>
    @csrf

    @if (in_array($httpMethod, $spoofedMethods, true))
        @method($httpMethod)
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{ $slot }}
</form>
