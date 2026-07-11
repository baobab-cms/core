<form method="POST" action="{{ route('admin.users.impersonate', $user) }}">
    @csrf
    <button type="submit" class="text-xs font-medium text-primary hover:underline">
        {{ __('baobab::admin.users.impersonate_action') }}
    </button>
</form>
