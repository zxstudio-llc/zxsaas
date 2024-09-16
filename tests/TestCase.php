<?php

namespace Tests;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Indicates whether the default seeder should run before each test.
     */
    protected bool $seed = true;

    /**
     * Run a specific seeder before each test.
     */
    protected string $seeder = TestDatabaseSeeder::class;

    protected User $testUser;

    protected ?Company $testCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = User::first();

        $this->testCompany = $this->testUser->ownedCompanies->first();

        $this->testUser->switchCompany($this->testCompany);

        $this->actingAs($this->testUser)
            ->withSession(['current_company_id' => $this->testCompany->id]);
    }
}
