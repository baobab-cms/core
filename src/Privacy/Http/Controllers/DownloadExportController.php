<?php

declare(strict_types=1);

namespace Baobab\Privacy\Http\Controllers;

use Baobab\Audit\AuditLogger;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Téléchargement d'une archive d'export (spec 16 §4.1) : route publique,
 * signée (le lien part par e-mail) et rate-limitée. L'archive est chiffrée ;
 * le mot de passe voyage par un autre canal. 410 une fois l'échéance passée
 * ou l'archive purgée — c'est pourquoi la signature n'expire pas d'elle-même :
 * l'échéance se lit sur la demande, pas dans l'URL. Chaque téléchargement est
 * audité.
 */
final class DownloadExportController
{
    public function __invoke(PrivacyRequest $privacyRequest, AuditLogger $audit): StreamedResponse
    {
        abort_unless($privacyRequest->type === PrivacyRequestType::Export, 404);

        abort_if(
            $privacyRequest->status === PrivacyRequestStatus::Expired
            || ($privacyRequest->expires_at !== null && $privacyRequest->expires_at->isPast()),
            410,
        );

        abort_unless($privacyRequest->isDownloadable(), 404);

        /** @var string $disk */
        $disk = $privacyRequest->file_disk;
        /** @var string $path */
        $path = $privacyRequest->file_path;

        abort_unless(Storage::disk($disk)->exists($path), 404);

        $audit->record('privacy.export.downloaded', $privacyRequest);

        return Storage::disk($disk)->download($path, 'donnees-personnelles-'.now()->format('Y-m-d').'.zip');
    }
}
