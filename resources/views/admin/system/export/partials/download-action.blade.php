@props(['job'])

<a href="{{ route('admin.system.export.download', ['exportJob' => $job->uuid]) }}" class="text-primary hover:underline">
    {{ __('baobab::admin.export.download') }}
</a>
