<?php

namespace App\Filament\Company\Resources\Accounting;

use App\Enums\Accounting\AdjustmentCategory;
use App\Filament\Company\Resources\Accounting\DocumentResource\Pages;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Document;
use App\Models\Common\Offering;
use App\Utilities\Currency\CurrencyAccessor;
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
                                    ->relationship('client', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->required(),
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
                                Header::make('Items')->width('20%'),
                                Header::make('Description')->width('30%'),
                                Header::make('Quantity')->width('10%'),
                                Header::make('Price')->width('10%'),
                                Header::make('Taxes')->width('20%'),
                                Header::make('Amount')->width('10%')->align('right'),
                            ])
                            ->live()
                            ->schema([
                                Forms\Components\Select::make('offering_id')
                                    ->relationship('offering', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        $offeringId = $state;
                                        $offeringRecord = Offering::with('salesTaxes')->find($offeringId);

                                        if ($offeringRecord) {
                                            $set('description', $offeringRecord->description);
                                            $set('unit_price', $offeringRecord->price);
                                            $set('salesTaxes', $offeringRecord->salesTaxes->pluck('id')->toArray());

                                            $quantity = $get('quantity');
                                            $total = $quantity * $offeringRecord->price;

                                            // Calculate taxes and update total
                                            $taxAmount = $offeringRecord->salesTaxes->sum(fn ($tax) => $total * ($tax->rate / 100));
                                            $set('total', $total + $taxAmount);
                                        }
                                    }),
                                Forms\Components\TextInput::make('description')
                                    ->required(),
                                Forms\Components\TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(1),
                                Forms\Components\TextInput::make('unit_price')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\Select::make('salesTaxes')
                                    ->relationship('salesTaxes', 'name')
                                    ->preload()
                                    ->multiple()
                                    ->searchable(),
                                Forms\Components\Placeholder::make('total')
                                    ->hiddenLabel()
                                    ->content(function (Forms\Get $get) {
                                        $quantity = $get('quantity') ?? 0;
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $salesTaxes = $get('salesTaxes') ?? [];

                                        $total = $quantity * $unitPrice;

                                        if (! empty($salesTaxes)) {
                                            $taxRates = Adjustment::whereIn('id', $salesTaxes)->pluck('rate');

                                            $taxAmount = $taxRates->sum(function ($rate) use ($total) {
                                                return $total * ($rate / 100);
                                            });

                                            $total += $taxAmount;

                                            return money($total, CurrencyAccessor::getDefaultCurrency(), true)->format();
                                        }

                                        return money($total, CurrencyAccessor::getDefaultCurrency(), true)->format();
                                    }),
                            ]),
                        Forms\Components\Grid::make(6)
                            ->inlineLabel()
                            ->extraAttributes([
                                'class' => 'text-right pr-16',
                            ])
                            ->schema([
                                Forms\Components\Group::make([
                                    Forms\Components\Placeholder::make('subtotal')
                                        ->label('Subtotal')
                                        ->content(function (Forms\Get $get) {
                                            $lineItems = $get('lineItems');

                                            $subtotal = collect($lineItems)
                                                ->sum(fn ($item) => ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0));

                                            return money($subtotal, CurrencyAccessor::getDefaultCurrency(), true)->format();
                                        }),
                                    Forms\Components\Placeholder::make('tax_total')
                                        ->label('Taxes')
                                        ->content(function (Forms\Get $get) {
                                            $lineItems = $get('lineItems');

                                            $totalTaxes = collect($lineItems)->reduce(function ($carry, $item) {
                                                $quantity = $item['quantity'] ?? 0;
                                                $unitPrice = $item['unit_price'] ?? 0;
                                                $salesTaxes = $item['salesTaxes'] ?? [];
                                                $lineTotal = $quantity * $unitPrice;

                                                $taxAmount = Adjustment::whereIn('id', $salesTaxes)
                                                    ->pluck('rate')
                                                    ->sum(fn ($rate) => $lineTotal * ($rate / 100));

                                                return $carry + $taxAmount;
                                            }, 0);

                                            return money($totalTaxes, CurrencyAccessor::getDefaultCurrency(), true)->format();
                                        }),
                                    Forms\Components\Placeholder::make('total')
                                        ->label('Total')
                                        ->content(function (Forms\Get $get) {
                                            $lineItems = $get('lineItems') ?? [];

                                            $subtotal = collect($lineItems)
                                                ->sum(fn ($item) => ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0));

                                            $totalTaxes = collect($lineItems)->reduce(function ($carry, $item) {
                                                $quantity = $item['quantity'] ?? 0;
                                                $unitPrice = $item['unit_price'] ?? 0;
                                                $salesTaxes = $item['salesTaxes'] ?? [];
                                                $lineTotal = $quantity * $unitPrice;

                                                $taxAmount = Adjustment::whereIn('id', $salesTaxes)
                                                    ->pluck('rate')
                                                    ->sum(fn ($rate) => $lineTotal * ($rate / 100));

                                                return $carry + $taxAmount;
                                            }, 0);

                                            $grandTotal = $subtotal + $totalTaxes;

                                            return money($grandTotal, CurrencyAccessor::getDefaultCurrency(), true)->format();
                                        }),
                                ])->columnStart(6),
                            ]),
                        //                        Forms\Components\Repeater::make('lineItems')
                        //                            ->relationship()
                        //                            ->columns(8)
                        //                            ->schema([
                        //                                Forms\Components\Select::make('offering_id')
                        //                                    ->relationship('offering', 'name')
                        //                                    ->preload()
                        //                                    ->columnSpan(2)
                        //                                    ->searchable()
                        //                                    ->required()
                        //                                    ->live()
                        //                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        //                                        $offeringId = $state;
                        //                                        $offeringRecord = Offering::with('salesTaxes')->find($offeringId);
                        //
                        //                                        if ($offeringRecord) {
                        //                                            $set('description', $offeringRecord->description);
                        //                                            $set('unit_price', $offeringRecord->price);
                        //                                            $set('total', $offeringRecord->price);
                        //
                        //                                            $salesTaxes = $offeringRecord->salesTaxes->map(function ($tax) {
                        //                                                return [
                        //                                                    'id' => $tax->id,
                        //                                                    'amount' => null, // Amount will be calculated dynamically
                        //                                                ];
                        //                                            })->toArray();
                        //
                        //                                            $set('taxes', $salesTaxes);
                        //                                        }
                        //                                    }),
                        //                                Forms\Components\TextInput::make('description')
                        //                                    ->columnSpan(3)
                        //                                    ->required(),
                        //                                Forms\Components\TextInput::make('quantity')
                        //                                    ->required()
                        //                                    ->numeric()
                        //                                    ->live()
                        //                                    ->default(1),
                        //                                Forms\Components\TextInput::make('unit_price')
                        //                                    ->live()
                        //                                    ->numeric()
                        //                                    ->default(0),
                        //                                Forms\Components\Placeholder::make('total')
                        //                                    ->content(function (Forms\Get $get) {
                        //                                        $quantity = $get('quantity');
                        //                                        $unitPrice = $get('unit_price');
                        //
                        //                                        if ($quantity && $unitPrice) {
                        //                                            return $quantity * $unitPrice;
                        //                                        }
                        //                                    }),
                        //                                TableRepeater::make('taxes')
                        //                                    ->relationship()
                        //                                    ->columnSpanFull()
                        //                                    ->columnStart(6)
                        //                                    ->headers([
                        //                                        Header::make('')->width('200px'),
                        //                                        Header::make('')->width('50px')->align('right'),
                        //                                    ])
                        //                                    ->defaultItems(0)
                        //                                    ->schema([
                        //                                        Forms\Components\Select::make('id') // The ID of the adjustment being attached.
                        //                                            ->label('Tax Adjustment')
                        //                                            ->options(
                        //                                                Adjustment::query()
                        //                                                    ->where('category', AdjustmentCategory::Tax)
                        //                                                    ->pluck('name', 'id')
                        //                                            )
                        //                                            ->preload()
                        //                                            ->searchable()
                        //                                            ->required()
                        //                                            ->live(),
                        //                                        Forms\Components\Placeholder::make('amount')
                        //                                            ->hiddenLabel()
                        //                                            ->content(function (Forms\Get $get) {
                        //                                                $quantity = $get('../../quantity') ?? 0; // Get parent quantity
                        //                                                $unitPrice = $get('../../unit_price') ?? 0; // Get parent unit price
                        //                                                $rate = Adjustment::find($get('id'))->rate ?? 0;
                        //
                        //                                                $total = $quantity * $unitPrice;
                        //
                        //                                                return $total * ($rate / 100);
                        //                                            }),
                        //                                    ]),
                        //                            ]),
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
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->money(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->money(),
                Tables\Columns\TextColumn::make('amount_due')
                    ->money(),
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
