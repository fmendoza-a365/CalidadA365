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
        $adminEmail = 'fmendoza@a365.com.pe';
        
        $admin = User::where('email', $adminEmail)->first();
        
        if (!$admin) {
            $admin = User::create([
                'name' => 'Fernando Mendoza',
                'username' => 'fmendoza',
                'email' => $adminEmail,
                'password' => Hash::make('@Asdasd23**'),
                'email_verified_at' => now(),
            ]);
        }

        // ALWAYS update to admin role
        $admin->syncRoles(['admin']);
        
        $this->command->info("✓ Admin role assigned: {$adminEmail}");
    }
}
