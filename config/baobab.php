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

    /*
    |--------------------------------------------------------------------------
    | Cycle éditorial (spec 09, M5 point 2)
    |--------------------------------------------------------------------------
    |
    | revisions_limit : révisions conservées par entrée au-delà desquelles les
    | plus anciennes sont purgées (défaut 50, spec 02 §9 décision 4) —
    | surchargeable par type via `blueprint.revisions.limit`.
    | autosave_seconds : cadence de l'autosave côté formulaire (spec 09 §3).
    | lock_heartbeat_seconds : fréquence du ping qui maintient un verrou
    | d'édition ouvert (spec 09 §7).
    | lock_expiry_seconds : au-delà de ce délai sans heartbeat, le verrou est
    | considéré abandonné et libéré silencieusement (onglet fermé brutalement).
    | trash_retention_days : un contenu mis à la corbeille est purgé
    | définitivement après ce délai par `content:purge-trash` (spec 09 §8),
    | même convention que `media.trash_retention_days`.
    |
    */
    'content' => [
        'revisions_limit' => (int) env('BAOBAB_CONTENT_REVISIONS_LIMIT', 50),
        'autosave_seconds' => (int) env('BAOBAB_CONTENT_AUTOSAVE_SECONDS', 60),
        'lock_heartbeat_seconds' => (int) env('BAOBAB_CONTENT_LOCK_HEARTBEAT_SECONDS', 30),
        'lock_expiry_seconds' => (int) env('BAOBAB_CONTENT_LOCK_EXPIRY_SECONDS', 120),
        'trash_retention_days' => (int) env('BAOBAB_CONTENT_TRASH_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bibliothèque de médias
    |--------------------------------------------------------------------------
    |
    | disk : disque Laravel (config/filesystems.php) où les fichiers sont
    | stockés — jamais de chemin absolu manipulé directement (spec 06 §1.1).
    | max_upload_size : limite globale en octets (défaut 64 Mo, spec 06 §7.3).
    | Une limite par rôle n'est pas construite en dur ici (pas d'écran
    | « fiche rôle → réglages » avant M9) : le filtre `baobab.media.uploading.
    | max_size` (UploadMedia) est le point d'extension prévu par la spec.
    | allowed_mime_types : liste blanche, vérifiée par contenu (jamais la
    | seule extension).
    | chunk_size / chunk_threshold : un fichier plus gros que chunk_threshold
    | est découpé côté client en morceaux de chunk_size (M4 point 1b) —
    | nécessaire dès que max_upload_size dépasse upload_max_filesize/
    | post_max_size de PHP, pas seulement pour le confort.
    | duplicate_behavior : comportement par défaut à l'upload d'un fichier de
    | checksum déjà connu — "ask" (demander), "reuse" (réutiliser
    | silencieusement) ou "allow" (autoriser le doublon), spec 06 §2.
    | image_driver : pilote Intervention Image ("gd" ou "imagick", spec 06
    | §1.1) — "gd" par défaut, seule extension image disponible ici.
    | avif_enabled : désactivé par défaut (coût CPU documenté, registre
    | décision #4) — les variantes restent jpg/png + webp tant que c'est faux.
    | job_memory_limit : memory_limit PHP appliqué le temps du job de
    | génération de variantes (GenerateMediaConversions) — un bitmap décodé
    | par GD pour une photo réelle dépasse vite le memory_limit web par
    | défaut (souvent 128 Mo), taillé pour une requête HTTP, pas du
    | traitement d'image. Relevé uniquement pour ce job, pas globalement.
    | trash_retention_days : un média mis à la corbeille est purgé (fichiers
    | + ligne) après ce délai par `media:purge-trash` (M4 point 3, spec 06
    | §1.2), configurable, défaut 30.
    |
    */
    'media' => [
        'disk' => env('BAOBAB_MEDIA_DISK', 'public'),
        'max_upload_size' => (int) env('BAOBAB_MEDIA_MAX_UPLOAD_SIZE', 67_108_864),
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'application/pdf',
            'video/mp4',
            'video/webm',
            'audio/mpeg',
            'audio/wav',
        ],
        'chunk_size' => (int) env('BAOBAB_MEDIA_CHUNK_SIZE', 5_242_880),
        'chunk_threshold' => (int) env('BAOBAB_MEDIA_CHUNK_THRESHOLD', 5_242_880),
        'duplicate_behavior' => env('BAOBAB_MEDIA_DUPLICATE_BEHAVIOR', 'ask'),
        'image_driver' => env('BAOBAB_MEDIA_IMAGE_DRIVER', 'gd'),
        'avif_enabled' => (bool) env('BAOBAB_MEDIA_AVIF_ENABLED', false),
        'job_memory_limit' => env('BAOBAB_MEDIA_JOB_MEMORY_LIMIT', '512M'),
        'trash_retention_days' => (int) env('BAOBAB_MEDIA_TRASH_RETENTION_DAYS', 30),
    ],

];
