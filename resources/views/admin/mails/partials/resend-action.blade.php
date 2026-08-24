{{--
    Le bouton n'est pas caché quand le renvoi est impossible, il est **rendu
    inerte avec sa raison** (spec 13 §4.2). Cacher laisserait croire que la
    fonction n'existe pas ; le désactiver dit qu'elle existe et pourquoi elle
    ne s'applique pas ici.

    `title` porte l'explication au survol, et `sr-only` la donne aux lecteurs
    d'écran, qui n'ont pas de survol.
--}}
@if ($resendable)
    <form method="POST" action="{{ route('admin.mails.log.resend', ['entry' => $entry->id]) }}">
        @csrf
        <button type="submit" class="text-primary hover:underline">
            {{ __('baobab::admin.mail_log.resend') }}
        </button>
    </form>
@else
    <span class="cursor-not-allowed text-muted" title="{{ __('baobab::admin.mail_log.not_resendable') }}">
        {{ __('baobab::admin.mail_log.resend') }}
        <span class="sr-only">— {{ __('baobab::admin.mail_log.not_resendable') }}</span>
    </span>
@endif
