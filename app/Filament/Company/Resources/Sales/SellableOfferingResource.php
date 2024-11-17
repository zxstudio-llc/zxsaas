<?php

namespace App\Filament\Company\Resources\Sales;

use App\Filament\Company\Resources\Common\OfferingResource;
use App\Filament\Company\Resources\Sales\SellableOfferingResource\Pages;
use App\Models\Common\Offering;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SellableOfferingResource extends Resource
{
    protected static ?string $model = Offering::class;

    protected static ?string $pluralModelLabel = 'Products & Services';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('income_account_id');
    }

    public static function form(Form $form): Form
    {
        return OfferingResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return OfferingResource::table($table);
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
            'index' => Pages\ListSellableOfferings::route('/'),
            'create' => Pages\CreateSellableOffering::route('/create'),
            'edit' => Pages\EditSellableOffering::route('/{record}/edit'),
        ];
    }
}
