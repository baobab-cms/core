<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Facades\Hook;
use Baobab\Seo\Models\SeoMeta;
use Baobab\Support\Logger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Sauvegarde la metabox SEO d'une entrée (spec 07 §2.1). Appelée par le
 * listener `baobab.content.saved` (BaobabServiceProvider) avec
 * `request()->input('seo', [])` — jamais via `ContentController::validated()`,
 * qui ignore ce bloc (spec 07 §1, « le moteur de contenu ne le connaît
 * pas »). Tous les champs sont optionnels (spec : « Aucun champ n'est
 * obligatoire ») ; l'entrée de contenu elle-même est déjà committée au
 * moment où ce listener s'exécute — une erreur de validation SEO est donc
 * journalée et absorbée, jamais propagée (patron « erreur non fatale »
 * déjà établi pour les widgets, M6 point 4b).
 */
final class UpdateSeoMeta
{
    public function __construct(private readonly Logger $logger) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(Model $entry, array $input): ?SeoMeta
    {
        try {
            $validated = Validator::make($input, [
                'meta_title' => ['nullable', 'string', 'max:255'],
                'meta_description' => ['nullable', 'string', 'max:1000'],
                'robots_noindex' => ['nullable', 'boolean'],
                'robots_nofollow' => ['nullable', 'boolean'],
                'canonical_url' => ['nullable', 'url', 'max:2048'],
                'og_title' => ['nullable', 'string', 'max:255'],
                'og_description' => ['nullable', 'string', 'max:1000'],
                'og_image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            ])->validate();
        } catch (ValidationException $e) {
            $this->logger->warning('Metabox SEO invalide, non sauvegardée.', [
                'entry_type' => $entry->getMorphClass(),
                'entry_id' => $entry->getKey(),
                'errors' => $e->errors(),
            ]);

            return null;
        }

        $meta = SeoMeta::forEntry($entry);
        $meta->robots_noindex = (bool) ($validated['robots_noindex'] ?? false);
        $meta->robots_nofollow = (bool) ($validated['robots_nofollow'] ?? false);
        $meta->fill(array_diff_key($validated, array_flip(['robots_noindex', 'robots_nofollow'])));
        $meta->save();

        Hook::action('baobab.seo.meta.updated', $entry, $meta);

        return $meta;
    }
}
