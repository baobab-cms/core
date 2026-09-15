<?php

declare(strict_types=1);

return [
    'not_found_title' => 'Page introuvable',

    // Pages de repli du Core (spec 19 §6). Ces textes s'adressent à un
    // **visiteur**, jamais à un développeur : aucun diagnostic technique ici,
    // il vit dans la bande admin (§6.5).
    'skip_to_content' => 'Aller au contenu',
    'not_found_body' => 'Cette adresse ne correspond à aucune page de ce site.',
    'home_link' => 'Retour à l\'accueil',
    'index_title' => 'Bienvenue',
    'index_body' => 'Ce site est en cours de préparation.',
    'archive_empty' => 'Aucun contenu publié pour le moment.',
    'search_title' => 'Rechercher',
    'search_results_title' => 'Résultats pour « :query »',
    'search_submit' => 'Rechercher',
    'search_label' => 'Votre recherche',
    'search_invite' => 'Saisissez un mot pour chercher dans le contenu du site.',
    'search_empty' => 'Aucun résultat pour « :query ». Essayez avec un autre mot, ou moins de mots.',

    // Page 500 : servie quand tout le reste a échoué, donc sans traduction
    // dynamique fiable ni compilation d'artefact. Les clés existent pour la
    // cohérence, la vue porte aussi un texte de repli en dur (§6.4).
    'error_title' => 'Une erreur est survenue',
    'error_body' => 'Le site n\'a pas pu afficher cette page. L\'incident a été enregistré.',

    // Page de maintenance (spec 12 §6.1, spec 19 §6.4) — surchargeable par le
    // thème actif, contrairement à la page 500.
    'maintenance_title' => 'Site en maintenance',
    'maintenance_body' => 'Ce site est temporairement indisponible pour maintenance. Merci de revenir dans quelques instants.',
    'maintenance_retry' => 'Nouvelle tentative possible dans environ :seconds secondes.',

    // Bande admin — diagnostic réservé à qui peut agir (§6.5).
    'no_active_theme_notice' => 'Aucun thème actif : le rendu de repli du Core est servi.',
    'no_active_theme_action' => 'Choisir un thème',

    // Formulaires publics (spec 14 §4, M8 point 6 Pass C1) — `<x-baobab::form-embed>`.
    'form_confirmation_default' => 'Merci, votre message a bien été envoyé.',
    'form_consent_privacy_link' => 'Voir la politique de confidentialité',
    'form_submit_action' => 'Envoyer',
    'form_captcha_failed' => 'Merci de confirmer que vous n\'êtes pas un robot.',
];
