<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'view_campaigns',
        'view_quality_forms',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $supervisor = Role::where('name', 'supervisor')->where('guard_name', 'web')->first();

        if (! $supervisor) {
            return;
        }

        $existingPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->get();

        foreach ($existingPermissions as $permission) {
            $supervisor->revokePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $supervisor = Role::where('name', 'supervisor')->where('guard_name', 'web')->first();

        if (! $supervisor) {
            return;
        }

        $existingPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->permissions)
            ->get();

        $supervisor->givePermissionTo($existingPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
