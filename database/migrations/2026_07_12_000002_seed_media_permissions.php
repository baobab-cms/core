<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'baobab.media.view',
        'baobab.media.upload',
        'baobab.media.update',
        'baobab.media.update_any',
        'baobab.media.delete',
        'baobab.media.delete_any',
        'baobab.media.upload_svg',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'baobab');
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'baobab')
            ->delete();
    }
};
