<?php

namespace App\Filament\Company\Resources\Common;

use App\Enums\Common\ContractorType;
use App\Enums\Common\VendorType;
use App\Filament\Company\Resources\Common\VendorResource\Pages;
use App\Filament\Forms\Components\CreateCurrencySelect;
use App\Filament\Forms\Components\CustomSection;
use App\Filament\Forms\Components\PhoneBuilder;
use App\Models\Common\Vendor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General Information')
                    ->schema([
                        Forms\Components\Group::make()
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Vendor Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Radio::make('type')
                                    ->label('Vendor Type')
                                    ->required()
                                    ->live()
                                    ->options(VendorType::class)
                                    ->default(VendorType::Regular)
                                    ->columnSpanFull(),
                                CreateCurrencySelect::make('currency_code')
                                    ->relationship('currency', 'name')
                                    ->nullable()
                                    ->visible(fn (Forms\Get $get) => VendorType::parse($get('type')) === VendorType::Regular),
                                Forms\Components\Select::make('contractor_type')
                                    ->label('Contractor Type')
                                    ->required()
                                    ->live()
                                    ->visible(fn (Forms\Get $get) => VendorType::parse($get('type')) === VendorType::Contractor)
                                    ->options(ContractorType::class),
                                Forms\Components\TextInput::make('ssn')
                                    ->label('Social Security Number')
                                    ->required()
                                    ->live()
                                    ->mask('999-99-9999')
                                    ->stripCharacters('-')
                                    ->maxLength(11)
                                    ->visible(fn (Forms\Get $get) => ContractorType::parse($get('contractor_type')) === ContractorType::Individual)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('ein')
                                    ->label('Employer Identification Number')
                                    ->required()
                                    ->live()
                                    ->mask('99-9999999')
                                    ->stripCharacters('-')
                                    ->maxLength(10)
                                    ->visible(fn (Forms\Get $get) => ContractorType::parse($get('contractor_type')) === ContractorType::Business)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('account_number')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('website')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('notes')
                                    ->columnSpanFull(),
                            ]),
                        CustomSection::make('Primary Contact')
                            ->relationship('contact')
                            ->contained(false)
                            ->schema([
                                Forms\Components\Hidden::make('is_primary')
                                    ->default(true),
                                Forms\Components\TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->required()
                                    ->email()
                                    ->columnSpanFull()
                                    ->maxLength(255),
                                PhoneBuilder::make('phones')
                                    ->hiddenLabel()
                                    ->blockLabels(false)
                                    ->default([
                                        ['type' => 'primary'],
                                    ])
                                    ->columnSpanFull()
                                    ->blocks([
                                        Forms\Components\Builder\Block::make('primary')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Phone')
                                                    ->required()
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                        Forms\Components\Builder\Block::make('mobile')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Mobile')
                                                    ->required()
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                        Forms\Components\Builder\Block::make('toll_free')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Toll Free')
                                                    ->required()
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                        Forms\Components\Builder\Block::make('fax')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Fax')
                                                    ->live()
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                    ])
                                    ->deletable(fn (PhoneBuilder $builder) => $builder->getItemsCount() > 1)
                                    ->reorderable(false)
                                    ->blockNumbers(false)
                                    ->addActionLabel('Add Phone'),
                            ])->columns(),
                    ])->columns(1),
                Forms\Components\Section::make('Address Information')
                    ->relationship('address')
                    ->schema([
                        Forms\Components\Hidden::make('type')
                            ->default('general'),
                        Forms\Components\TextInput::make('address_line_1')
                            ->label('Address Line 1')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('address_line_2')
                            ->label('Address Line 2')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('state')
                            ->label('State')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('postal_code')
                            ->label('Postal Code / Zip Code')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('country')
                            ->label('Country')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Vendor $vendor) => $vendor->contact?->full_name),
                Tables\Columns\TextColumn::make('contact.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('primaryContact.phones')
                    ->label('Phone')
                    ->state(fn (Vendor $vendor) => $vendor->contact?->first_available_phone),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'edit' => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
