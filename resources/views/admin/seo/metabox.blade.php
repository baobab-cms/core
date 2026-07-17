{{--
    Rendue via `view('baobab::admin.seo.metabox', [...])->render()` par le
    listener `baobab.content.form.sections` (BaobabServiceProvider) — jamais
    incluse directement par `form.blade.php`, qui ne connaît pas le SEO
    (spec 07 §1). `old('seo.xxx', ...)` ne restaure pas ces champs après un
    échec de validation des champs du contenu lui-même (limitation connue,
    documentée : `old()` ne résout pas une notation `seo[xxx]` en crochets —
    sans conséquence grave, la pire issue est un champ SEO à retaper).
--}}
<x-baobab::card class="mb-6" :header="__('baobab::admin.seo.metabox_title')">
    <div x-data="baobabSeoMetabox({
        title: @js(old('seo.meta_title', $seoMeta->meta_title) ?? ''),
        description: @js(old('seo.meta_description', $seoMeta->meta_description) ?? ''),
    })">
        <x-baobab::field.text
            name="seo[meta_title]"
            :label="__('baobab::admin.seo.meta_title_label')"
            :value="old('seo.meta_title', $seoMeta->meta_title)"
            x-on:input="title = $event.target.value"
        />
        <p class="-mt-3 mb-4 text-xs text-muted" x-text="title.length + ' / 60'"></p>

        <x-baobab::field.textarea
            name="seo[meta_description]"
            :label="__('baobab::admin.seo.meta_description_label')"
            :value="old('seo.meta_description', $seoMeta->meta_description)"
            x-on:input="description = $event.target.value"
        />
        <p class="-mt-3 mb-4 text-xs text-muted" x-text="description.length + ' / 160'"></p>

        <div class="mb-4 rounded-md border border-border p-3">
            <p class="text-xs uppercase tracking-wide text-muted">{{ __('baobab::admin.seo.serp_preview_label') }}</p>
            <p class="mt-1 truncate text-base text-primary" x-text="title || @js($previewFallbackTitle)"></p>
            @if ($previewUrl)
                <p class="truncate text-sm text-emerald-700">{{ $previewUrl }}</p>
            @endif
            <p class="text-sm text-foreground" x-text="description"></p>
        </div>

        <div class="mb-4 flex flex-wrap gap-6">
            <x-baobab::field.checkbox
                name="seo[robots_noindex]"
                :label="__('baobab::admin.seo.robots_noindex_label')"
                :checked="(bool) old('seo.robots_noindex', $seoMeta->robots_noindex)"
            />
            <x-baobab::field.checkbox
                name="seo[robots_nofollow]"
                :label="__('baobab::admin.seo.robots_nofollow_label')"
                :checked="(bool) old('seo.robots_nofollow', $seoMeta->robots_nofollow)"
            />
        </div>

        <x-baobab::field.text
            name="seo[canonical_url]"
            :label="__('baobab::admin.seo.canonical_label')"
            :value="old('seo.canonical_url', $seoMeta->canonical_url)"
        />

        <x-baobab::field.text
            name="seo[og_title]"
            :label="__('baobab::admin.seo.og_title_label')"
            :value="old('seo.og_title', $seoMeta->og_title)"
        />

        <x-baobab::field.textarea
            name="seo[og_description]"
            :label="__('baobab::admin.seo.og_description_label')"
            :value="old('seo.og_description', $seoMeta->og_description)"
        />

        <x-baobab::field.media
            name="seo[og_image_media_id]"
            :label="__('baobab::admin.seo.og_image_label')"
            type="image"
            :media="$seoMeta->ogImage"
        />
    </div>
</x-baobab::card>

@once
    <script>
        function baobabSeoMetabox(config) {
            return {
                title: config.title,
                description: config.description,
            };
        }
    </script>
@endonce
