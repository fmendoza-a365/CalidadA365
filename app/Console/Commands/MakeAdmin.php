<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class MakeAdmin extends Command
{
    protected $signature = 'user:make-admin {email}';
    protected $description = 'Update a user to admin role';

    public function handle()
    {
        $email = $this->argument('email');
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User not found: {$email}");
            return 1;
        }
        
        // Remove all current roles and assign admin
        $user->syncRoles([]);
        $user->assignRole('admin');
        
        $this->info("✓ User {$email} updated to admin role!");
        return 0;
    }
}
