<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Setting\CompanyDefault;
use App\Models\Setting\CompanyProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Wallo\FilamentCompanies\FilamentCompanies;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'profile_photo_path' => null,
            'current_company_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(static fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should have a personal company.
     */
    public function withPersonalCompany(?callable $callback = null): static
    {
        if (! FilamentCompanies::hasCompanyFeatures()) {
            return $this->state([]);
        }

        $countryCode = $this->faker->countryCode;

        return $this->afterCreating(function (User $user) use ($countryCode, $callback) {
            Company::factory()
                ->state(static fn (array $attributes, User $user) => [
                    'name' => $user->name . '\'s Company',
                    'user_id' => $user->id,
                    'personal_company' => true,
                ])
                ->has(CompanyProfile::factory()->withCountry($countryCode), 'profile')
                ->afterCreating(function (Company $company) use ($user, $countryCode) {
                    CompanyDefault::factory()->withDefault($user, $company, $countryCode)->create();
                })
                ->when(is_callable($callback), $callback)
                ->create();
        });
    }
}
