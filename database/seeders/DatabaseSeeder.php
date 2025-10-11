<?php

namespace Database\Seeders;

use App\Models\User;
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
        // Create Super Admin (user_type = 0)
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'user_type' => 0,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create Business Admin (user_type = 1)
        User::factory()->create([
            'name' => 'Business Admin',
            'email' => 'business@example.com',
            'user_type' => 1,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Create Regular User (user_type = 2)
        User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'user_type' => 2,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }
}
