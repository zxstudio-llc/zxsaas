<?php

namespace App\Filament\Company\Pages;

use App\Enums\Setting\EntityType;
use App\Events\CompanyGenerated;
use App\Models\Company;
use App\Models\Locale\Country;
use App\Models\Setting\Localization;
use App\Utilities\Currency\CurrencyAccessor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Wallo\FilamentCompanies\Events\AddingCompany;
use Wallo\FilamentCompanies\FilamentCompanies;
use Wallo\FilamentCompanies\Pages\Company\CreateCompany as FilamentCreateCompany;

class CreateCompany extends FilamentCreateCompany
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('filament-companies::default.labels.company_name'))
                    ->autofocus()
                    ->maxLength(255)
                    ->softRequired(),
                TextInput::make('profile.email')
                    ->label('Company Email')
                    ->email()
                    ->softRequired(),
                Select::make('profile.entity_type')
                    ->label('Entity Type')
                    ->options(EntityType::class)
                    ->softRequired(),
                Select::make('profile.country')
                    ->label('Country')
                    ->live()
                    ->searchable()
                    ->options(Country::getAvailableCountryOptions())
                    ->softRequired(),
                Select::make('locale.language')
                    ->label('Language')
                    ->searchable()
                    ->options(Localization::getAllLanguages())
                    ->softRequired(),
                Select::make('currencies.code')
                    ->label('Currency')
                    ->searchable()
                    ->options(CurrencyAccessor::getAllCurrencyOptions())
                    ->optionsLimit(5)
                    ->softRequired(),
            ])
            ->model(FilamentCompanies::companyModel())
            ->statePath('data');
    }

    protected function handleRegistration(array $data): Model
    {
        $user = Auth::user();

        Gate::forUser($user)->authorize('create', FilamentCompanies::newCompanyModel());

        AddingCompany::dispatch($user);

        $personalCompany = $user?->personalCompany() === null;

        /** @var Company $company */
        $company = $user?->ownedCompanies()->create([
            'name' => $data['name'],
            'personal_company' => $personalCompany,
        ]);

        $company->profile()->create([
            'email' => $data['profile']['email'],
            'entity_type' => $data['profile']['entity_type'],
            'country' => $data['profile']['country'],
        ]);

        $user?->switchCompany($company);

        $name = $data['name'];

        CompanyGenerated::dispatch($user ?? Auth::user(), $company, $data['profile']['country'], $data['locale']['language'], $data['currencies']['code']);

        $this->companyCreated($name);

        return $company;
    }
}
