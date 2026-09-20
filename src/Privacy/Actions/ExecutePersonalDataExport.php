<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Mail\Mailer;
use Baobab\Privacy\Exceptions\NoPersonalDataException;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;
use Baobab\Support\Logger;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Exécute une demande d'export (spec 16 §4.1, décision 10) : assemble
 * l'archive par `ExportPersonalData`, la dépose sur le disque d'exports sous
 * `privacy-exports/{uuid}.zip`, conserve son mot de passe **chiffré sur la
 * demande** jusqu'à sa première lecture, fixe l'échéance et prévient le
 * sujet par e-mail — lien signé seulement, **jamais** le mot de passe (canaux
 * distincts, §4.1).
 *
 * Un échec ne fuit pas : le motif lisible d'un sujet sans donnée est
 * conservé, toute autre erreur est journalisée et la demande porte un motif
 * générique — un message d'exception peut citer des données personnelles.
 */
final class ExecutePersonalDataExport
{
    public function __construct(
        private readonly ExportPersonalData $export,
        private readonly Mailer $mailer,
        private readonly Logger $logger,
    ) {}

    public function __invoke(PrivacyRequest $request): bool
    {
        if ($request->type !== PrivacyRequestType::Export || $request->status !== PrivacyRequestStatus::Pending) {
            return false;
        }

        $request->update(['status' => PrivacyRequestStatus::Running, 'started_at' => now()]);

        try {
            $archive = ($this->export)($request->subject());
        } catch (NoPersonalDataException $e) {
            $this->fail($request, $e->getMessage());

            return false;
        } catch (Throwable $e) {
            $this->logger->error("Échec de l'export de données personnelles.", ['request' => $request->uuid, 'exception' => $e]);
            $this->fail($request, __('baobab::privacy.export.failed'));

            return false;
        }

        try {
            $disk = (string) config('baobab.exports.disk');
            $path = "privacy-exports/{$request->uuid}.zip";
            $stream = fopen($archive->path, 'rb');

            if ($stream === false) {
                throw new \RuntimeException("Archive d'export illisible.");
            }

            try {
                Storage::disk($disk)->writeStream($path, $stream);
            } finally {
                fclose($stream);
            }

            $request->update([
                'status' => PrivacyRequestStatus::Completed,
                'file_disk' => $disk,
                'file_path' => $path,
                'file_size' => Storage::disk($disk)->size($path),
                'password' => $archive->password,
                'expires_at' => now()->addDays((int) config('baobab.privacy.export_retention_days', 7)),
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $this->logger->error("Échec du dépôt de l'archive d'export.", ['request' => $request->uuid, 'exception' => $e]);
            $this->fail($request, __('baobab::privacy.export.failed'));

            return false;
        } finally {
            @unlink($archive->path);
        }

        $this->notify($request);

        return true;
    }

    private function notify(PrivacyRequest $request): void
    {
        $to = $request->subject_email
            ?? (string) User::query()->whereKey($request->subject_user_id)->value('email');

        if ($to === '') {
            return;
        }

        $this->mailer->send('core.privacy_export_ready', $to, [
            'download_url' => URL::signedRoute('baobab.privacy.export.download', ['privacyRequest' => $request->uuid]),
            'expires_at' => $request->expires_at?->format('d/m/Y H:i') ?? '',
        ]);
    }

    private function fail(PrivacyRequest $request, string $message): void
    {
        $request->update([
            'status' => PrivacyRequestStatus::Failed,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
