<?php

declare(strict_types=1);

namespace Baobab\Forms\Support;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\Forms\Models\Form;
use Illuminate\Validation\Rule;

/**
 * Construction des règles de validation Laravel d'une soumission depuis le
 * blueprint du formulaire (spec 14 §5) — patron
 * `Baobab\ContentTypes\Support\ContentEntryRules`, en plus simple : pas de
 * relations, pas de colonne `slug`/`unpublish_at`. Le honeypot n'y figure
 * jamais (§2.2, résolu par l'anti-spam en Pass D, pas par la validation).
 */
final class FormEntryRules
{
    public function __construct(private readonly FieldRegistry $fields) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(Form $form): array
    {
        $rules = [];

        foreach ((array) ($form->blueprint['fields'] ?? []) as $field) {
            $key = $field['key'];
            $required = (bool) ($field['required'] ?? false);

            if ($field['type'] === 'consent') {
                $rules[$key] = $required ? ['accepted'] : ['nullable', 'boolean'];

                continue;
            }

            if ($field['type'] === 'file') {
                $rules[$key] = $this->fileRules($field, $required);

                continue;
            }

            $fieldType = $this->fields->resolve(FormFieldTypes::registryKey($field['type']));
            $options = (array) ($field['options'] ?? []);

            $rules[$key] = array_merge(
                $required ? ['required'] : ['nullable'],
                $fieldType->rules($key, $options),
            );

            if ($field['type'] === 'checkboxes') {
                $rules["{$key}.*"] = [Rule::in((array) ($options['choices'] ?? []))];
            }
        }

        return $rules;
    }

    /**
     * `mimetypes:` (spec 14 §5, « type MIME réel, pas l'extension ») plutôt
     * qu'une assertion maison façon `UploadMedia::assertMimeTypeAllowed()` :
     * la règle native de Laravel sniffe déjà le contenu réel du fichier
     * (Symfony `MimeTypeGuesser`, pas l'extension déclarée par le client) —
     * dupliquer ce contrôle aurait été un second mécanisme pour la même
     * garantie. `max:` est en kilo-octets (convention Laravel), d'où la
     * division ici plutôt que dans la config, exprimée en octets partout
     * ailleurs (`baobab.media.max_upload_size`, patron suivi).
     *
     * @param  array{options?: array<string, mixed>}  $field
     * @return list<mixed>
     */
    private function fileRules(array $field, bool $required): array
    {
        $options = (array) ($field['options'] ?? []);

        /** @var list<string> $mimeTypes */
        $mimeTypes = $options['mime_types'] ?? (array) config('baobab.forms.allowed_mime_types', []);
        $maxBytes = (int) ($options['max_size'] ?? config('baobab.forms.max_upload_size', 10_485_760));

        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimetypes:'.implode(',', $mimeTypes),
            'max:'.intdiv($maxBytes, 1024),
        ];
    }
}
