<?php

declare(strict_types=1);

namespace Baobab\Seo;

/**
 * Porte les données SEO composées (`ComposeSeoMeta`) le temps d'une requête,
 * entre le Render Action qui les calcule et `<x-baobab::seo-head />` posé
 * tel quel (sans attribut) dans le layout du thème — l'auteur de thème n'a
 * jamais à transmettre ces données explicitement, cohérent avec le fait que
 * le thème ne connaît jamais les mécanismes internes du Core (spec 03 §1).
 * Lié en singleton (BaobabServiceProvider) : une seule instance par requête,
 * jamais partagée entre requêtes (contrairement à un vrai singleton
 * process-wide, qui fuiterait entre requêtes sous Octane).
 */
final class SeoContext
{
    /** @var array<string, mixed>|null */
    private ?array $data = null;

    /**
     * @param  array<string, mixed>  $data
     */
    public function set(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        return $this->data;
    }
}
