<?php

declare(strict_types=1);

namespace Baobab\Forms\Blueprint;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\Forms\Exceptions\InvalidFormBlueprintException;
use Baobab\Forms\Support\FormFieldTypes;
use Illuminate\Support\Facades\Validator;

/**
 * Représentation validée d'un blueprint de formulaire (spec 14 §2). Contrairement
 * à `Baobab\ContentTypes\Blueprint\ContentTypeBlueprint`, aucun schéma JSON
 * dédié n'existe encore ici : la surface d'un formulaire (une liste de champs,
 * sans relations ni colonnes générées) ne le justifie pas à ce stade — à
 * revoir si le besoin apparaît une fois le constructeur admin (Pass B) en
 * usage réel.
 *
 * @phpstan-type FormField array{key: string, type: string, label: ?string, placeholder: ?string, help_text: ?string, required: bool, options: array<string, mixed>}
 */
final readonly class FormBlueprint
{
    /**
     * @param  list<FormField>  $fields
     */
    private function __construct(private array $fields) {}

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public static function fromArray(array $fields, ?FieldRegistry $registry = null): self
    {
        $registry ??= app(FieldRegistry::class);
        $seenKeys = [];
        $normalized = [];

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? '');

            if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                throw InvalidFormBlueprintException::forField("fields.{$key}.key", 'La clé doit être en minuscules, commencer par une lettre (a-z0-9_).');
            }

            if (isset($seenKeys[$key])) {
                throw InvalidFormBlueprintException::forField("fields.{$key}.key", 'Clé de champ dupliquée.');
            }

            $seenKeys[$key] = true;

            self::validateType($key, $type, $registry);

            $options = (array) ($field['options'] ?? []);
            self::validateOptions($key, $type, $options, $registry);

            $normalized[] = [
                'key' => $key,
                'type' => $type,
                'label' => self::nullableString($field['label'] ?? null),
                'placeholder' => self::nullableString($field['placeholder'] ?? null),
                'help_text' => self::nullableString($field['help_text'] ?? null),
                'required' => (bool) ($field['required'] ?? false),
                'options' => $options,
            ];
        }

        return new self($normalized);
    }

    /**
     * @return list<FormField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @return array{fields: list<FormField>}
     */
    public function toArray(): array
    {
        return ['fields' => $this->fields];
    }

    private static function validateType(string $key, string $type, FieldRegistry $registry): void
    {
        if ($type === 'honeypot') {
            // spec 14 §2.2 : « invisible, ajouté automatiquement — jamais
            // déclaré manuellement ». Un blueprint qui le porterait viendrait
            // d'un import trafiqué ou d'un bug d'écran ; le rejeter ici évite
            // qu'un second honeypot, non reconnu par le mécanisme anti-spam
            // (Pass D), ne se retrouve validé comme un champ ordinaire.
            throw InvalidFormBlueprintException::forField("fields.{$key}.type", 'Le honeypot est injecté automatiquement, il ne se déclare pas dans le blueprint.');
        }

        if ($type === 'consent' || $type === 'file') {
            // `file` (spec 14 §5, Pass C3) est traité comme `consent` : un type
            // spécial propre aux formulaires, jamais résolu via le
            // `FieldRegistry` (spec 02) — le champ fichier des Content Types
            // cible la médiathèque publique, ce que la spec 14 §5 interdit ici.
            return;
        }

        if (! FormFieldTypes::isAllowed($type)) {
            throw InvalidFormBlueprintException::forField("fields.{$key}.type", "Type de champ inconnu ou non ouvert aux formulaires : « {$type} ».");
        }

        if (! $registry->has(FormFieldTypes::registryKey($type))) {
            throw InvalidFormBlueprintException::forField("fields.{$key}.type", "Type de champ « {$type} » absent du catalogue.");
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function validateOptions(string $key, string $type, array $options, FieldRegistry $registry): void
    {
        $rules = match ($type) {
            'consent' => ['text' => ['required', 'string'], 'privacy_url' => ['nullable', 'url']],
            // Resserre les plafonds globaux (`config('baobab.forms')`), ne les
            // élargit jamais — `FormEntryRules` retombe sur la config quand ces
            // clés sont absentes, jamais l'inverse (spec 14 §5, « par champ +
            // globale »).
            'file' => ['mime_types' => ['nullable', 'array'], 'mime_types.*' => ['string'], 'max_size' => ['nullable', 'integer', 'min:1']],
            default => $registry->resolve(FormFieldTypes::registryKey($type))->optionsRules(),
        };

        if ($rules === []) {
            return;
        }

        $result = Validator::make($options, $rules);

        if ($result->fails()) {
            throw InvalidFormBlueprintException::forField("fields.{$key}.options", (string) $result->errors()->first());
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
