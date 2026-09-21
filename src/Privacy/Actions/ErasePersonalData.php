<?php

declare(strict_types=1);

namespace Baobab\Privacy\Actions;

use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Audit\AuditLogger;
use Baobab\Privacy\Contracts\ErasesPersonalData;
use Baobab\Privacy\ErasureResult;
use Baobab\Privacy\Exceptions\NoPersonalDataException;
use Baobab\Privacy\PrivacyRegistry;
use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\ErasureGuard;
use Baobab\Privacy\Support\Pseudonym;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Droit à l'effacement (spec 16 §4.3, §5). Appelle `erase()` sur chaque
 * fournisseur concerné, qui choisit sa stratégie et la documente ; le
 * rapport consolidé est consigné dans l'audit comme preuve d'exécution,
 * référencé par le pseudonyme du sujet, jamais par son e-mail.
 *
 * S'exécute immédiatement : le délai de grâce et son annulation sont portés
 * par les demandes d'effacement planifiées (`CreatePrivacyRequest`,
 * `ExecutePersonalDataErasure`, décision 12) ; cette action est le moteur
 * qu'elles appellent à l'échéance, et la voie `--now` de la CLI.
 */
final class ErasePersonalData
{
    public function __construct(
        private readonly PrivacyRegistry $registry,
        private readonly AuditLogger $audit,
        private readonly ErasureGuard $guard,
    ) {}

    /**
     * @throws NoPersonalDataException quand aucun fournisseur ne détient de donnée du sujet
     * @throws AdminLockoutException quand le sujet est le dernier super-administrateur
     */
    public function __invoke(Subject $subject): ErasureResult
    {
        $subject = $this->resolve($subject);
        $user = $subject->userId === null ? null : User::query()->find($subject->userId);

        $this->guard->assertErasable($subject);

        $reports = [];
        $unsupported = [];

        DB::transaction(function () use ($subject, &$reports, &$unsupported): void {
            foreach ($this->registry->all() as $key => $provider) {
                if (! $provider->locate($subject)) {
                    continue;
                }

                if (! $provider instanceof ErasesPersonalData) {
                    $unsupported[] = $key;

                    continue;
                }

                $reports[$key] = $provider->erase($subject);
            }
        });

        if ($reports === [] && $unsupported === []) {
            throw NoPersonalDataException::forSubject();
        }

        $result = new ErasureResult(Pseudonym::of((string) $subject->email), $reports, $unsupported);

        $this->audit->record('privacy.erased', $user, [
            'reference' => $result->reference,
            'reports' => $result->reportsToArray(),
            'unsupported' => $unsupported,
        ]);

        return $result;
    }

    /**
     * Complète le sujet (compte ET e-mail) avant tout effacement : les
     * fournisseurs n'ont alors plus à résoudre l'un par l'autre, et l'ordre
     * dans lequel ils s'exécutent est sans effet — le compte anonymisé en
     * premier ne fait pas perdre l'e-mail aux suivants.
     */
    private function resolve(Subject $subject): Subject
    {
        $user = $subject->userId === null
            ? User::query()->whereRaw('LOWER(email) = ?', [$subject->email])->first()
            : User::query()->find($subject->userId);

        if ($user === null) {
            // Un sujet « e-mail » sans compte est légitime ; un identifiant de compte inconnu ne l'est pas.
            return $subject->userId === null ? $subject : throw NoPersonalDataException::forSubject();
        }

        return new Subject((int) $user->getKey(), $user->email);
    }
}
