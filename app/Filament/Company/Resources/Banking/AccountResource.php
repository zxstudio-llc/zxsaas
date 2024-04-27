<?php

namespace App\Filament\Company\Resources\Banking;

use App\Actions\OptionAction\CreateCurrency;
use App\Enums\Accounting\AccountCategory;
use App\Enums\Banking\BankAccountType;
use App\Facades\Forex;
use App\Filament\Company\Resources\Banking\AccountResource\Pages;
use App\Models\Accounting\AccountSubtype;
use App\Models\Banking\BankAccount;
use App\Utilities\Currency\CurrencyAccessor;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Unique;
use Wallo\FilamentSelectify\Components\ToggleButton;

class AccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static ?string $modelLabel = 'Account';

    public static function getModelLabel(): string
    {
        $modelLabel = static::$modelLabel;

        return translate($modelLabel);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Account Information')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options(BankAccountType::class)
                            ->localizeLabel()
                            ->searchable()
                            ->columnSpan(1)
                            ->default(BankAccountType::DEFAULT)
                            ->live()
                            ->afterStateUpdated(static function (Forms\Set $set, $state, ?BankAccount $record, string $operation) {
                                if ($operation === 'create') {
                                    $set('account.subtype_id', null);
                                } elseif ($operation === 'edit' && $record !== null) {
                                    if ($state !== $record->type->value) {
                                        $set('account.subtype_id', null);
                                    } else {
                                        $set('account.subtype_id', $record->account->subtype_id);
                                    }
                                }
                            })
                            ->required(),
                        Forms\Components\Group::make()
                            ->columnStart(2)
                            ->relationship('account')
                            ->schema([
                                Forms\Components\Select::make('subtype_id')
                                    ->options(static function (Forms\Get $get) {
                                        $typeValue = $get('data.type', true); // Bug: $get('type') returns string on edit, but returns Enum type on create
                                        $typeString = $typeValue instanceof BackedEnum ? $typeValue->value : $typeValue;

                                        return static::groupSubtypesBySubtypeType($typeString);
                                    })
                                    ->localizeLabel()
                                    ->searchable()
                                    ->live()
                                    ->required(),
                            ]),
                        Forms\Components\Group::make()
                            ->relationship('account')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->maxLength(100)
                                    ->localizeLabel()
                                    ->required(),
                                Forms\Components\Select::make('currency_code')
                                    ->localizeLabel('Currency')
                                    ->relationship('currency', 'name')
                                    ->default(CurrencyAccessor::getDefaultCurrency())
                                    ->preload()
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\Select::make('code')
                                            ->localizeLabel()
                                            ->searchable()
                                            ->options(CurrencyAccessor::getAvailableCurrencies())
                                            ->live()
                                            ->afterStateUpdated(static function (callable $set, $state) {
                                                if ($state === null) {
                                                    return;
                                                }

                                                $currency_code = currency($state);
                                                $defaultCurrencyCode = currency()->getCurrency();
                                                $forexEnabled = Forex::isEnabled();
                                                $exchangeRate = $forexEnabled ? Forex::getCachedExchangeRate($defaultCurrencyCode, $state) : null;

                                                $set('name', $currency_code->getName() ?? '');

                                                if ($forexEnabled && $exchangeRate !== null) {
                                                    $set('rate', $exchangeRate);
                                                }
                                            })
                                            ->required(),
                                        Forms\Components\TextInput::make('name')
                                            ->localizeLabel()
                                            ->maxLength(100)
                                            ->required(),
                                        Forms\Components\TextInput::make('rate')
                                            ->localizeLabel()
                                            ->numeric()
                                            ->required(),
                                    ])->createOptionAction(static function (Forms\Components\Actions\Action $action) {
                                        return $action
                                            ->label('Add Currency')
                                            ->slideOver()
                                            ->modalWidth('md')
                                            ->action(static function (array $data) {
                                                return DB::transaction(static function () use ($data) {
                                                    $code = $data['code'];
                                                    $name = $data['name'];
                                                    $rate = $data['rate'];

                                                    return (new CreateCurrency())->create($code, $name, $rate);
                                                });
                                            });
                                    }),
                            ]),
                        Forms\Components\Group::make()
                            ->columns()
                            ->columnSpanFull()
                            ->schema([
                                Forms\Components\TextInput::make('number')
                                    ->localizeLabel('Account Number')
                                    ->unique(ignoreRecord: true, modifyRuleUsing: static function (Unique $rule, $state) {
                                        $companyId = Auth::user()->currentCompany->id;

                                        return $rule->where('company_id', $companyId)->where('number', $state);
                                    })
                                    ->maxLength(20)
                                    ->validationAttribute('account number'),
                                ToggleButton::make('enabled')
                                    ->localizeLabel('Default'),
                            ]),
                    ])->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('account.name')
                    ->localizeLabel('Account')
                    ->searchable()
                    ->weight(FontWeight::Medium)
                    ->icon(static fn (BankAccount $record) => $record->isEnabled() ? 'heroicon-o-lock-closed' : null)
                    ->tooltip(static fn (BankAccount $record) => $record->isEnabled() ? 'Default Account' : null)
                    ->iconPosition('after')
                    ->description(static fn (BankAccount $record) => $record->mask ?? null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.ending_balance')
                    ->localizeLabel('Current Balance')
                    ->state(static fn (BankAccount $record) => $record->account->ending_balance->convert()->formatWithCode())
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription('Are you sure you want to delete the selected accounts? All transactions associated with the accounts will be deleted as well.'),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(static function (BankAccount $record) {
                return $record->isDisabled();
            })
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }

    public static function groupSubtypesBySubtypeType($typeString): array
    {
        $category = match ($typeString) {
            BankAccountType::Depository->value, BankAccountType::Investment->value => AccountCategory::Asset,
            BankAccountType::Credit->value, BankAccountType::Loan->value => AccountCategory::Liability,
            default => null,
        };

        if ($category === null) {
            return [];
        }

        $subtypes = AccountSubtype::where('category', $category)->get();

        return $subtypes->groupBy(fn (AccountSubtype $subtype) => $subtype->type->getLabel())
            ->map(fn (Collection $subtypes, string $type) => $subtypes->mapWithKeys(static fn (AccountSubtype $subtype) => [$subtype->id => $subtype->name]))
            ->toArray();
    }
}
