@use('Baobab\Imports\ImportJobStatusPresenter')

@props(['job'])

<x-baobab::badge :variant="ImportJobStatusPresenter::badgeVariant($job->status)">
    {{ ImportJobStatusPresenter::label($job->status) }}
</x-baobab::badge>
