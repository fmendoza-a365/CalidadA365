<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Shared helper for creating users with roles and their default permissions.
 * Mirrors the PermissionSeeder role-permission mapping so tests don't need
 * to run the full seeder.
 */
trait CreatesUsersWithRoles
{
    /**
     * Default permissions per role (excluding admin, which gets all).
     */
    private static array $rolePermissions = [
        'agent' => ['view_own_evaluations', 'view_agent_dashboard', 'respond_evaluations'],
        'supervisor' => [
            'view_team_evaluations', 'view_supervisor_dashboard', 'export_evaluations',
            'review_disputes', 'manage_evaluation_lifecycle', 'respond_evaluations',
        ],
        'qa_monitor' => [
            'create_evaluations', 'view_assigned_evaluations', 'view_campaigns',
            'view_quality_forms', 'view_transcripts', 'create_transcripts',
            'view_monitor_dashboard', 'export_evaluations', 'export_calibration',
            'export_evaluation_audit', 'view_work_queue', 'view_sampling',
            'manage_sampling', 'view_staffing', 'manage_staffing',
            'manage_evaluation_lifecycle', 'review_disputes',
        ],
        'qa_coordinator' => [
            'view_team_evaluations', 'create_evaluations', 'view_campaigns',
            'view_quality_forms', 'edit_quality_forms', 'view_transcripts',
            'create_transcripts', 'edit_transcripts', 'delete_transcripts',
            'assign_agents', 'view_coordinator_dashboard', 'view_insights',
            'manage_evaluation_lifecycle', 'export_evaluations', 'export_calibration',
            'export_evaluation_audit', 'view_work_queue', 'view_sampling',
            'manage_sampling', 'view_staffing', 'manage_staffing',
            'review_disputes', 'resolve_disputes',
        ],
        'qa_manager' => [
            'view_insights', 'generate_insights', 'view_all_evaluations',
            'create_evaluations', 'edit_evaluations', 'manage_evaluation_lifecycle',
            'export_evaluations', 'export_calibration', 'export_evaluation_audit',
            'view_work_queue', 'view_sampling', 'manage_sampling', 'view_staffing',
            'manage_staffing', 'view_campaigns', 'create_campaigns', 'edit_campaigns', 'delete_campaigns',
            'assign_agents', 'view_quality_forms', 'create_quality_forms',
            'edit_quality_forms', 'publish_quality_forms', 'view_transcripts',
            'create_transcripts', 'edit_transcripts', 'view_monitor_dashboard',
            'view_coordinator_dashboard', 'view_ai_performance',
            'review_disputes', 'resolve_disputes',
        ],
        'manager' => [
            'view_all_evaluations', 'view_campaigns', 'view_quality_forms',
            'create_quality_forms', 'edit_quality_forms', 'publish_quality_forms',
            'view_transcripts', 'view_manager_dashboard', 'view_insights',
            'view_users', 'export_evaluations', 'export_calibration',
            'export_evaluation_audit', 'view_work_queue', 'view_sampling',
            'view_staffing', 'manage_evaluation_lifecycle', 'review_disputes',
            'resolve_disputes',
        ],
    ];

    private function userWithRole(string $role, array $attributes = []): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        // Ensure all permissions exist in DB before assigning to any role
        $allPermissions = array_unique(array_merge(...array_values(static::$rolePermissions)));
        foreach ($allPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Assign permissions to role (admin gets all, others get their default set)
        if ($role === 'admin') {
            $roleModel->syncPermissions(Permission::all());
        } elseif (isset(static::$rolePermissions[$role])) {
            $roleModel->syncPermissions(static::$rolePermissions[$role]);
        }

        $user = User::factory()->create($attributes);
        $user->assignRole($roleModel);

        return $user;
    }
}
