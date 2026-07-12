<?php

declare(strict_types=1);

namespace Baobab\Media\Support;

use Baobab\Media\Exceptions\InvalidMediaUploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Assemble un fichier reçu en chunks (M4 point 1b, spec 06 §3.1) — pas de
 * nouvelle table : chaque chunk vit sur le disque `local` sous
 * `media-chunks/{uploadId}/{index}` le temps de la réception. Une fois tous
 * les chunks présents, ils sont concaténés dans l'ordre en un fichier
 * temporaire dont le chemin est retourné — à l'appelant de le faire passer
 * par UploadMedia (même pipeline que l'upload direct, aucune logique
 * dupliquée) puis de le nettoyer.
 */
final class ChunkedUploadAssembler
{
    private const DISK = 'local';

    private const ROOT = 'media-chunks';

    /**
     * @return string|null Chemin absolu du fichier assemblé une fois complet, null tant que des chunks manquent.
     */
    public function receive(string $uploadId, int $chunkIndex, int $totalChunks, UploadedFile $chunk): ?string
    {
        $this->assertValidUploadId($uploadId);

        $disk = Storage::disk(self::DISK);
        $dir = self::ROOT."/{$uploadId}";

        $disk->putFileAs($dir, $chunk, (string) $chunkIndex);

        for ($index = 0; $index < $totalChunks; $index++) {
            if (! $disk->exists("{$dir}/{$index}")) {
                return null;
            }
        }

        $assembledRelativePath = self::ROOT."/{$uploadId}.assembled";
        $disk->put($assembledRelativePath, '');

        $out = fopen($disk->path($assembledRelativePath), 'wb');

        if ($out === false) {
            throw InvalidMediaUploadException::invalidUploadId();
        }

        for ($index = 0; $index < $totalChunks; $index++) {
            $in = fopen($disk->path("{$dir}/{$index}"), 'rb');

            if ($in !== false) {
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        }

        fclose($out);

        $disk->deleteDirectory($dir);

        return $disk->path($assembledRelativePath);
    }

    private function assertValidUploadId(string $uploadId): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uploadId) !== 1) {
            throw InvalidMediaUploadException::invalidUploadId();
        }
    }
}
