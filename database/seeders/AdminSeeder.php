<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * IMPORTANT: This seeder is idempotent - it will only create the admin user if it doesn't exist.
     */
    public function run(): void
    {
        // Check if admin user already exists
        $adminEmail = 'admin@qa365.com';
        
        $existingAdmin = User::where('email', $adminEmail)->first();
        
        if ($existingAdmin) {
            $this->command->info("✓ Admin user already exists: {$adminEmail}");
            return;
        }

        // Create permanent super admin user
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => $adminEmail,
            'password' => Hash::make('admin123'), // Change after first login!
            'email_verified_at' => now(),
        ]);

        // Assign qa_manager role (highest permission level)
        $admin->assignRole('qa_manager');

        $this->command->info("✓ Super admin created successfully!");
        $this->command->warn("⚠ Default credentials:");
        $this->command->warn("   Email: {$adminEmail}");
        $this->command->warn("   Password: admin123");
        $this->command->warn("   PLEASE CHANGE THE PASSWORD AFTER FIRST LOGIN!");
    }
}
