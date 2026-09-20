<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Illuminate\Support\Facades\Storage;

/**
 * Détruit les archives d'export échues (spec 16 §4.1) : le fichier, le mot de
 * passe resté non lu, puis la demande passe à `expired`. **La ligne reste** :
 * elle est la trace que l'export a eu lieu — sa purge est différée (suivi
 * n° 337).
 */
final class PurgeExpiredPrivacyExports
{
    public function __invoke(): int
    {
        $count = 0;

        $expired = PrivacyRequest::query()
            ->where('type', PrivacyRequestType::Export)
            ->where('status', PrivacyRequestStatus::Completed)
            ->where('expires_at', '<=', now());

        foreach ($expired->lazyById(100) as $request) {
            if ($request->file_disk !== null && $request->file_path !== null) {
                Storage::disk($request->file_disk)->delete($request->file_path);
            }

            $request->update([
                'status' => PrivacyRequestStatus::Expired,
                'file_disk' => null,
                'file_path' => null,
                'password' => null,
            ]);
            $count++;
        }

        return $count;
    }
}
