<?php

declare(strict_types=1);

namespace Baobab\Privacy\Jobs;

use Baobab\Privacy\Actions\ExecutePersonalDataExport;
use Baobab\Privacy\Models\PrivacyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fin wrapper de queue (patron `RunContentExportJob`) : l'exécution réelle
 * est `ExecutePersonalDataExport`, seule la mise sur `baobab-low` est ici.
 */
final class RunPersonalDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $privacyRequestId) {}

    public function handle(ExecutePersonalDataExport $action): void
    {
        $request = PrivacyRequest::find($this->privacyRequestId);

        if ($request === null) {
            return;
        }

        $action($request);
    }
}
