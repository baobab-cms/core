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
                <h1 class="font-display text-3xl font-bold leading-tight tracking-tight text-foreground">{{ $title }}</h1>
            @endif

            @if ($subtitle)
                <p class="mt-1 font-mono text-[0.92em] leading-tight text-muted">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    <div class="mt-4 border-b border-border"></div>

    <div class="mt-10">
        {{ $slot }}
    </div>
</div>
