<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedCourts();
    }

    private function seedAdmin(): void
    {
        $password = config('booking.admin_password');

        if (app()->isProduction() && ! $password) {
            throw new \RuntimeException('Set SEED_ADMIN_PASSWORD sebelum seeding di production.');
        }

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => Hash::make($password ?? 'password')],
        )->forceFill(['role' => User::ROLE_ADMIN])->save();
    }

    private function seedCourts(): void
    {
        $default = config('booking.default_price');

        $courts = [
            'Lapangan A' => $default,
            'Lapangan B' => $default,
            'Lapangan C' => 120_000,
        ];

        foreach ($courts as $name => $price) {
            Court::updateOrCreate(
                ['name' => $name],
                ['sport' => 'futsal', 'price_per_hour' => $price, 'is_active' => true],
            );
        }
    }
}