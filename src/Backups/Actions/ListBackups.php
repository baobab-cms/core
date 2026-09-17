<?php

declare(strict_types=1);

namespace Baobab\Backups\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Liste les sauvegardes présentes sur les destinations configurées
 * (`baobab.backups.destinations`) — extrait de `BackupsController` (M9
 * chantier 0.a Pass D) pour être réutilisé tel quel par le contrôle de
 * santé « Sauvegardes » (spec 12 §7.2, Pass E), qui n'a besoin que de la
 * fraîcheur de la plus récente, pas du jeton de téléchargement (propre à
 * l'écran admin, resté dans le contrôleur).
 */
final class ListBackups
{
    /**
     * @return list<array{disk: string, filename: string, size: int, date: Carbon}>
     */
    public function __invoke(): array
    {
        $name = config('baobab.backups.name');
        $backups = [];

        foreach (config('baobab.backups.destinations') as $disk) {
            foreach (Storage::disk($disk)->files($name) as $path) {
                if (! str_ends_with($path, '.zip')) {
                    continue;
                }

                $backups[] = [
                    'disk' => $disk,
                    'filename' => basename($path),
                    'size' => Storage::disk($disk)->size($path),
                    'date' => Carbon::createFromTimestamp(Storage::disk($disk)->lastModified($path)),
                ];
            }
        }

        usort($backups, fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $backups;
    }
}
