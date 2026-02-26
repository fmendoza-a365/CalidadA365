<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Agente Top Performer (Diamond/Gold Potential) - Lima
        $agent1 = User::create([
            'name' => 'Lucía',
            'paternal_surname' => 'Fernández',
            'maternal_surname' => 'Chávez',
            'email' => 'lucia.fernandez@a365.com.pe',
            'personal_email' => 'lucia.fer@gmail.com',
            'password' => Hash::make('password'),
            'birthdate' => '1998-05-15', // 26 years old
            'gender' => 'F',
            'personal_phone' => '987654321',
            'company_phone' => '01-200-1000 Anexo 101',
            'address' => 'Av. Javier Prado 450, San Isidro',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'San Isidro',
        ]);
        $agent1->assignRole('agent');

        // 2. Supervisor (Silver) - Arequipa
        $sup = User::create([
            'name' => 'Carlos',
            'paternal_surname' => 'Mamani',
            'maternal_surname' => 'Quispe',
            'email' => 'carlos.mamani@a365.com.pe',
            'password' => Hash::make('password'),
            'birthdate' => '1990-11-02', 
            'gender' => 'M',
            'personal_phone' => '955123456',
            'address' => 'Calle Mercaderes 123',
            'department' => 'Arequipa',
            'province' => 'Arequipa',
            'district' => 'Arequipa',
        ]);
        $sup->assignRole('supervisor');

        // 3. Agente Newbie (Bronze/Standard) - Trujillo
        $agent2 = User::create([
            'name' => 'Miguel',
            'paternal_surname' => 'Torres',
            'maternal_surname' => 'Vega',
            'email' => 'miguel.torres@a365.com.pe',
            'password' => Hash::make('password'),
            'birthdate' => '2001-03-20',
            'gender' => 'M',
            'personal_phone' => '944888777',
            'address' => 'Av. Larco 500',
            'department' => 'La Libertad',
            'province' => 'Trujillo',
            'district' => 'Trujillo',
        ]);
        $agent2->assignRole('agent');
        
        // 4. QA Monitor (Gold) - Callao
        $qa = User::create([
            'name' => 'Sofia',
            'paternal_surname' => 'Ramírez',
            'maternal_surname' => 'Pérez',
            'email' => 'sofia.qa@a365.com.pe',
            'password' => Hash::make('password'),
            'birthdate' => '1995-08-10',
            'gender' => 'F',
            'address' => 'Av. Saenz Peña 400',
            'department' => 'Callao',
            'province' => 'Callao',
            'district' => 'Bellavista',
        ]);
        $qa->assignRole('qa_monitor');
    }
}
