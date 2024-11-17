<?php

namespace App\Filament\Company\Resources\Purchases;

use App\Filament\Company\Resources\Common\OfferingResource;
use App\Filament\Company\Resources\Purchases\BuyableOfferingResource\Pages;
use App\Models\Common\Offering;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BuyableOfferingResource extends Resource
{
    protected static ?string $model = Offering::class;

    protected static ?string $pluralModelLabel = 'Products & Services';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('expense_account_id');
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
            'index' => Pages\ListBuyableOfferings::route('/'),
            'create' => Pages\CreateBuyableOffering::route('/create'),
            'edit' => Pages\EditBuyableOffering::route('/{record}/edit'),
        ];
    }
}
