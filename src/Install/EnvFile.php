<?php

declare(strict_types=1);

namespace Baobab\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Écriture du fichier `.env` de l'instance (spec 15 §4, étapes 2 et 5).
 *
 * **Rien dans le Core n'écrivait `.env` avant l'installateur** : ce mécanisme
 * n'existait pas, il n'est pas repris d'ailleurs.
 *
 * La règle qui gouverne cette classe : **on modifie, on ne régénère pas.**
 * Le fichier livré par l'archive porte des commentaires qui expliquent chaque
 * bloc à quelqu'un qui n'a ni shell ni documentation sous la main — le
 * plancher PHP, le refus de SQLite en production, les locales publiées. Les
 * réécrire à plat les effacerait, et l'utilisateur perdrait précisément ce
 * qu'on a mis là pour lui. Une clé connue est donc remplacée sur place, ligne
 * pour ligne ; une clé inconnue est ajoutée à la fin.
 */
final readonly class EnvFile
{
    public function __construct(
        private Filesystem $files,
        private string $path,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return $this->files->isFile($this->path);
    }

    /**
     * Crée le fichier depuis `.env.example` s'il n'existe pas encore.
     *
     * Ne fait rien si le fichier est déjà là : une installation reprise après
     * coupure ne doit pas perdre ce que l'étape précédente y avait écrit.
     */
    public function createFromExample(string $examplePath): void
    {
        if ($this->exists() || ! $this->files->isFile($examplePath)) {
            return;
        }

        $this->files->copy($examplePath, $this->path);
    }

    public function get(string $key): ?string
    {
        foreach ($this->lines() as $line) {
            if ($this->keyOf($line) === $key) {
                return $this->unquote(trim(substr($line, strpos($line, '=') + 1)));
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|bool|int|null>  $values
     */
    public function set(array $values): void
    {
        $lines = $this->lines();
        $remaining = $values;

        // 1. Les clés actives sont remplacées là où elles sont.
        foreach ($lines as $index => $line) {
            $key = $this->keyOf($line);

            if ($key === null || ! array_key_exists($key, $remaining)) {
                continue;
            }

            $lines[$index] = $key.'='.$this->format($remaining[$key]);
            unset($remaining[$key]);
        }

        // 2. Une clé qui n'existe qu'en commentaire est **décommentée sur
        //    place**, jamais ajoutée à la fin.
        //
        //    Le `.env.example` de l'archive propose des clés commentées —
        //    `# DB_DATABASE=`, `# DB_HOST=` — entourées du commentaire qui les
        //    explique. Les ajouter en fin de fichier laisserait la version
        //    commentée sous les yeux de l'utilisateur, avec la vraie valeur
        //    soixante lignes plus bas : le fichier paraîtrait inchangé là où il
        //    compte. Relevé en recette, l'utilisateur ayant conclu — à raison —
        //    que le fichier n'avait pas bougé.
        foreach ($lines as $index => $line) {
            $key = $this->commentedKeyOf($line);

            if ($key === null || ! array_key_exists($key, $remaining)) {
                continue;
            }

            $lines[$index] = $key.'='.$this->format($remaining[$key]);
            unset($remaining[$key]);
        }

        // 3. Ce que le fichier ne connaissait sous aucune forme.
        foreach ($remaining as $key => $value) {
            $lines[] = $key.'='.$this->format($value);
        }

        $this->files->put($this->path, implode("\n", $lines)."\n");
    }

    /**
     * La clé d'une ligne commentée de la forme `# CLE=valeur`, ou `null`.
     *
     * **Strict à dessein** : la clé doit toucher le signe égal. Un commentaire
     * en prose comme `# Utilisation = réservée aux tests` contient un signe
     * égal sans être une affectation ; le décommenter détruirait une phrase
     * écrite pour l'utilisateur, et lui ferait lire une clé qu'il n'a jamais
     * posée. La tolérance appliquée aux lignes actives — où une espace avant
     * le `=` reste une affectation — n'a pas lieu d'être ici.
     */
    private function commentedKeyOf(string $line): ?string
    {
        return preg_match('/^\s*#\s*([A-Za-z_][A-Za-z0-9_]*)=/', $line, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $raw = (string) $this->files->get($this->path);

        return explode("\n", rtrim(str_replace("\r\n", "\n", $raw), "\n"));
    }

    /**
     * La clé d'une ligne, ou `null` si la ligne est un commentaire ou vide.
     */
    private function keyOf(string $line): ?string
    {
        $trimmed = ltrim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            return null;
        }

        $position = strpos($trimmed, '=');

        if ($position === false) {
            return null;
        }

        $key = rtrim(substr($trimmed, 0, $position));

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) === 1 ? $key : null;
    }

    /**
     * Cite ce qui doit l'être, et rien d'autre.
     *
     * Un mot de passe de base de données contient volontiers une espace ou un
     * dièse ; non cité, il tronquerait la valeur au premier de ces caractères
     * et donnerait une erreur de connexion incompréhensible.
     */
    private function format(string|bool|int|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        return preg_match('/[\s#"\']/', $value) === 1
            ? '"'.str_replace(['"', '$'], [chr(92).'"', chr(92).'$'], $value).'"'
            : $value;
    }

    private function unquote(string $value): string
    {
        if (mb_strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[mb_strlen($value) - 1] === $value[0]) {
            $inner = mb_substr($value, 1, -1);

            return $value[0] === '"'
                ? str_replace([chr(92).'"', chr(92).'$'], ['"', '$'], $inner)
                : $inner;
        }

        return $value;
    }
}
