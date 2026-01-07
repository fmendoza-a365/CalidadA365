<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Evaluations
            // Evaluations (Scopes)
            'view_all_evaluations',      // Admin/Manager
            'view_team_evaluations',     // Supervisor/Coordinator
            'view_assigned_evaluations', // Monitor (ones they performed)
            'view_own_evaluations',      // Agent (ones received)
            
            // Evaluation Actions
            'create_evaluations',
            'edit_evaluations',
            'delete_evaluations',
            
            // Campaigns
            'view_campaigns',
            'create_campaigns',
            'edit_campaigns',
            'delete_campaigns',
            'assign_agents',

            // Users & Roles
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'manage_roles',

            // Quality Forms
            'view_quality_forms',
            'create_quality_forms',
            'edit_quality_forms',
            'delete_quality_forms',
            'publish_quality_forms',

            // Dashboard
            'view_admin_dashboard',
            'view_supervisor_dashboard',
            'view_agent_dashboard',
            'view_monitor_dashboard',
            'view_coordinator_dashboard',
            'view_manager_dashboard',
            
            // System
            'view_system_settings',
            'manage_ai_settings',

            // Insights
            'view_insights',
            'generate_insights',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Auto-assign all permissions to Admin
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions(Permission::all());
        }

        // Assign Insights to QA Manager
        $qaManager = \Spatie\Permission\Models\Role::where('name', 'qa_manager')->first();
        if ($qaManager) {
            $qaManager->givePermissionTo(['view_insights', 'generate_insights']);
        }

        // Assign Insights View to Supervisor
        $supervisor = \Spatie\Permission\Models\Role::where('name', 'supervisor')->first();
        if ($supervisor) {
            $supervisor->givePermissionTo(['view_insights']);
        }

        // Assign Agent Permissions
        $agentRole = \Spatie\Permission\Models\Role::where('name', 'agent')->first();
        if ($agentRole) {
            $agentRole->givePermissionTo(['view_own_evaluations', 'view_agent_dashboard']);
        }

        // Assign Manager Permissions (Sees ALL data like Admin, but cannot manage system)
        $managerRole = \Spatie\Permission\Models\Role::where('name', 'manager')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo([
                'view_all_evaluations',
                'view_campaigns',
                'view_quality_forms',
                'view_manager_dashboard',
                'view_insights',
                'view_users',
            ]);
        }

        // Assign Supervisor Permissions (Sees team data only)
        $supervisorRole = \Spatie\Permission\Models\Role::where('name', 'supervisor')->first();
        if ($supervisorRole) {
            $supervisorRole->givePermissionTo([
                'view_team_evaluations',
                'view_campaigns',
                'view_quality_forms',
                'view_supervisor_dashboard',
                'view_insights',
            ]);
        }

        // Assign QA Monitor Permissions (Creates evaluations, sees only assigned)
        $qaMonitorRole = \Spatie\Permission\Models\Role::where('name', 'qa_monitor')->first();
        if ($qaMonitorRole) {
            $qaMonitorRole->givePermissionTo([
                'create_evaluations',
                'view_assigned_evaluations',
                'view_campaigns',
                'view_quality_forms',
                'view_monitor_dashboard',
            ]);
        }

        // Assign QA Coordinator Permissions (Manages monitors, sees team evaluations)
        $qaCoordinatorRole = \Spatie\Permission\Models\Role::where('name', 'qa_coordinator')->first();
        if ($qaCoordinatorRole) {
            $qaCoordinatorRole->givePermissionTo([
                'view_team_evaluations',
                'create_evaluations',
                'view_campaigns',
                'view_quality_forms',
                'view_coordinator_dashboard',
                'view_insights',
            ]);
        }
    }
}
