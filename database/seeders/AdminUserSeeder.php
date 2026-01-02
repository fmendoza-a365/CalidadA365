<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@admin.com';
        
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'name' => 'Super Admin',
                'username' => 'admin',
                'email' => $email,
                'password' => Hash::make('admin'),
                'email_verified_at' => now(),
            ]);
            $this->command->info('✅ Usuario Super Admin creado: admin@admin.com');
        } else {
             $this->command->info("ℹ️ El usuario admin ya existe.");
        }

        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
            $this->command->info('✅ Rol Admin asignado.');
        }

        $this->command->info("   Email: admin@admin.com");
        $this->command->info("   Password: admin");
    }
}
