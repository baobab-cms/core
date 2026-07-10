@props([
    'title' => null,
    'breadcrumbs' => [],
])

<div {{ $attributes->class(['mb-6']) }}>
    <div class="flex items-center justify-between gap-4">
        <div>
            @if (! empty($breadcrumbs))
                <nav aria-label="breadcrumb" class="mb-1 flex items-center gap-1 text-xs text-muted">
                    @foreach ($breadcrumbs as $index => $crumb)
                        @php
                            $label = is_array($crumb) ? ($crumb[0] ?? '') : $crumb;
                            $url = is_array($crumb) ? ($crumb[1] ?? null) : null;
                        @endphp

                        @if ($index > 0)
                            <span aria-hidden="true">/</span>
                        @endif

                        @if ($url)
                            <a href="{{ $url }}" class="hover:text-foreground">{{ $label }}</a>
                        @else
                            <span>{{ $label }}</span>
                        @endif
                    @endforeach
                </nav>
            @endif

            @if ($title)
                <h1 class="text-lg font-semibold text-foreground">{{ $title }}</h1>
            @endif
        </div>

        @isset($actions)
            <div class="flex items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    <div class="mt-4">
        {{ $slot }}
    </div>
</div>
