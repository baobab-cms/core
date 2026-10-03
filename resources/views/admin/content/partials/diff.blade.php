@if ($diff)
    <div class="space-y-3">
        @foreach ($diff as $field => $entry)
            @if ($entry['changed'])
                <div>
                    <p class="text-xs font-medium uppercase text-muted">{{ $field }}</p>
                    @if ($entry['words'])
                        <p class="mt-1 text-sm leading-relaxed">
                            @foreach ($entry['words'] as $token)
                                @if ($token['type'] === 'equal')
                                    <span>{{ $token['text'] }}</span>
                                @elseif ($token['type'] === 'delete')
                                    <span class="bg-danger/20 text-danger line-through">{{ $token['text'] }}</span>
                                @else
                                    <span class="bg-success/20 text-success">{{ $token['text'] }}</span>
                                @endif
                            @endforeach
                        </p>
                    @else
                        <div class="mt-1 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                            <p class="rounded bg-danger/10 p-2 text-danger">{{ json_encode($entry['before']) }}</p>
                            <p class="rounded bg-success/10 p-2 text-success">{{ json_encode($entry['after']) }}</p>
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    </div>
@endif
