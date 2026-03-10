<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Schedule;
use App\Models\CompanySetting;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear usuario de prueba
        $user = User::factory()->create([
            'name' => 'Usuario Test',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Crear configuración de la empresa (Ubicación de ejemplo: Centro de una ciudad)
        CompanySetting::create(['key' => 'latitude', 'value' => '20.659698']);
        CompanySetting::create(['key' => 'longitude', 'value' => '-103.349609']);

        // Crear horario para el usuario
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        foreach ($days as $day) {
            Schedule::create([
                'user_id' => $user->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
            ]);
        }
    }
}
