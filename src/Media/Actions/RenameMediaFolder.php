<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Models\MediaFolder;

final class RenameMediaFolder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(MediaFolder $folder, string $name): MediaFolder
    {
        $before = $folder->name;

        $folder->update(['name' => $name]);

        $this->audit->record('media_folder.renamed', $folder, ['before' => $before, 'after' => $name]);

        return $folder;
    }
}
