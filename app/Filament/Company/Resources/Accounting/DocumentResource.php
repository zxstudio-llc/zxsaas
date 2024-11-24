<?php

namespace App\Filament\Company\Resources\Accounting;

use App\Filament\Company\Resources\Accounting\DocumentResource\Pages;
use App\Models\Accounting\Document;
use App\Models\Common\Offering;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;

        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Header')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Split::make([
                            Forms\Components\Group::make([
                                FileUpload::make('logo')
                                    ->openable()
                                    ->maxSize(1024)
                                    ->localizeLabel()
                                    ->visibility('public')
                                    ->disk('public')
                                    ->directory('logos/document')
                                    ->imageResizeMode('contain')
                                    ->imageCropAspectRatio('3:2')
                                    ->panelAspectRatio('3:2')
                                    ->maxWidth(MaxWidth::ExtraSmall)
                                    ->panelLayout('integrated')
                                    ->removeUploadedFileButtonPosition('center bottom')
                                    ->uploadButtonPosition('center bottom')
                                    ->uploadProgressIndicatorPosition('center bottom')
                                    ->getUploadedFileNameForStorageUsing(
                                        static fn (TemporaryUploadedFile $file): string => (string) str($file->getClientOriginalName())
                                            ->prepend(Auth::user()->currentCompany->id . '_'),
                                    )
                                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/gif']),
                            ]),
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('header')
                                    ->default(fn () => $company->defaultInvoice->header),
                                Forms\Components\TextInput::make('subheader')
                                    ->default(fn () => $company->defaultInvoice->subheader),
                                Forms\Components\View::make('filament.forms.components.company-info')
                                    ->viewData([
                                        'company_name' => $company->name,
                                        'company_address' => $company->profile->address,
                                        'company_city' => $company->profile->city?->name,
                                        'company_state' => $company->profile->state?->name,
                                        'company_zip' => $company->profile->zip_code,
                                        'company_country' => $company->profile->state?->country->name,
                                    ]),
                            ])->grow(true),
                        ])->from('md'),
                    ]),
                Forms\Components\Section::make('Invoice Details')
                    ->schema([
                        Forms\Components\Split::make([
                            Forms\Components\Group::make([
                                Forms\Components\Select::make('client_id')
                                    ->relationship('client', 'name'),
                            ]),
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('document_number')
                                    ->label('Invoice Number')
                                    ->default(fn () => $company->defaultInvoice->getNumberNext()),
                                Forms\Components\TextInput::make('order_number')
                                    ->label('P.O/S.O Number'),
                                Forms\Components\DatePicker::make('date')
                                    ->label('Invoice Date')
                                    ->default(now()),
                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Payment Due')
                                    ->default(function () use ($company) {
                                        return now()->addDays($company->defaultInvoice->payment_terms->getDays());
                                    }),
                            ])->grow(true),
                        ])->from('md'),
                        TableRepeater::make('lineItems')
                            ->relationship()
                            ->headers([
                                Header::make('Items'),
                                Header::make('Description'),
                                Header::make('Quantity'),
                                Header::make('Price'),
                                Header::make('Amount'),
                            ])
                            ->schema([
                                Forms\Components\Select::make('offering_id')
                                    ->relationship('offering', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                        $offeringId = $state;
                                        $offeringRecord = Offering::find($offeringId);
                                        $set('description', $offeringRecord->description);
                                        $set('unit_price', $offeringRecord->price);
                                        $set('total', $offeringRecord->price);
                                    }),
                                Forms\Components\TextInput::make('description')
                                    ->required(),
                                Forms\Components\TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->live()
                                    ->default(1),
                                Forms\Components\Group::make([
                                    Forms\Components\TextInput::make('unit_price')
                                        ->hiddenLabel()
                                        ->live()
                                        ->numeric()
                                        ->default(0),
                                ]),
                                Forms\Components\Placeholder::make('total')
                                    ->hiddenLabel()
                                    ->content(function (Forms\Get $get) {
                                        $quantity = $get('quantity');
                                        $unitPrice = $get('unit_price');

                                        if ($quantity && $unitPrice) {
                                            return $quantity * $unitPrice;
                                        }
                                    }),
                            ]),
                        Forms\Components\Textarea::make('terms')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Invoice Footer')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Textarea::make('footer')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('logo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('header')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subheader')
                    ->searchable(),
                Tables\Columns\TextColumn::make('document_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('currency_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subtotal')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_total')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_total')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_due')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
