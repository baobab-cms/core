@if ($confirmationMessage !== null)
    <div {{ $attributes->class(['rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-foreground']) }} role="status">
        {{ $confirmationMessage }}
    </div>
@else
    <x-baobab::form method="POST" :action="route('baobab.forms.submit', ['form' => $form->slug])" {{ $attributes }}>
        <input type="hidden" name="_form_slug" value="{{ $slug }}">

        <x-baobab::forms.fields :fields="$fields" required />

        <x-baobab::button type="submit" variant="primary">{{ __('baobab::rendering.form_submit_action') }}</x-baobab::button>
    </x-baobab::form>
@endif
