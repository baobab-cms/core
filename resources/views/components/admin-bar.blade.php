<div style="position: sticky; top: 0; z-index: 999999; display: flex; align-items: center; justify-content: space-between; height: 32px; padding: 0 12px; background: #1d2327; color: #f0f0f1; font: 13px/32px -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
    <a href="{{ route('admin.dashboard') }}" style="color: #f0f0f1; text-decoration: none; font-weight: 600;">
        {{ __('baobab::admin.admin_bar.dashboard') }}
    </a>

    {{--
        Diagnostic réservé à qui peut agir (spec 19 §6.5) : ce message vivait
        auparavant sur la page publique, où un visiteur le lisait sans pouvoir
        rien en faire.
    --}}
    @if ($noActiveTheme)
        <span style="color: #f0c33c;">
            {{ __('baobab::rendering.no_active_theme_notice') }}

            @if ($canManageThemes)
                <a href="{{ route('admin.themes.index') }}" style="color: #f0c33c;">
                    {{ __('baobab::rendering.no_active_theme_action') }}
                </a>
            @endif
        </span>
    @endif

    <div style="display: flex; align-items: center; gap: 12px;">
        <span style="color: #a7aaad;">{{ $userName }}</span>

        <form method="POST" action="{{ route('logout') }}" style="margin: 0; line-height: 1;">
            @csrf
            <button type="submit" style="background: none; border: none; color: #f0f0f1; font: inherit; cursor: pointer; padding: 0;">
                {{ __('baobab::admin.admin_bar.logout') }}
            </button>
        </form>
    </div>
</div>
