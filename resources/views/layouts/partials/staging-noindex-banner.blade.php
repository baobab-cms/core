@if ($stagingNoindexActive ?? false)
    <div class="bg-warning/10 border-b border-warning/30 px-4 py-2 text-center text-sm text-warning">
        {{ __('baobab::admin.staging_banner.message') }}
    </div>
@endif
