@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.dashboard.title'))
@section('page-title', __('baobab::admin.dashboard.title'))

@section('content')
    <p class="text-sm text-muted">{{ __('baobab::admin.dashboard.placeholder') }}</p>
@endsection
