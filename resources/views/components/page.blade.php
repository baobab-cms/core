<div {{ $attributes->class(['mb-6']) }}>
    <div class="flex items-center justify-between gap-4">
        <div>
            @if (! empty($crumbs))
                <nav aria-label="breadcrumb" class="mb-1 flex items-center gap-1 text-xs text-muted">
                    @foreach ($crumbs as $crumb)
                        @if (! $loop->first)
                            <span aria-hidden="true">/</span>
                        @endif

                        @if ($crumb['url'])
                            <a href="{{ $crumb['url'] }}" class="hover:text-foreground">{{ $crumb['label'] }}</a>
                        @else
                            <span>{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </nav>
            @endif

            @if ($title)
                <h1 class="font-display text-2xl font-semibold leading-tight text-foreground">{{ $title }}</h1>
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
