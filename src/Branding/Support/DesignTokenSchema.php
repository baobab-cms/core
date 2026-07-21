<?php

declare(strict_types=1);

namespace Baobab\Branding\Support;

/**
 * Vocabulaire fermé des design tokens (spec 18 §2.1-2.4) : groupes et clés
 * structurels, jamais réglables par environnement — une classe à constantes,
 * pas un fichier de config. `dark` n'apparaît jamais ici (§2.3, réservé
 * post-v1) : rejeté explicitement à la validation du manifest et du
 * formulaire admin, jamais silencieusement ignoré.
 */
final class DesignTokenSchema
{
    /**
     * @var array<string, list<string>>
     */
    public const array GROUPS = [
        'colors' => ['primary', 'secondary', 'accent', 'success', 'warning', 'danger', 'info', 'surface', 'background', 'text', 'muted', 'border'],
        'fonts' => ['body', 'heading', 'mono'],
        'text' => ['xs', 'sm', 'base', 'lg', 'xl', '2xl', '3xl'],
        'leading' => ['tight', 'normal', 'relaxed'],
        'weight' => ['normal', 'medium', 'bold'],
        'radius' => ['sm', 'md', 'lg', 'full'],
        'spacing' => ['xs', 'sm', 'md', 'lg', 'xl'],
        'shadow' => ['sm', 'md', 'lg'],
    ];

    /**
     * Défauts Core (spec 18 §13.3/§13.5), niveau 1 de la cascade.
     *
     * @var array<string, array<string, string>>
     */
    public const array CORE_DEFAULTS = [
        'colors' => [
            'primary' => '#C2571B',
            'secondary' => '#3D6B45',
            'accent' => '#B5821C',
            'success' => '#3D6B45',
            'warning' => '#C07A10',
            'danger' => '#B23A28',
            'info' => '#3D6A8A',
            'surface' => '#FFFFFF',
            'background' => '#FAF8F5',
            'text' => '#2E2B24',
            'muted' => '#615C52',
            'border' => '#DDD8CE',
        ],
        'fonts' => [
            'body' => "'Figtree Variable', ui-sans-serif, system-ui, sans-serif",
            'heading' => "'Bricolage Grotesque Variable', ui-sans-serif, system-ui, sans-serif",
            'mono' => "'JetBrains Mono', ui-monospace, monospace",
        ],
        'text' => [
            'xs' => '0.64rem',
            'sm' => '0.8rem',
            'base' => '1rem',
            'lg' => '1.25rem',
            'xl' => '1.5625rem',
            '2xl' => '1.9531rem',
            '3xl' => '2.4414rem',
        ],
        'leading' => [
            'tight' => '1.25',
            'normal' => '1.6',
            'relaxed' => '1.75',
        ],
        'weight' => [
            'normal' => '400',
            'medium' => '500',
            'bold' => '700',
        ],
        'radius' => [
            'sm' => '4px',
            'md' => '8px',
            'lg' => '16px',
            'full' => '9999px',
        ],
        'spacing' => [
            'xs' => '4px',
            'sm' => '8px',
            'md' => '16px',
            'lg' => '24px',
            'xl' => '40px',
        ],
        'shadow' => [
            'sm' => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
            'md' => '0 4px 6px -1px rgb(0 0 0 / 0.1)',
            'lg' => '0 10px 15px -3px rgb(0 0 0 / 0.1)',
        ],
    ];

    /**
     * Nom du groupe dans la variable CSS émise (spec 18 §2.2) : au singulier
     * pour `colors`/`fonts` (`--bb-color-primary`, `--bb-font-body`) — les
     * autres groupes sont déjà au singulier et restent inchangés. La clé du
     * vocabulaire (`GROUPS`/`CORE_DEFAULTS`, JSON du manifeste) reste au
     * pluriel, seul le nom émis en CSS diffère.
     *
     * @var array<string, string>
     */
    private const array CSS_GROUP_NAMES = [
        'colors' => 'color',
        'fonts' => 'font',
    ];

    public static function cssVar(string $group, string $key): string
    {
        $cssGroup = self::CSS_GROUP_NAMES[$group] ?? $group;

        return "--bb-{$cssGroup}-{$key}";
    }
}
