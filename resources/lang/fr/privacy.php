<?php

declare(strict_types=1);

/*
 * Déclarations des fournisseurs Core de données personnelles (spec 16 §2.1).
 * Le registre des traitements en est généré : ces textes sont la matière
 * première du registre de l'exploitant, pas un avis juridique.
 */
return [
    'services' => [
        'mail' => 'Envoi d\'e-mails (:driver) — adresses des destinataires',
    ],

    'users' => [
        'title' => 'Comptes utilisateurs',
        'nature' => 'Nom, adresse e-mail, mot de passe (haché), secret et codes de récupération 2FA (chiffrés), rôles et sessions actives.',
        'purpose' => 'Authentification et gestion des accès à l\'administration et au site.',
        'legal_basis' => 'Exécution du service demandé par l\'utilisateur.',
        'retention' => 'Tant que le compte existe.',
    ],

    'content_authorship' => [
        'title' => 'Contenus signés',
        'nature' => 'Rattachement de chaque contenu éditorial à son auteur (identifiant du compte).',
        'purpose' => 'Attribution éditoriale, droits « les siens / tous » et traçabilité.',
        'legal_basis' => 'Intérêt légitime de l\'exploitant (gestion éditoriale).',
        'retention' => 'Tant que le contenu existe ; le contenu appartient au site, pas à la personne.',
    ],

    'media' => [
        'title' => 'Fichiers déposés',
        'nature' => 'Fichiers téléversés (peuvent contenir des données personnelles) et rattachement à l\'auteur du dépôt.',
        'purpose' => 'Bibliothèque de médias du site.',
        'legal_basis' => 'Intérêt légitime de l\'exploitant (gestion éditoriale).',
        'retention' => 'Tant que le média existe ; corbeille purgée après :days jours.',
    ],

    'mail_log' => [
        'title' => 'Journal des e-mails',
        'nature' => 'Adresse du destinataire, objet, statut d\'envoi ; corps du message uniquement si conservé.',
        'purpose' => 'Diagnostic de délivrabilité et preuve d\'envoi.',
        'legal_basis' => 'Intérêt légitime de l\'exploitant (fiabilité du service).',
        'retention' => 'Entrées purgées après :days jours ; corps effacés après :body_days jours.',
    ],

    'audit_log' => [
        'title' => 'Journal d\'audit',
        'nature' => 'Acteur, usurpateur éventuel, action, objet concerné, adresse IP et navigateur.',
        'purpose' => 'Sécurité, traçabilité des actions et obligation de preuve.',
        'legal_basis' => 'Intérêt légitime (sécurité) et obligation de preuve.',
        'retention' => 'Purgé après :days jours ; pseudonymisé plutôt que supprimé en cas d\'effacement.',
    ],

    'form_submissions' => [
        'title' => 'Soumissions de formulaires',
        'nature' => 'Réponses saisies par les visiteurs (dont adresse e-mail), fichiers joints, preuve de consentement, adresse IP si l\'anti-spam l\'exige.',
        'purpose' => 'Traitement des demandes reçues via les formulaires du site.',
        'legal_basis' => 'Consentement (champ dédié) ou mesures précontractuelles, selon le formulaire.',
        'retention' => 'Durée définie par formulaire (365 jours par défaut).',
    ],

    'export' => [
        'title' => 'Export de vos données personnelles',
        'generated' => 'Généré le :date.',
        'files' => 'Fichiers joints :',
        'unknown_subject' => 'Sujet introuvable : indiquez un identifiant de compte existant ou une adresse e-mail.',
        'written' => 'Archive écrite : :path',
        'providers' => 'Traitements inclus : :providers',
        'password' => 'Mot de passe de l\'archive : :password',
        'password_once' => 'Ce mot de passe n\'est affiché qu\'une fois et n\'est conservé nulle part ; transmettez-le séparément de l\'archive.',
        'none_found' => 'Aucune donnée personnelle trouvée pour ce sujet.',
        'unsupported_title' => 'Données non couvertes par cet export',
        'unsupported_hint' => 'Ces traitements détiennent des données vous concernant mais ne savent pas les exporter automatiquement ; contactez l\'exploitant du site.',
    ],
];
