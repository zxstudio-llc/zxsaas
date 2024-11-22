<?php

namespace App\Filament\Company\Resources\Common;

use App\Filament\Company\Resources\Common\ClientResource\Pages;
use App\Filament\Forms\Components\CreateCurrencySelect;
use App\Filament\Forms\Components\PhoneBuilder;
use App\Models\Common\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Client Name')
                            ->required()
                            ->maxLength(255),
                        CreateCurrencySelect::make('currency_code')
                            ->disabledOn('edit')
                            ->relationship('currency', 'name'),
                        Forms\Components\TextInput::make('account_number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('website')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])->columns(),
                Forms\Components\Section::make('Primary Contact')
                    ->relationship('primaryContact')
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
                            ->maxLength(255),
                        PhoneBuilder::make('phones')
                            ->hiddenLabel()
                            ->blockLabels(false)
                            ->default([
                                ['type' => 'office'],
                            ])
                            ->blocks([
                                Forms\Components\Builder\Block::make('office')
                                    ->label('Office')
                                    ->schema([
                                        Forms\Components\TextInput::make('number')
                                            ->label('Office')
                                            ->required()
                                            ->maxLength(15),
                                    ])->maxItems(1),
                                Forms\Components\Builder\Block::make('mobile')
                                    ->label('Mobile')
                                    ->schema([
                                        Forms\Components\TextInput::make('number')
                                            ->label('Mobile')
                                            ->required()
                                            ->maxLength(15),
                                    ])->maxItems(1),
                                Forms\Components\Builder\Block::make('toll_free')
                                    ->label('Toll Free')
                                    ->schema([
                                        Forms\Components\TextInput::make('number')
                                            ->label('Toll Free')
                                            ->required()
                                            ->maxLength(15),
                                    ])->maxItems(1),
                                Forms\Components\Builder\Block::make('fax')
                                    ->label('Fax')
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
                    ]),
                Forms\Components\Repeater::make('secondaryContacts')
                    ->relationship()
                    ->columnSpanFull()
                    ->hiddenLabel()
                    ->defaultItems(0)
                    ->addActionLabel('Add Contact')
                    ->schema([
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
                            ->maxLength(255),
                        PhoneBuilder::make('phones')
                            ->hiddenLabel()
                            ->blockLabels(false)
                            ->default([
                                ['type' => 'office'],
                            ])
                            ->blocks([
                                Forms\Components\Builder\Block::make('office')
                                    ->label('Office')
                                    ->schema([
                                        Forms\Components\TextInput::make('number')
                                            ->label('Office')
                                            ->required()
                                            ->maxLength(255),
                                    ])->maxItems(1),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->blockNumbers(false),
                    ]),
                Forms\Components\Section::make('Billing')
                    ->relationship('billingAddress')
                    ->schema([
                        Forms\Components\Hidden::make('type')
                            ->default('billing'),
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
                    ])->columns(),
                Forms\Components\Section::make('Shipping')
                    ->relationship('shippingAddress')
                    ->schema([
                        Forms\Components\Hidden::make('type')
                            ->default('shipping'),
                        Forms\Components\TextInput::make('recipient')
                            ->label('Recipient')
                            ->required()
                            ->maxLength(255),
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
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('notes')
                            ->label('Delivery Instructions')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('currency_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('website')
                    ->searchable(),
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
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
