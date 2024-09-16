<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()
            ->withPersonalCompany()
            ->createQuietly([
                'name' => 'Test Company Owner',
                'email' => 'test@gmail.com',
                'password' => bcrypt('password'),
                'current_company_id' => 1,
            ]);
    }
}
