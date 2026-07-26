<?php

declare(strict_types=1);

namespace Baobab\Themes\Actions;

use Baobab\Themes\Blueprint\ThemeBlueprintValidator;
use Baobab\Themes\Exceptions\InvalidThemeBlueprintException;
use Baobab\Themes\Exceptions\ThemeBlueprintAlreadyExistsException;
use Illuminate\Support\Facades\File;

/**
 * Écrit `themes/{slug}/theme.json` (ébauche Studio thèmes, spec 17 §1, M8
 * point 5 Pass B) — édite uniquement l'identité, les menus, les zones de
 * widgets et les supports ; `content_types`/`tokens`/`fonts` d'un fichier
 * existant sont préservés tels quels (cette Action ne les connaît pas).
 * Pas de mécanisme de checksum ici (contrairement à `GeneratedFileChecksums`
 * utilisé par `ThemeGenerator`) : ce fichier est l'entrée du générateur, pas
 * une sortie générée — il n'y a rien à protéger d'un écrasement par la
 * génération elle-même, seulement des créations en double (`$isCreate`).
 */
final class SaveThemeBlueprint
{
    public function __construct(private readonly ThemeBlueprintValidator $validator) {}

    /**
     * @param  array{name: string, slug: string, menus?: array<string, string>, widget_zones?: array<string, string>, supports?: list<string>}  $attributes
     *
     * @throws ThemeBlueprintAlreadyExistsException
     * @throws InvalidThemeBlueprintException
     */
    public function __invoke(array $attributes, bool $isCreate = false): void
    {
        $themeDir = base_path("themes/{$attributes['slug']}");
        $path = "{$themeDir}/theme.json";

        if ($isCreate && File::isFile($path)) {
            throw ThemeBlueprintAlreadyExistsException::forSlug($attributes['slug']);
        }

        /** @var array<string, mixed> $existing */
        $existing = File::isFile($path)
            ? (array) json_decode((string) File::get($path), associative: true)
            : [];

        $blueprint = array_merge($existing, [
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
            'menus' => (object) ($attributes['menus'] ?? []),
            'widget_zones' => (object) ($attributes['widget_zones'] ?? []),
            'supports' => $attributes['supports'] ?? [],
        ]);

        // `content_types`/`tokens` préservés d'un fichier existant : un bloc
        // vide y décoderait en tableau PHP `[]`, ré-encodé à tort en JSON
        // `[]` plutôt que l'objet `{}` attendu par le schéma (même piège que
        // ThemeGenerator::moduleJson() pour menus/widget_zones).
        foreach (['content_types', 'tokens'] as $objectKey) {
            if (isset($blueprint[$objectKey]) && $blueprint[$objectKey] === []) {
                $blueprint[$objectKey] = (object) [];
            }
        }

        $json = (string) json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->validator->validate($json);

        File::ensureDirectoryExists($themeDir);
        File::put($path, $json);
    }
}
