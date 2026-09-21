<?php

declare(strict_types=1);

namespace Baobab\Privacy\Jobs;

use Baobab\Privacy\Actions\ExecutePersonalDataErasure;
use Baobab\Privacy\Models\PrivacyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fin wrapper de queue (patron `RunPersonalDataExportJob`) : l'exécution
 * réelle est `ExecutePersonalDataErasure`, seule la mise sur `baobab-low` est ici.
 */
final class RunPersonalDataErasureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $privacyRequestId) {}

    public function handle(ExecutePersonalDataErasure $action): void
    {
        $request = PrivacyRequest::find($this->privacyRequestId);

        if ($request === null) {
            return;
        }

        $action($request);
    }
}
