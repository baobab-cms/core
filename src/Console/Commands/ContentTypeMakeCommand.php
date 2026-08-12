<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\ContentTypes\Actions\BuildContentType;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Relations\RelationType;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Assistant interactif de création d'un Content Type (spec 02 §2.1). Assemble
 * un blueprint depuis les réponses puis délègue entièrement à BuildContentType
 * — aucune validation métier ici, une réponse invalide remonte telle quelle
 * via l'exception levée par le pipeline (même comportement que
 * content-type:build).
 */
final class ContentTypeMakeCommand extends Command
{
    protected $signature = 'content-type:make {key? : Clé du Content Type (PascalCase, ex. Car)}';

    protected $description = 'Assistant interactif de création d\'un Content Type.';

    public function handle(BuildContentType $build, FieldRegistry $fields): int
    {
        /** @var string|null $key */
        $key = $this->argument('key');

        if ($key === null) {
            $key = (string) $this->ask('Clé du Content Type (PascalCase, ex. Car)');
        }

        $labelSingular = (string) $this->ask('Libellé singulier', $key);
        $labelPlural = (string) $this->ask('Libellé pluriel', Str::plural($key));
        $isAddressable = (bool) $this->confirm('Ce type a-t-il des pages publiques (adressable) ?', false);

        $collectedFields = $this->collectFields($fields);
        $titleField = $isAddressable ? $this->askTitleField($collectedFields) : null;

        // Les deux autres désignations (spec 02 §3.1) ne gouvernent que
        // l'affichage public : elles n'ont pas de sens sur un type sans pages
        // publiques, exactement comme title_field — et se demandent dans la
        // foulée, tant que les champs sont sous les yeux.
        $designations = $isAddressable ? $this->askDesignations($collectedFields) : [];

        $relations = $this->collectRelations();

        $blueprint = [
            'key' => $key,
            'label' => ['singular' => $labelSingular, 'plural' => $labelPlural],
            'is_addressable' => $isAddressable,
            'fields' => $collectedFields,
            'relations' => $relations,
        ];

        if ($titleField !== null) {
            $blueprint['title_field'] = $titleField;
        }

        foreach ($designations as $designation => $fieldKey) {
            $blueprint[$designation] = $fieldKey;
        }

        $this->summarize($key, $labelSingular, $labelPlural, $isAddressable, $collectedFields, $relations);

        if (! $this->confirm('Construire ce Content Type ?', true)) {
            $this->comment('Annulé.');

            return self::SUCCESS;
        }

        try {
            $contentType = $build((string) json_encode($blueprint));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Content Type [{$contentType->key}] construit avec succès.");
        $this->line("  Table : {$contentType->table_name}");
        $this->line("  Module : {$contentType->module?->name}");

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectFields(FieldRegistry $fields): array
    {
        $collected = [];
        $types = array_keys($fields->all());

        while ($this->confirm('Ajouter un champ ?', true)) {
            $key = (string) $this->ask('Clé du champ (snake_case)');
            $type = $this->askChoice('Type de champ', $types);
            $label = (string) $this->ask('Libellé affiché', Str::headline($key));
            $required = (bool) $this->confirm('Obligatoire ?', false);
            $optionsJson = (string) $this->ask('Options (JSON, vide si aucune)', '');

            $field = ['key' => $key, 'type' => $type, 'required' => $required];

            // Un libellé identique au repli n'est pas écrit : le blueprint
            // reste lisible, et `FieldDisplay::label()` produit exactement la
            // même chose. On n'enregistre que ce que l'auteur a vraiment
            // décidé.
            if ($label !== '' && $label !== Str::headline($key)) {
                $field['label'] = $label;
            }

            if ($optionsJson !== '') {
                $decoded = json_decode($optionsJson, true);
                $field['options'] = is_array($decoded) ? $decoded : [];
            }

            $collected[] = $field;
        }

        return $collected;
    }

    /**
     * @param  list<array<string, mixed>>  $collectedFields
     */
    private function askTitleField(array $collectedFields): ?string
    {
        $eligible = collect($collectedFields)
            ->filter(fn (array $field): bool => in_array($field['type'], ['text', 'textarea', 'richtext'], true))
            ->pluck('key')
            ->all();

        if ($eligible === []) {
            $this->warn('Aucun champ text/textarea/richtext disponible pour servir de source au slug — title_field ne sera pas défini.');

            return null;
        }

        /** @var list<string> $eligible */
        return $this->askChoice('Champ source du slug', $eligible);
    }

    /**
     * `body_field` et `image_field` (spec 02 §3.1) — le champ qui porte la
     * prose éditoriale et l'image mise en avant.
     *
     * Chacune est **proposée seulement si le type porte un champ éligible**,
     * et chacune peut être déclinée : sans elle, `FieldDisplay` retombe sur sa
     * déduction, qui suffit au cas courant. Ce qu'elle sert, c'est le cas
     * ambigu — deux zones de texte, ou deux images —, celui-là même que la
     * déduction ne peut pas trancher.
     *
     * @param  list<array<string, mixed>>  $collectedFields
     * @return array<string, string>
     */
    private function askDesignations(array $collectedFields): array
    {
        $designations = [
            'body_field' => ['types' => ['text', 'textarea', 'richtext'], 'question' => 'Champ portant le contenu éditorial'],
            'image_field' => ['types' => ['image'], 'question' => 'Champ portant l\'image mise en avant'],
        ];

        $declared = [];

        foreach ($designations as $designation => $spec) {
            /** @var list<string> $eligible */
            $eligible = collect($collectedFields)
                ->filter(fn (array $field): bool => in_array($field['type'], $spec['types'], true))
                ->pluck('key')
                ->all();

            if ($eligible === [] || ! $this->confirm("Désigner explicitement le {$designation} ?", count($eligible) > 1)) {
                continue;
            }

            $declared[$designation] = $this->askChoice($spec['question'], $eligible);
        }

        return $declared;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectRelations(): array
    {
        $collected = [];
        $types = array_map(fn (RelationType $type): string => $type->value, RelationType::cases());

        /** @var list<string> $targets */
        $targets = [...ContentType::whereNotNull('module_id')->pluck('key')->all(), 'User'];

        while ($this->confirm('Ajouter une relation ?', false)) {
            $key = (string) $this->ask('Clé de la relation');
            $type = $this->askChoice('Type de relation', $types);
            $target = $this->askChoice('Cible', $targets);

            $collected[] = ['key' => $key, 'type' => $type, 'target' => $target];
        }

        return $collected;
    }

    /**
     * `Command::choice()` returns `array|string` (array only when the
     * `$multiple` argument is used, which none of our prompts do) — narrows
     * back to `string` without a cast, which PHPStan refuses for `array|string`.
     *
     * @param  list<string>  $choices
     */
    private function askChoice(string $question, array $choices, mixed $default = null): string
    {
        $answer = $this->choice($question, $choices, $default);

        if (! is_string($answer)) {
            throw new RuntimeException("Expected a single choice for \"{$question}\".");
        }

        return $answer;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array<string, mixed>>  $relations
     */
    private function summarize(string $key, string $labelSingular, string $labelPlural, bool $isAddressable, array $fields, array $relations): void
    {
        $this->newLine();
        $this->line("Clé : {$key}");
        $this->line("Libellés : {$labelSingular} / {$labelPlural}");
        $this->line('Adressable : '.($isAddressable ? 'oui' : 'non'));
        $this->line('Champs : '.(collect($fields)->pluck('key')->implode(', ') ?: '(aucun)'));
        $this->line('Relations : '.(collect($relations)->pluck('key')->implode(', ') ?: '(aucune)'));
        $this->newLine();
    }
}
