<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'view_transcripts',
        'create_transcripts',
        'edit_transcripts',
        'delete_transcripts',
        'view_insights',
        'generate_insights',
        'view_work_queue',
        'view_sampling',
        'manage_sampling',
        'view_staffing',
        'manage_staffing',
    ];

    private array $previousSupervisorPermissions = [
        'view_transcripts',
        'view_insights',
        'view_work_queue',
        'view_sampling',
        'view_staffing',
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
            ->whereIn('name', $this->previousSupervisorPermissions)
            ->get();

        $supervisor->givePermissionTo($existingPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
