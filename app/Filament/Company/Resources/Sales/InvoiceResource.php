<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\DocumentType;
use App\Filament\Company\Resources\Accounting\DocumentResource;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages;
use App\Models\Accounting\Document;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $pluralModelLabel = 'Invoices';

    protected static ?string $modelLabel = 'Invoice';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', DocumentType::Invoice);
    }

    public static function form(Form $form): Form
    {
        return DocumentResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return DocumentResource::table($table);
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
