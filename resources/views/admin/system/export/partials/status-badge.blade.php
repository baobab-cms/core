@use('Baobab\Exports\ExportJobStatusPresenter')

@props(['job'])

<x-baobab::badge :variant="ExportJobStatusPresenter::badgeVariant($job->status)">
    {{ ExportJobStatusPresenter::label($job->status) }}
</x-baobab::badge>
