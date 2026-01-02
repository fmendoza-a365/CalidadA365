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
        // Admin user configuration
        $adminEmail = 'fmendoza@a365.com.pe';
        
        $existingAdmin = User::where('email', $adminEmail)->first();
        
        if ($existingAdmin) {
            // Update existing user to admin role
            $this->command->info("✓ Admin user exists, updating to admin role...");
            
            // Remove all current roles
            $existingAdmin->syncRoles([]);
            
            // Assign admin role
            $existingAdmin->assignRole('admin');
            
            $this->command->info("✓ User updated to admin role: {$adminEmail}");
            return;
        }

        // Create new admin user
        $admin = User::create([
            'name' => 'Fernando Mendoza',
            'username' => 'fmendoza',
            'email' => $adminEmail,
            'password' => Hash::make('@Asdasd23**'),
            'email_verified_at' => now(),
        ]);

        // Assign admin role (highest permission level)
        $admin->assignRole('admin');

        $this->command->info("✓ Super admin created successfully!");
        $this->command->info("   Email: {$adminEmail}");
    }
}
