<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /** @var array<string, int> */
    private const ROLES = [
        'super-admin' => 100,
        'admin'       => 80,
        'editor'      => 60,
        'moderator'   => 40,
        'author'      => 40,
        'visitor'     => 10,
    ];

    public function up(): void
    {
        foreach (self::ROLES as $name => $level) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'baobab'],
                ['level' => $level],
            );
        }
    }

    public function down(): void
    {
        Role::whereIn('name', array_keys(self::ROLES))
            ->where('guard_name', 'baobab')
            ->delete();
    }
};
