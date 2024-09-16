<?php

use App\Enums\Setting\EntityType;
use App\Filament\Company\Pages\CreateCompany;
use App\Models\Company;

use function Pest\Livewire\livewire;

function createCompany(string $name): Company
{
    livewire(CreateCompany::class)
        ->fillForm([
            'name' => $name,
            'profile.email' => 'company@gmail.com',
            'profile.entity_type' => EntityType::LimitedLiabilityCompany,
            'profile.country' => 'US',
            'locale.language' => 'en',
            'currencies.code' => 'USD',
        ])
        ->call('register')
        ->assertHasNoErrors();

    return auth()->user()->currentCompany;
}
