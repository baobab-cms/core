@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.branding.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.branding.title')">
        {{--
            Formulaire de réinitialisation déclaré à côté du formulaire principal,
            jamais dedans : imbriquer un <form> dans un autre est invalide en HTML
            et le navigateur abandonne le formulaire intérieur. Les boutons
            « Réinitialiser » s'y rattachent par form="" et choisissent leur cible
            par formaction — même patron que les actions groupées de
            <x-baobab::table>.
        --}}
        <form id="branding-reset" method="POST" action="{{ route('admin.branding.reset') }}">
            @csrf
        </form>

        <x-baobab::card class="mb-6">
            <x-baobab::form method="POST" action="{{ route('admin.branding.update') }}">
                <h2 class="mb-4 font-display text-lg font-semibold text-foreground">{{ __('baobab::admin.branding.section_identity') }}</h2>

                <x-baobab::field.media
                    name="logo_media_id"
                    label="{{ __('baobab::admin.branding.logo_label') }}"
                    type="image"
                    :media="$setting->logo"
                />

                <x-baobab::field.media
                    name="favicon_media_id"
                    label="{{ __('baobab::admin.branding.favicon_label') }}"
                    type="image"
                    :media="$setting->favicon"
                />

                <x-baobab::field.color
                    name="primary_color"
                    :label="__('baobab::admin.branding.primary_color_label')"
                    :value="$colors['primary']"
                    :reset-action="route('admin.branding.reset-token', ['group' => 'colors', 'key' => 'primary'])"
                />

                <h2 class="mb-4 mt-8 font-display text-lg font-semibold text-foreground">{{ __('baobab::admin.branding.section_colors') }}</h2>

                @foreach ($colors as $key => $value)
                    @if ($key !== 'primary')
                        <x-baobab::field.color
                            name="tokens[colors][{{ $key }}]"
                            :label="__('baobab::admin.branding.color_'.$key.'_label')"
                            :value="$value"
                            :reset-action="route('admin.branding.reset-token', ['group' => 'colors', 'key' => $key])"
                        />
                    @endif
                @endforeach

                <h2 class="mb-4 mt-8 font-display text-lg font-semibold text-foreground">{{ __('baobab::admin.branding.section_fonts') }}</h2>

                @foreach ($fonts as $key => $value)
                    <x-baobab::field.text
                        name="tokens[fonts][{{ $key }}]"
                        label="{{ __('baobab::admin.branding.font_'.$key.'_label') }}"
                        :value="$value"
                    />
                @endforeach

                @foreach (['text' => $text, 'leading' => $leading, 'weight' => $weight] as $group => $values)
                    @foreach ($values as $key => $value)
                        <x-baobab::field.text
                            name="tokens[{{ $group }}][{{ $key }}]"
                            label="{{ __('baobab::admin.branding.'.$group.'_'.$key.'_label') }}"
                            :value="$value"
                        />
                    @endforeach
                @endforeach

                <h2 class="mb-4 mt-8 font-display text-lg font-semibold text-foreground">{{ __('baobab::admin.branding.section_surfaces') }}</h2>

                @foreach (['radius' => $radius, 'spacing' => $spacing, 'shadow' => $shadow] as $group => $values)
                    @foreach ($values as $key => $value)
                        <x-baobab::field.text
                            name="tokens[{{ $group }}][{{ $key }}]"
                            label="{{ __('baobab::admin.branding.'.$group.'_'.$key.'_label') }}"
                            :value="$value"
                        />
                    @endforeach
                @endforeach

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.branding.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>

        <x-baobab::card class="mb-6">
            <x-slot:header>
                <span class="font-display font-medium text-foreground">{{ __('baobab::admin.branding.section_profile') }}</span>
            </x-slot:header>

            @if ($profileModified)
                <p class="mb-3 text-xs text-warning">{{ __('baobab::admin.branding.profile_modified') }}</p>
            @endif

            {{-- Aperçu des pastilles (spec 18 §8) : on choisissait un preset par
                 son nom, sans voir ce qu'il change. Les couleurs viennent du
                 contrôleur, la vue ne connaît pas le vocabulaire de tokens. --}}
            <ul class="mb-4 space-y-1.5">
                @foreach ($profiles as $slug => $profile)
                    <li class="flex items-center gap-3 text-sm">
                        <span class="flex shrink-0 gap-1" role="img" aria-label="{{ __('baobab::admin.branding.profile_swatches_label') }}">
                            @foreach ($profileSwatches[$slug] ?? [] as $swatch)
                                <span class="h-4 w-4 rounded-full border border-border" style="background-color: {{ $swatch }}"></span>
                            @endforeach
                        </span>

                        <span @class(['text-foreground', 'font-medium' => $slug === $currentProfile])>{{ $profile['label'] }}</span>

                        @if ($slug === $currentProfile)
                            <x-baobab::badge variant="success">{{ __('baobab::admin.branding.profile_applied_badge') }}</x-baobab::badge>
                        @endif
                    </li>
                @endforeach
            </ul>

            <x-baobab::form method="POST" action="{{ route('admin.branding.profile') }}">
                <x-baobab::field.select
                    name="profile"
                    :label="__('baobab::admin.branding.profile_label')"
                    :value="$currentProfile"
                    :options="$profileOptions"
                />

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.branding.profile_apply_action') }}</x-baobab::button>
            </x-baobab::form>

            {{-- Réinitialisation globale (spec 18 §8, « vider les niveaux 3–4 »).
                 Le bouton vise le formulaire déclaré en tête d'écran ; sa
                 confirmation énonce la conséquence plutôt que « êtes-vous
                 sûr ? » — patron de la désactivation de thème (suivi n° 126). --}}
            <div class="mt-6 border-t border-border pt-4">
                <p class="mb-2 text-xs text-muted">{{ __('baobab::admin.branding.reset_all_confirm') }}</p>

                <button
                    type="submit"
                    form="branding-reset"
                    class="rounded-md border border-border px-3 py-2 text-sm font-medium text-foreground hover:bg-surface-subtle"
                >
                    {{ __('baobab::admin.branding.reset_all_action') }}
                </button>
            </div>
        </x-baobab::card>

        @if ($canManageFonts)
            <x-baobab::card>
                <x-slot:header>
                    <span class="font-display font-medium text-foreground">{{ __('baobab::admin.branding.fonts.title') }}</span>
                </x-slot:header>

                <table class="mb-6 w-full text-left text-sm">
                    <thead class="text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2 font-medium">{{ __('baobab::admin.branding.fonts.column_family') }}</th>
                            <th class="py-2 font-medium">{{ __('baobab::admin.branding.fonts.column_source') }}</th>
                            <th class="py-2 font-medium">{{ __('baobab::admin.branding.fonts.column_license') }}</th>
                            <th class="py-2 font-medium">{{ __('baobab::admin.branding.fonts.column_usage') }}</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($registeredFonts as $font)
                            <tr>
                                <td class="py-2 text-foreground">{{ $font->family }}</td>
                                <td class="py-2 text-muted">{{ __('baobab::admin.branding.fonts.source_'.$font->source) }}</td>
                                <td class="py-2 text-muted">{{ $font->license ?? '—' }}</td>
                                <td class="py-2">
                                    @if ($fontUsage[$font->id] ?? false)
                                        <x-baobab::badge variant="success">{{ __('baobab::admin.branding.fonts.in_use') }}</x-baobab::badge>
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    @if ($font->source !== 'bundled')
                                        <x-baobab::form method="DELETE" action="{{ route('admin.branding.fonts.destroy', $font) }}">
                                            <x-baobab::button type="submit" variant="ghost" class="text-danger">
                                                {{ __('baobab::admin.branding.fonts.delete_action') }}
                                            </x-baobab::button>
                                        </x-baobab::form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <h3 class="mb-4 font-display text-base font-medium text-foreground">{{ __('baobab::admin.branding.fonts.upload_title') }}</h3>

                <x-baobab::form method="POST" action="{{ route('admin.branding.fonts.store') }}" enctype="multipart/form-data">
                    <x-baobab::field.text name="family" label="{{ __('baobab::admin.branding.fonts.family_label') }}" />

                    <div class="mb-4">
                        <label for="file" class="mb-1 block text-sm font-medium text-foreground">{{ __('baobab::admin.branding.fonts.file_label') }}</label>
                        <input type="file" id="file" name="file" accept=".woff2" class="w-full text-sm text-foreground">
                        @error('file')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-baobab::field.text name="license" label="{{ __('baobab::admin.branding.fonts.license_label') }}" />

                    <div class="mb-4">
                        <label for="license_file" class="mb-1 block text-sm font-medium text-foreground">{{ __('baobab::admin.branding.fonts.license_file_label') }}</label>
                        <input type="file" id="license_file" name="license_file" class="w-full text-sm text-foreground">
                    </div>

                    <x-baobab::field.checkbox
                        name="license_attested"
                        :label="__('baobab::admin.branding.fonts.license_attested_label')"
                    />

                    <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.branding.fonts.upload_action') }}</x-baobab::button>
                </x-baobab::form>
            </x-baobab::card>
        @endif
    </x-baobab::page>
@endsection
