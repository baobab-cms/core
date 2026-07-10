<?php

declare(strict_types=1);

return [

    'layout' => [
        'default_title' => 'Administration',
        'toggle_sidebar' => 'Afficher/masquer le menu',
        'logout' => 'Se déconnecter',
    ],

    'dashboard' => [
        'title' => 'Tableau de bord',
        'placeholder' => 'Le tableau de bord arrive dans une prochaine étape.',
    ],

    'auth' => [
        'login_title' => 'Connexion',
        'email' => 'E-mail',
        'password' => 'Mot de passe',
        'submit' => 'Continuer',
        'failed' => 'Ces identifiants ne correspondent à aucun compte.',
        'two_factor_title' => 'Vérification en deux étapes',
        'two_factor_prompt' => 'Saisissez le code généré par votre application d\'authentification.',
        'two_factor_code' => 'Code',
        'two_factor_invalid' => 'Le code saisi est invalide.',
    ],

    'components' => [
        'no_results' => 'Aucun résultat.',
        'select_all' => 'Tout sélectionner',
        'confirm_placeholder' => 'Retapez « :text » pour confirmer.',
        'close' => 'Fermer',
    ],

    'sidebar' => [
        'audit' => 'Journal d\'audit',
        'access' => 'Rôles & permissions',
    ],

    'audit' => [
        'title' => 'Journal d\'audit',
        'filter_action' => 'Action',
        'filter_actor' => 'Acteur (ID)',
        'filter_submit' => 'Filtrer',
        'column_date' => 'Date',
        'column_actor' => 'Acteur',
        'column_action' => 'Action',
        'column_data' => 'Détails',
        'column_ip' => 'IP',
        'system_actor' => 'Système',
    ],

    'access' => [
        'title' => 'Rôles & permissions',
        'column_permission' => 'Permission',
        'core_group' => 'Core',
        'inactive_module' => 'Module inactif',
        'always_granted' => 'Toujours accordé (Super Admin)',
        'create_role_title' => 'Créer un rôle',
        'create_role_name' => 'Nom',
        'create_role_level' => 'Niveau',
        'create_role_submit' => 'Créer',
        'role_created' => 'Rôle « :name » créé.',
    ],

];
