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
        $adminEmail = 'fmendoza@a365.com.pe';
        
        $existingAdmin = User::where('email', $adminEmail)->first();
        
        if ($existingAdmin) {
            $this->command->info("✓ Admin user already exists: {$adminEmail}");
            return;
        }

        // Create permanent super admin user
        $admin = User::create([
            'name' => 'Fernando Mendoza',
            'email' => $adminEmail,
            'password' => Hash::make('@Asdasd23**'),
            'email_verified_at' => now(),
        ]);

        // Assign qa_manager role (highest permission level)
        $admin->assignRole('qa_manager');

        $this->command->info("✓ Super admin created successfully!");
        $this->command->info("   Email: {$adminEmail}");
    }
}
