<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Models\MediaFolder;

final class CreateMediaFolder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(string $name, ?int $parentId = null): MediaFolder
    {
        $folder = MediaFolder::create([
            'name' => $name,
            'parent_id' => $parentId,
        ]);

        $this->audit->record('media_folder.created', $folder, ['name' => $name]);

        return $folder;
    }
}
