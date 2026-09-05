@extends('baobab::layouts.admin')

@section('title', $form->title)

@section('content')
    <x-baobab::page :title="$form->title" :breadcrumbs="[[__('baobab::admin.forms.title'), route('admin.forms.index')]]">
        <p class="mb-4 text-xs text-muted">
            {{ __('baobab::admin.forms.slug_display', ['slug' => $form->slug]) }}
            &middot;
            {{ __('baobab::admin.forms.version_display', ['version' => $form->version]) }}
        </p>

        <div
            x-data="formFieldsEditor({
                fields: @js($form->blueprint['fields'] ?? []),
                needChoices: @js($typesNeedingChoices),
            })"
        >
            <x-baobab::card>
                <x-baobab::form method="PUT" action="{{ route('admin.forms.update', ['form' => $form->id]) }}">
                    <input type="hidden" name="fields" x-bind:value="payload">

                    <x-baobab::field.text name="title" :label="__('baobab::admin.forms.title_label')" :value="$form->title" />

                    <x-baobab::blueprint.field-rows
                        collection="fields"
                        add-expression="addField()"
                        :field-types="$fieldTypes"
                        :extra-fields="true"
                        :show-unique-indexed="false"
                        :draggable="true"
                    />

                    <div class="mt-4">
                        <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.forms.save_action') }}</x-baobab::button>
                    </div>
                </x-baobab::form>
            </x-baobab::card>
        </div>

        {{--
            Trois sections plutôt que des onglets (spec 14 §3 en parle
            littéralement) : aucun composant d'onglets n'existe nulle part
            dans le Core (recherché, aucun précédent), et la Branding suit
            déjà ce même patron pour plusieurs groupes de réglages sur un
            seul écran. Écart assumé, revenir aux onglets un jour n'est pas
            structurant.
        --}}
        <x-baobab::card class="mt-6">
            <x-baobab::form method="PUT" action="{{ route('admin.forms.settings.update', ['form' => $form->id]) }}">
                <h2 class="mb-3 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.forms.confirmation_title') }}</h2>
                <p class="mb-4 text-xs text-muted">{{ __('baobab::admin.forms.confirmation_hint') }}</p>

                <div class="mb-4 space-y-2">
                    <x-baobab::field.textarea name="confirmation_message" :label="__('baobab::admin.forms.confirmation_message')" :placeholder="__('baobab::admin.forms.confirmation_message_placeholder')" :value="$settingsForm['confirmation_message']" />
                </div>

                <h2 class="mb-3 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.forms.suites_title') }}</h2>

                <div class="mb-4 space-y-2">
                    <x-baobab::field.checkbox name="suites[email_notification][enabled]" :label="__('baobab::admin.forms.suite_email_notification')" :checked="$settingsForm['email_notification_enabled']" />
                    <x-baobab::field.textarea name="suites[email_notification][recipients]" :label="__('baobab::admin.forms.suite_email_notification_recipients')" :value="$settingsForm['email_notification_recipients']" />

                    <x-baobab::field.checkbox name="suites[acknowledgement][enabled]" :label="__('baobab::admin.forms.suite_acknowledgement')" :checked="$settingsForm['acknowledgement_enabled']" />
                    <x-baobab::field.checkbox name="suites[admin_notification][enabled]" :label="__('baobab::admin.forms.suite_admin_notification')" :checked="$settingsForm['admin_notification_enabled']" />
                    <x-baobab::field.checkbox name="suites[webhook][enabled]" :label="__('baobab::admin.forms.suite_webhook')" :checked="$settingsForm['webhook_enabled']" />
                </div>

                <h2 class="mb-3 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.forms.anti_spam_title') }}</h2>
                <p class="mb-4 text-xs text-muted">{{ __('baobab::admin.forms.anti_spam_hint') }}</p>

                <div class="mb-4 space-y-2">
                    <x-baobab::field.select name="captcha_provider" :label="__('baobab::admin.forms.captcha_provider')" :value="$settingsForm['captcha_provider']" :options="[
                        'none' => __('baobab::admin.forms.captcha_provider_none'),
                        'turnstile' => __('baobab::admin.forms.captcha_provider_turnstile'),
                        'hcaptcha' => __('baobab::admin.forms.captcha_provider_hcaptcha'),
                    ]" />
                    <x-baobab::field.text name="captcha_site_key" :label="__('baobab::admin.forms.captcha_site_key')" :value="$settingsForm['captcha_site_key']" />
                    <x-baobab::field.text name="captcha_secret_key" :label="__('baobab::admin.forms.captcha_secret_key')" :value="$settingsForm['captcha_secret_key']" />
                    <x-baobab::field.checkbox name="retain_ip" :label="__('baobab::admin.forms.retain_ip')" :checked="$settingsForm['retain_ip']" />
                </div>

                <h2 class="mb-3 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.forms.retention_title') }}</h2>

                <div class="mb-4 space-y-2">
                    <x-baobab::field.checkbox name="store_submissions" :label="__('baobab::admin.forms.store_submissions')" :checked="$settingsForm['store_submissions']" />
                    <x-baobab::field.integer name="retention_days" :label="__('baobab::admin.forms.retention_days')" :value="$settingsForm['retention_days']" />
                </div>

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.forms.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>

        <x-baobab::card class="mt-6">
            <h2 class="mb-3 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.forms.preview_title') }}</h2>
            <p class="mb-4 text-xs text-muted">{{ __('baobab::admin.forms.preview_hint') }}</p>

            @if ($previewFields === [])
                <p class="text-sm text-muted">{{ __('baobab::admin.forms.preview_empty') }}</p>
            @else
                <x-baobab::forms.fields :fields="$previewFields" />
            @endif
        </x-baobab::card>
    </x-baobab::page>
@endsection

@once
    <script>
        function formFieldsEditor(config) {
            return {
                needChoices: config.needChoices,

                fields: config.fields.map((field) => ({
                    key: field.key,
                    type: field.type,
                    label: field.label ?? '',
                    placeholder: field.placeholder ?? '',
                    help_text: field.help_text ?? '',
                    required: field.required ?? false,
                    _choicesText: (field.options?.choices ?? []).join('\n'),
                    _consentText: field.options?.text ?? '',
                    _consentPrivacyUrl: field.options?.privacy_url ?? '',
                })),

                /**
                 * Point de sérialisation unique : les clés d'interface
                 * (préfixées `_`) ne sortent jamais d'ici — patron
                 * `studioEntities().payload`.
                 */
                get payload() {
                    return JSON.stringify(this.fields.map((field) => {
                        let options = {};

                        if (field.type === 'consent') {
                            options = { text: field._consentText, privacy_url: field._consentPrivacyUrl };
                        } else if (this.needsChoices(field.type)) {
                            options = {
                                choices: (field._choicesText ?? '')
                                    .split('\n')
                                    .map((choice) => choice.trim())
                                    .filter(Boolean),
                            };
                        }

                        return {
                            key: field.key,
                            type: field.type,
                            label: field.label,
                            placeholder: field.placeholder,
                            help_text: field.help_text,
                            required: field.required,
                            options,
                        };
                    }));
                },

                needsChoices(type) {
                    return this.needChoices.includes(type);
                },

                addField() {
                    this.fields.push({
                        key: '',
                        type: 'text',
                        label: '',
                        placeholder: '',
                        help_text: '',
                        required: false,
                        _choicesText: '',
                        _consentText: '',
                        _consentPrivacyUrl: '',
                    });
                },
            };
        }
    </script>
@endonce
