<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Identidad
            $table->string('paternal_surname')->nullable()->after('name');
            $table->string('maternal_surname')->nullable()->after('paternal_surname');
            $table->date('birthdate')->nullable()->after('email');
            $table->string('gender')->nullable()->after('birthdate'); // M, F, O

            // Contacto
            $table->string('personal_phone')->nullable()->after('gender');
            $table->string('company_phone')->nullable()->after('personal_phone');
            $table->string('personal_email')->nullable()->after('email');

            // Ubicación (Perú)
            $table->string('address')->nullable()->after('company_phone');
            $table->string('department')->nullable()->after('address');
            $table->string('province')->nullable()->after('department');
            $table->string('district')->nullable()->after('province');

            // Perfil
            $table->string('profile_photo_path', 2048)->nullable()->after('district');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'paternal_surname',
                'maternal_surname',
                'birthdate',
                'gender',
                'personal_phone',
                'company_phone',
                'personal_email',
                'address',
                'department',
                'province',
                'district',
                'profile_photo_path',
            ]);
        });
    }
};
