<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\ContentTypes\Generator\ContentTypeModuleGenerator;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;

/**
 * Pipeline complet d'un Content Type (spec 02 §2.1) : persiste le blueprint
 * (M3 point 1a), génère le module sur disque (point 1b), l'installe et
 * l'active via le cycle de vie M1 existant (migration exécutée, permissions
 * Spatie réelles), puis journalise la ligne avec son module_id.
 */
final class BuildContentType
{
    public function __construct(
        private readonly CreateContentType $create,
        private readonly ContentTypeModuleGenerator $generator,
        private readonly InstallModule $install,
        private readonly ActivateModule $activate,
    ) {}

    public function __invoke(string $blueprintJson): ContentType
    {
        $contentType = ($this->create)($blueprintJson);

        $moduleName = ($this->generator)($contentType);

        $module = ($this->install)($moduleName);
        ($this->activate)($moduleName);

        $contentType->update(['module_id' => $module->id]);

        Hook::action('baobab.content_type.created', $contentType);

        return $contentType->fresh() ?? $contentType;
    }
}
