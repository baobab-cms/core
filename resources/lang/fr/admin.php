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
        'users' => 'Utilisateurs',
    ],

    'audit' => [
        'title' => 'Journal d\'audit',
        'filter_action' => 'Action',
        'filter_actor' => 'Acteur (ID)',
        'filter_submit' => 'Filtrer',
        'column_date' => 'Date',
        'column_actor' => 'Acteur',
        'column_impersonator' => 'Pour le compte de',
        'column_action' => 'Action',
        'column_data' => 'Détails',
        'column_ip' => 'IP',
        'system_actor' => 'Système',
    ],

    'users' => [
        'title' => 'Utilisateurs',
        'column_name' => 'Nom',
        'column_email' => 'E-mail',
        'column_roles' => 'Rôles',
        'column_level' => 'Niveau',
        'impersonate_action' => 'Se connecter en tant que',
    ],

    'impersonation' => [
        'banner' => 'Vous naviguez en tant que :name.',
        'stop' => 'Revenir à mon compte',
    ],

    'account' => [
        'security' => [
            'title' => 'Sécurité du compte',
            'not_enabled_intro' => 'La vérification en deux étapes n\'est pas activée sur votre compte.',
            'enable_action' => 'Activer la 2FA',
            'pending_intro' => 'Scannez ce QR code avec votre application d\'authentification, puis saisissez le code généré pour confirmer l\'activation.',
            'secret_fallback_label' => 'Vous ne pouvez pas scanner le code ? Saisissez cette clé manuellement :',
            'confirm_code_label' => 'Code de vérification',
            'confirm_action' => 'Confirmer',
            'confirm_invalid' => 'Le code saisi est invalide.',
            'enabled_since' => 'Activée depuis le :date.',
            'recovery_codes_title' => 'Codes de récupération',
            'recovery_codes_warning' => 'Conservez ces codes dans un endroit sûr : ils ne seront plus affichés après avoir quitté cette page.',
            'regenerate_recovery_codes_action' => 'Régénérer les codes de récupération',
            'regenerate_recovery_codes_confirm_title' => 'Régénérer les codes de récupération ?',
            'regenerate_recovery_codes_confirm_description' => 'Les codes actuels seront invalidés et remplacés par 8 nouveaux codes.',
            'disable_action' => 'Désactiver la 2FA',
            'disable_confirm_title' => 'Désactiver la vérification en deux étapes ?',
            'disable_confirm_description' => 'Ressaisissez votre mot de passe pour confirmer.',
            'current_password_label' => 'Mot de passe actuel',
            'enabled' => 'Vérification en deux étapes activée.',
            'confirmed' => 'Vérification en deux étapes confirmée.',
            'recovery_codes_regenerated' => 'Codes de récupération régénérés.',
            'disabled' => 'Vérification en deux étapes désactivée.',
        ],
    ],

    'content' => [
        'search_placeholder' => 'Rechercher…',
        'search_submit' => 'Rechercher',
        'filter_status' => 'Statut',
        'filter_all_statuses' => 'Tous les statuts',
        'create_action' => 'Ajouter',
        'edit_action' => 'Modifier',
        'delete_action' => 'Supprimer',
        'delete_confirm_title' => 'Supprimer cet élément ?',
        'delete_confirm_description' => 'Cette action déplace l\'élément dans la corbeille.',
        'bulk_delete_action' => 'Supprimer la sélection',
        'column_status' => 'Statut',
        'save_action' => 'Enregistrer',
        'cancel_action' => 'Annuler',
        'created' => 'Élément créé.',
        'updated' => 'Élément modifié.',
        'deleted' => 'Élément supprimé.',
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
