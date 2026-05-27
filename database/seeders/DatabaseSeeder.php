<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Production Seeding Flow
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            AdminUserSeeder::class,
        ]);

        // Demo data is explicitly excluded for production safety.
        // To review current functionality with examples, run:
        // php artisan db:seed --class=CurrentFunctionalityDemoSeeder
    }
}
