<?php

declare(strict_types=1);

namespace Baobab\Demo\Actions;

use Baobab\Demo\Models\DemoContent;
use Baobab\Forms\Actions\SaveForm;
use Baobab\Forms\Models\Form;
use Baobab\Users\Models\User;

/**
 * Pose le formulaire de contact de démonstration (spec 17 §4, §9 décision 1 ;
 * spec 14 §8, M8 point 5 Pass D, suivi n° 284). Patron exact `SeedDemoContent`
 * (même marquage, même garde d'idempotence), mais un formulaire n'est pas un
 * Content Type : une seule classe de modèle (`Form`), pas une par type — la
 * garde se fait donc directement sur `Form::class`, sans l'étape de
 * résolution/réutilisation de type que `SeedDemoContent::resolveType()`
 * impose pour `Page`/`Article`.
 *
 * **Suites branchées, pas seulement déclarées** (spec 14 §8) : e-mail de
 * notification vers l'acteur résolu par l'appelant (même acteur que
 * `SeedDemoContent`, il n'y a personne d'autre à notifier sur une base de
 * démonstration) et accusé de réception au soumetteur — les deux mécanismes
 * que la Pass E du point 6 a réellement câblés. Webhook et captcha restent
 * désactivés : rien à y relier sur une installation fraîche.
 */
final class SeedDemoForm
{
    public function __construct(private readonly SaveForm $saveForm) {}

    /**
     * @return list<string> lignes affichables, destinées au `StepOutcome`
     */
    public function __invoke(User $actor): array
    {
        if (DemoContent::where('demoable_type', Form::class)->exists()) {
            return ['Le formulaire de démonstration est déjà en place.'];
        }

        $form = ($this->saveForm)(null, [
            'slug' => 'contact',
            'title' => 'Contact',
            'fields' => [
                ['key' => 'name', 'type' => 'text', 'label' => 'Nom', 'required' => true],
                ['key' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true],
                ['key' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true],
                [
                    'key' => 'consent',
                    'type' => 'consent',
                    'required' => true,
                    'options' => ['text' => 'J\'accepte que ce message soit traité pour me répondre.'],
                ],
            ],
            'settings' => [
                'suites' => [
                    'email_notification' => [
                        'enabled' => true,
                        'recipients' => [$actor->email],
                    ],
                    'acknowledgement' => [
                        'enabled' => true,
                        'subject' => 'Nous avons bien reçu votre message',
                        'body' => "Bonjour,\n\nMerci pour votre message, nous vous répondrons rapidement.\n\nRécapitulatif :\n{{ fields_summary }}",
                    ],
                    'admin_notification' => ['enabled' => true],
                    'webhook' => ['enabled' => false],
                ],
            ],
            'store_submissions' => true,
        ]);

        DemoContent::markCreated($form);

        return ['Formulaire de démonstration créé : Contact.'];
    }
}
