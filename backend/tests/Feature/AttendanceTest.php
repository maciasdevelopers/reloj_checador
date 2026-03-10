<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CompanySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['access_token', 'token_type', 'user']);
    }

    public function test_check_in_fails_if_too_far()
    {
        $user = User::factory()->create();
        CompanySetting::create(['key' => 'latitude', 'value' => '20.659698']);
        CompanySetting::create(['key' => 'longitude', 'value' => '-103.349609']);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json',
        ])->postJson('/api/check-in', [
            'latitude' => 21.000000, // Lejos
            'longitude' => -104.000000,
        ]);

        $response->assertStatus(403);
    }
}
