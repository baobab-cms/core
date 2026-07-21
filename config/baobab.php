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

    /*
    |--------------------------------------------------------------------------
    | E-mails (spec 13 §2-3, M5 point 6)
    |--------------------------------------------------------------------------
    |
    | templates : déclaration des templates du Core lui-même (`core.*`) — pas
    | une ligne de la table `modules`, donc pas de manifest à lire ; même forme
    | que la section "mails" d'un module.json. Les templates de modules sont
    | lus depuis leur manifest par TemplateRegistry.
    |
    */
    'mail' => [
        'templates' => [
            [
                'key' => 'core.test',
                'description' => 'E-mail de test (SendTestMail, `baobab:mail:test`).',
                'variables' => [
                    'sent_at' => 'Date/heure d\'envoi du test',
                ],
                'defaults' => __DIR__.'/../resources/mails/core/test.json',
            ],
            [
                'key' => 'core.content.review_requested',
                'description' => 'Envoyé aux détenteurs de publish_any quand un contenu est soumis à validation (spec 09 §5, M5 point 4).',
                'variables' => [
                    'content_type' => 'Libellé du Content Type',
                    'content_title' => 'Titre/identifiant du contenu',
                    'url' => 'Lien vers le formulaire d\'édition',
                ],
                'defaults' => __DIR__.'/../resources/mails/core/content-review-requested.json',
            ],
            [
                'key' => 'core.content.review_approved',
                'description' => 'Envoyé à l\'auteur quand son contenu est approuvé (spec 09 §5, M5 point 4).',
                'variables' => [
                    'content_type' => 'Libellé du Content Type',
                    'content_title' => 'Titre/identifiant du contenu',
                    'url' => 'Lien vers le formulaire d\'édition',
                ],
                'defaults' => __DIR__.'/../resources/mails/core/content-review-approved.json',
            ],
            [
                'key' => 'core.content.review_rejected',
                'description' => 'Envoyé à l\'auteur quand son contenu est rejeté (spec 09 §5, M5 point 4).',
                'variables' => [
                    'content_type' => 'Libellé du Content Type',
                    'content_title' => 'Titre/identifiant du contenu',
                    'comment' => [
                        'label' => 'Commentaire de rejet',
                        'required' => true,
                    ],
                    'url' => 'Lien vers le formulaire d\'édition',
                ],
                'defaults' => __DIR__.'/../resources/mails/core/content-review-rejected.json',
            ],
            [
                'key' => 'core.security.impersonation_started',
                'description' => 'Envoyé à un utilisateur quand un administrateur démarre une impersonation sur son compte (spec 11 §6, notification de sécurité non désactivable).',
                'variables' => [
                    'actor_name' => 'Nom de la personne qui a démarré l\'impersonation',
                    'occurred_at' => 'Date/heure de l\'impersonation',
                ],
                'defaults' => __DIR__.'/../resources/mails/core/security-impersonation-started.json',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications (spec 11 §5-8, M5 point 4)
    |--------------------------------------------------------------------------
    |
    | declarations : notifications du Core lui-même (`core.*`), même forme que
    | la section "notifications" d'un module.json — lue par NotificationRegistry
    | au même titre que les modules actifs.
    | retention_days : rétention des notifications `database` avant purge par
    | le scheduler (spec 11 §8, défaut 90).
    |
    */
    'notifications' => [
        'retention_days' => (int) env('BAOBAB_NOTIFICATIONS_RETENTION_DAYS', 90),
        'declarations' => [
            [
                'key' => 'core.content.review_requested',
                'description' => 'Un contenu attend une validation.',
                'channels' => ['database', 'mail'],
                'mail_template' => 'core.content.review_requested',
                'configurable' => true,
            ],
            [
                'key' => 'core.content.review_approved',
                'description' => 'Un contenu a été approuvé.',
                'channels' => ['database', 'mail'],
                'mail_template' => 'core.content.review_approved',
                'configurable' => true,
            ],
            [
                'key' => 'core.content.review_rejected',
                'description' => 'Un contenu a été rejeté.',
                'channels' => ['database', 'mail'],
                'mail_template' => 'core.content.review_rejected',
                'configurable' => true,
            ],
            [
                'key' => 'core.security.impersonation_started',
                'description' => 'Quelqu\'un s\'est connecté en tant que vous.',
                'channels' => ['database', 'mail'],
                'mail_template' => 'core.security.impersonation_started',
                'configurable' => false,
            ],
            [
                'key' => 'core.webhook.subscription_disabled',
                'description' => 'Un abonnement webhook a été désactivé après trop d\'échecs consécutifs.',
                'channels' => ['database'],
                'configurable' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rendu public (spec 03 §3-4, M6 point 1)
    |--------------------------------------------------------------------------
    |
    | reserved_prefixes : segments d'URL qu'un Content Type adressable ne peut
    | pas revendiquer comme url_prefix (collision avec l'admin, l'API — spec
    | 08, à venir M7 — la préview de thème, spec 03 §7, M6 point 2 — ou le
    | sitemap/robots.txt, spec 07 §5/§8, M5 point 5 Pass C).
    | per_page : taille de page des archives de types adressables.
    |
    */
    'rendering' => [
        'reserved_prefixes' => [
            env('BAOBAB_ADMIN_PATH', 'admin'),
            'api',
            'theme-preview',
            'sitemap.xml',
            'sitemaps',
            'robots.txt',
            'search',
        ],
        'per_page' => (int) env('BAOBAB_RENDERING_PER_PAGE', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Menus (spec 10 §2, M6 point 4a)
    |--------------------------------------------------------------------------
    |
    | max_depth : profondeur maximale d'imbrication (spec §4 décision 2,
    | défaut 4 niveaux). cache_ttl : durée du cache de l'arbre résolu par
    | menu (spec §2.4), en secondes.
    |
    */
    'menus' => [
        'max_depth' => (int) env('BAOBAB_MENUS_MAX_DEPTH', 4),
        'cache_ttl' => (int) env('BAOBAB_MENUS_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirections & journal 404 (spec 07 §3-4, M5 point 5 Pass B)
    |--------------------------------------------------------------------------
    |
    | trailing_slash : forme canonique imposée par NormalizePublicUrl —
    | "strip" (défaut, /voitures/208/ → /voitures/208) ou "append" (l'inverse,
    | spec 07 §3 dernière puce : « ou l'inverse, réglage »).
    | not_found_retention_days : purge automatique du journal des 404
    | (seo:purge-404-log) au-delà de ce délai, patron
    | notifications.retention_days.
    |
    */
    'redirects' => [
        'trailing_slash' => env('BAOBAB_REDIRECTS_TRAILING_SLASH', 'strip'),
        'not_found_retention_days' => (int) env('BAOBAB_NOT_FOUND_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks sortants (spec 08 §5, M7 point 4 Pass A)
    |--------------------------------------------------------------------------
    |
    | hooks : catalogue Core des événements abonnables
    | (Baobab\Webhooks\Support\WebhookEventCatalog), patron
    | notifications.declarations — HookRegistry ne peut pas fournir cette
    | liste elle-même (elle ne connaît que les hooks ayant déjà un listener).
    | Volontairement non exhaustif pour cette passe : cycle de vie contenu +
    | module, extensible sans redesign. Les modules ajoutent les leurs via
    | manifest['hooks']['emits'].
    | max_consecutive_failures : désactivation automatique d'un abonnement
    | après ce nombre d'échecs consécutifs (Baobab\Webhooks\Jobs\DeliverWebhook).
    |
    */
    'webhooks' => [
        'hooks' => [
            'baobab.content.saved',
            'baobab.content.transitioned',
            'baobab.module.installed',
            'baobab.module.activated',
            'baobab.module.deactivated',
            'baobab.module.uninstalled',
        ],
        'max_consecutive_failures' => (int) env('BAOBAB_WEBHOOKS_MAX_CONSECUTIVE_FAILURES', 10),
    ],

];
