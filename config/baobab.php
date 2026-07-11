<?php

declare(strict_types=1);
use Baobab\Users\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Version du cœur
    |--------------------------------------------------------------------------
    |
    | Comparée à requires.cms des manifests (DependencyResolver). Avant la
    | première version publiée, une valeur de développement arbitraire —
    | à faire suivre le versionnage réel du package une fois publié.
    |
    */
    'version' => '0.1.0',

    /*
    |--------------------------------------------------------------------------
    | Emplacements de découverte des modules
    |--------------------------------------------------------------------------
    |
    | ModuleDiscovery scanne ces glob patterns à la recherche de fichiers
    | module.json. Les entrées "local" sont scannées avant "composer" — en cas
    | de nom de module en double, le premier trouvé gagne (priorité au local,
    | spec 01 §7.1).
    |
    */
    'modules' => [
        'paths' => [
            'local' => [
                base_path('modules/*'),
                base_path('themes/*'),
            ],
            'composer' => [
                base_path('vendor/*/*'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentification
    |--------------------------------------------------------------------------
    |
    | user_model : modèle Eloquent utilisé par le guard "baobab". Par défaut
    | Baobab\Users\Models\User (table `users`). Remplacer par une classe
    | étendant ce modèle pour ajouter des colonnes applicatives.
    |
    */
    'auth' => [
        'user_model' => User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Interface d'administration
    |--------------------------------------------------------------------------
    |
    | path : préfixe des routes admin, configurable (white-label, réduction de
    | surface d'attaque — spec 04 §2). Défaut : /admin.
    |
    */
    'admin' => [
        'path' => env('BAOBAB_ADMIN_PATH', 'admin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Impersonation
    |--------------------------------------------------------------------------
    |
    | duration_minutes : durée maximale d'une session d'impersonation avant
    | retour automatique à l'identité réelle (spec 04 §9.1). Défaut : 60.
    |
    */
    'impersonation' => [
        'duration_minutes' => (int) env('BAOBAB_IMPERSONATION_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Journalisation technique
    |--------------------------------------------------------------------------
    |
    | retention_days : nombre de jours conservés par le channel de log dédié
    | `baobab` (driver "daily", spec 12 §9). Défaut : 14.
    |
    */
    'logging' => [
        'retention_days' => (int) env('BAOBAB_LOG_RETENTION_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Types
    |--------------------------------------------------------------------------
    |
    | modules_path : racine où le moteur de génération écrit les modules de
    | Content Types (spec 02 §1.2). Doit être couvert par un des patterns de
    | modules.paths.local ci-dessus pour rester découvrable. Séparé pour rester
    | surchargeable indépendamment en test (répertoire temporaire).
    |
    */
    'content_types' => [
        'modules_path' => env('BAOBAB_CONTENT_TYPES_PATH', base_path('modules')),
    ],

];
