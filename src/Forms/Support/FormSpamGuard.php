<?php

declare(strict_types=1);

namespace Baobab\Forms\Support;

use Baobab\Facades\Hook;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Niveau 1 de l'anti-spam (spec 14 §7.1, M8 point 6 Pass D1) : honeypot et
 * piège temporel, tous deux « silencieux, toujours actifs, coût utilisateur
 * zéro » — aucun réglage par formulaire, jamais déclarés dans le blueprint
 * (spec 14 §2.2, patron `consent`/`file`, hors `FieldRegistry`).
 *
 * Les deux champs sont pré-fixés `_form_` (patron `_form_slug`, déjà exclu
 * du payload validé puisqu'absent de `FormEntryRules::rules()` — `validate()`
 * ne retourne jamais une clé sans règle associée, aucune exclusion manuelle
 * à faire côté contrôleur).
 *
 * Le piège temporel signe l'horodatage de rendu (`Crypt::encryptString()`,
 * déjà dans le framework, aucune dépendance neuve) plutôt que de le poser en
 * clair : un horodatage lisible se falsifie d'un simple champ caché modifié
 * dans le DOM avant envoi, ce qui viderait le piège de tout effet. Absent ou
 * illisible, il n'y a jamais eu de rendu réel du composant — traité comme
 * suspect au même titre qu'une soumission trop rapide, jamais comme une
 * erreur qui bloquerait l'envoi (spec §7.2 : « marquage, pas rejet »).
 */
final class FormSpamGuard
{
    public const HONEYPOT_FIELD = '_form_hp';

    public const TIMESTAMP_FIELD = '_form_rt';

    private const DEFAULT_MIN_ELAPSED_SECONDS = 2;

    public static function renderToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function isTriggered(array $input): bool
    {
        return self::honeypotFilled($input) || self::submittedTooFast($input);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function honeypotFilled(array $input): bool
    {
        // Jamais de `trim()` : le champ est invisible et sans intitulé, rien
        // ne justifie qu'un seul espace y arrive légitimement — l'affaiblir
        // ouvrirait un contournement trivial pour un robot qui le sait.
        return ((string) ($input[self::HONEYPOT_FIELD] ?? '')) !== '';
    }

    /**
     * Clé absente : l'appelant ne participe pas à ce signal (le seul point
     * d'entrée HTTP réel, `SubmitFormController`, la pose toujours puisque
     * `FormEmbed` la génère à chaque rendu — un appel direct de `SubmitForm`,
     * comme le fait ce test unitaire, n'en a jamais). Clé présente mais
     * vide/illisible : un rendu a bien eu lieu, la valeur a disparu ou a été
     * altérée en chemin — traité comme suspect, jamais comme une erreur.
     *
     * @param  array<string, mixed>  $input
     */
    private static function submittedTooFast(array $input): bool
    {
        if (! array_key_exists(self::TIMESTAMP_FIELD, $input)) {
            return false;
        }

        $token = $input[self::TIMESTAMP_FIELD];

        if (! is_string($token) || $token === '') {
            return true;
        }

        try {
            $renderedAt = (int) Crypt::decryptString($token);
        } catch (DecryptException) {
            return true;
        }

        $minElapsedSeconds = (int) Hook::filter('baobab.form.anti_spam.min_elapsed_seconds', self::DEFAULT_MIN_ELAPSED_SECONDS);

        return (now()->getTimestamp() - $renderedAt) < $minElapsedSeconds;
    }
}
