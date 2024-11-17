<?php

namespace App\Filament\Company\Resources\Common;

use App\Enums\Common\OfferingType;
use App\Filament\Company\Resources\Common\OfferingResource\Pages;
use App\Models\Common\Offering;
use App\Utilities\Currency\CurrencyAccessor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OfferingResource extends Resource
{
    protected static ?string $model = Offering::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->autofocus()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->autosize(),
                        Forms\Components\Select::make('type')
                            ->options(OfferingType::class)
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->money(CurrencyAccessor::getDefaultCurrency()),
                        Forms\Components\Checkbox::make('sellable')
                            ->label('Sellable')
                            ->live()
                            ->dehydrated(false)
                            ->default(false),
                        Forms\Components\Checkbox::make('purchasable')
                            ->label('Purchasable')
                            ->live()
                            ->dehydrated(false)
                            ->default(false),
                    ]),
                // Sellable Section
                Forms\Components\Section::make('Sellable Configuration')
                    ->schema([
                        Forms\Components\Select::make('income_account_id')
                            ->relationship('incomeAccount', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('sales_taxes')
                            ->relationship('salesTaxes', 'name')
                            ->preload()
                            ->multiple(),
                        Forms\Components\Select::make('sales_discounts')
                            ->relationship('salesDiscounts', 'name')
                            ->preload()
                            ->multiple(),
                    ])
                    ->columns(2)
                    ->visible(fn (Forms\Get $get) => $get('sellable')),

                // Purchasable Section
                Forms\Components\Section::make('Purchasable Configuration')
                    ->schema([
                        Forms\Components\Select::make('expense_account_id')
                            ->relationship('expenseAccount', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('purchase_taxes')
                            ->relationship('purchaseTaxes', 'name')
                            ->preload()
                            ->multiple(),
                        Forms\Components\Select::make('purchase_discounts')
                            ->relationship('purchaseDiscounts', 'name')
                            ->preload()
                            ->multiple(),
                    ])
                    ->columns(2)
                    ->visible(fn (Forms\Get $get) => $get('purchasable')),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ViewColumn::make('name')
                    ->label('Name')
                    ->view('filament.company.components.tables.columns.offering-status'),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
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
            'index' => Pages\ListOfferings::route('/'),
            'create' => Pages\CreateOffering::route('/create'),
            'edit' => Pages\EditOffering::route('/{record}/edit'),
        ];
    }
}
