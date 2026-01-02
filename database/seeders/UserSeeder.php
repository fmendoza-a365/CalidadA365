<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@qa.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole('admin');
        
        $qa = User::firstOrCreate(
            ['email' => 'qa@qa.com'],
            [
                'name' => 'QA Manager',
                'password' => Hash::make('password'),
            ]
        );
        $qa->assignRole('qa_manager');
        
        $supervisor = User::firstOrCreate(
            ['email' => 'supervisor@qa.com'],
            [
                'name' => 'Supervisor',
                'password' => Hash::make('password'),
            ]
        );
        $supervisor->assignRole('supervisor');
        
        $agent = User::firstOrCreate(
            ['email' => 'agent@qa.com'],
            [
                'name' => 'Agent',
                'password' => Hash::make('password'),
            ]
        );
        $agent->assignRole('agent');
    }
}
