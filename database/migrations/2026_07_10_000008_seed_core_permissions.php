<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'baobab.admin.access',
        'baobab.audit.view',
        'baobab.access.manage',
        'baobab.users.impersonate',
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
