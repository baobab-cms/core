<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Privacy\Jobs\RunPersonalDataErasureJob;
use Baobab\Privacy\Models\PrivacyRequest;
use Baobab\Privacy\PrivacyRequestStatus;
use Baobab\Privacy\PrivacyRequestType;

/**
 * Met en file les effacements arrivés à échéance (spec 16 §4.3, décision 12).
 * Chaque demande est prise par un `UPDATE … WHERE status = scheduled` : deux
 * passages concurrents de la tâche, ou une annulation au même instant, ne
 * peuvent jamais la prendre deux fois — celui qui obtient 0 ligne passe.
 * La demande passe `pending` (prise en charge), le job la passe `running`.
 */
final class DispatchDueErasures
{
    public function __invoke(): int
    {
        $count = 0;

        $due = PrivacyRequest::query()
            ->where('type', PrivacyRequestType::Erasure)
            ->where('status', PrivacyRequestStatus::Scheduled)
            ->where('scheduled_for', '<=', now());

        foreach ($due->lazyById(100) as $request) {
            $claimed = PrivacyRequest::query()
                ->whereKey($request->getKey())
                ->where('status', PrivacyRequestStatus::Scheduled)
                ->update(['status' => PrivacyRequestStatus::Pending]);

            if ($claimed === 0) {
                continue;
            }

            RunPersonalDataErasureJob::dispatch($request->id)->onQueue('baobab-low');
            $count++;
        }

        return $count;
    }
}
