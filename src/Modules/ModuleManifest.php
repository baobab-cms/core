<?php

declare(strict_types=1);

namespace Baobab\Modules;

/**
 * Représentation validée d'un module.json (spec 01 §2.2). Lue sans exécuter de
 * code — seule sa présence et sa conformité au schéma sont requises.
 */
final readonly class ModuleManifest
{
    /**
     * @param  array<string, mixed>  $data  Manifest décodé et validé, tel quel.
     */
    private function __construct(private array $data) {}

    public static function fromJson(string $json, ?ManifestValidator $validator = null): self
    {
        ($validator ?? new ManifestValidator)->validate($json);

        /** @var array<string, mixed> $data */
        $data = json_decode($json, associative: true);

        return new self($data);
    }

    public function name(): string
    {
        return $this->data['name'];
    }

    public function title(): string
    {
        return $this->data['title'];
    }

    public function description(): ?string
    {
        return $this->data['description'] ?? null;
    }

    public function version(): string
    {
        return $this->data['version'];
    }

    public function type(): string
    {
        return $this->data['type'];
    }

    public function provider(): string
    {
        return $this->data['provider'];
    }

    public function requiresCms(): ?string
    {
        return $this->data['requires']['cms'] ?? null;
    }

    public function requiresPhp(): ?string
    {
        return $this->data['requires']['php'] ?? null;
    }

    /**
     * @return array<string, string> Nom du module → contrainte de version.
     */
    public function requiresModules(): array
    {
        return $this->data['requires']['modules'] ?? [];
    }

    /**
     * @return list<array{key: string, label: string, default_roles?: list<string>}>
     */
    public function permissions(): array
    {
        return $this->data['permissions'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminMenuItems(): array
    {
        return $this->data['menus']['admin'] ?? [];
    }

    /**
     * @return list<array{key: string, class: string}>
     */
    public function widgets(): array
    {
        return $this->data['widgets'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function hooksEmitted(): array
    {
        return $this->data['hooks']['emits'] ?? [];
    }

    /**
     * @return array<string, string> Nom du hook → classe listener.
     */
    public function hooksListened(): array
    {
        return $this->data['hooks']['listens'] ?? [];
    }

    /**
     * @return array<string, string> Namespace (avec antislash final) → chemin relatif au module.
     */
    public function autoloadPsr4(): array
    {
        return $this->data['autoload']['psr-4'] ?? [];
    }

    /**
     * @return array<string, array{width?: int, height?: int, fit?: string, quality?: int}>
     */
    public function mediaPresets(): array
    {
        return $this->data['media_presets'] ?? [];
    }

    /**
     * Tâches planifiées déclarées par ce module (spec 12 §2), enregistrées
     * par SchedulerRegistrar tant que le module est actif.
     *
     * @return list<array{key: string, command: string, cron: string}>
     */
    public function scheduledTasks(): array
    {
        return $this->data['schedule'] ?? [];
    }

    /**
     * Templates d'e-mails déclarés par ce module (spec 13 §3.1), résolus par
     * `Baobab\Mail\Mailer` tant que le module est actif.
     *
     * @return list<array{key: string, description?: string, variables: array<string, mixed>, defaults: string}>
     */
    public function mails(): array
    {
        return $this->data['mails'] ?? [];
    }

    /**
     * Notifications déclarées par ce module (spec 11 §6), résolues par
     * `Baobab\Notify\Notifier` tant que le module est actif.
     *
     * @return list<array{key: string, description?: string, channels: list<string>, mail_template?: string, configurable?: bool}>
     */
    public function notifications(): array
    {
        return $this->data['notifications'] ?? [];
    }

    /**
     * Section `theme` du manifeste (spec 03 §2.1), présente uniquement quand
     * `type === "theme"` (imposé par le schéma JSON). Accesseur brut, patron
     * `mails()`/`notifications()` — pas de value object, seul `parent` est
     * consommé pour l'instant (M6 point 2).
     *
     * @return array{parent?: string|null, screenshot?: string, menus?: array<string, string>, widget_zones?: array<string, string>, supports?: list<string>, settings_schema?: string}
     */
    public function theme(): array
    {
        return $this->data['theme'] ?? [];
    }

    /**
     * Design tokens surchargés par ce module/thème (spec 18 §6.1, niveau 2 de
     * la cascade). Accesseur brut, patron `theme()`/`mails()` — vocabulaire
     * fermé et validé par le schéma JSON, jamais un tableau libre.
     *
     * @return array<string, array<string, string>>
     */
    public function tokens(): array
    {
        return $this->data['tokens'] ?? [];
    }

    /**
     * Polices embarquées par ce thème (spec 18 §5.3, §6.1, `theme.json` bloc
     * `fonts`), enregistrées au registre à l'activation (`SyncThemeFonts`).
     * Distinct de `tokens()['fonts']` (les clés `body`/`heading`/`mono` du
     * vocabulaire, qui référencent un nom de famille) — ceci déclare les
     * fichiers réels de cette famille.
     *
     * @return list<array{family: string, files: array<string, string>, license?: string}>
     */
    public function fonts(): array
    {
        return $this->data['fonts'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
