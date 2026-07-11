<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('baobab.users.impersonate', 'baobab');
    }

    public function down(): void
    {
        Permission::where('name', 'baobab.users.impersonate')
            ->where('guard_name', 'baobab')
            ->delete();
    }
};
