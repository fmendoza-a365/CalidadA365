<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {email : Admin email} {name=Administrator : Admin display name}';

    protected $description = 'Create a super administrator user';

    public function handle()
    {
        $email = $this->argument('email');
        $name = $this->argument('name');

        $userValidator = Validator::make([
            'email' => $email,
            'name' => $name,
        ], [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($userValidator->fails()) {
            foreach ($userValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return 1;
        }

        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');

        $passwordValidator = Validator::make([
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return 1;
        }

        $username = $this->uniqueUsername($email, $name);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('admin');

        $this->info('Admin user created successfully.');
        $this->info("Username: {$username}");
        $this->info("Email: {$email}");
        $this->info('Role: admin');

        return 0;
    }

    private function uniqueUsername(string $email, string $name): string
    {
        $base = Str::slug(Str::before($email, '@')) ?: Str::slug($name);
        $base = Str::limit($base ?: 'admin', 45, '');
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $suffix = (string) $counter;
            $username = Str::limit($base, 50 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $username;
    }
}
