<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'admin', 
            'qa_manager', 
            'supervisor', 
            'agent',
            'qa_monitor',      // Evaluates calls
            'qa_coordinator',  // Manages monitors
            'manager'          // Views campaign results
        ];
        
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
