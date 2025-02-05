<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\RecurringInvoiceStatus;
use App\Enums\Setting\PaymentTerms;
use App\Filament\Company\Resources\Sales\RecurringInvoiceResource\Pages;
use App\Filament\Forms\Components\CreateCurrencySelect;
use App\Filament\Forms\Components\DocumentTotals;
use App\Filament\Tables\Columns;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\RecurringInvoice;
use App\Models\Common\Client;
use App\Models\Common\Offering;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;
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

class RecurringInvoiceResource extends Resource
{
    protected static ?string $model = RecurringInvoice::class;

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;

        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Header')
                    ->collapsible()
                    ->collapsed()
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
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $currencyCode = Client::find($state)?->currency_code;

                                        if ($currencyCode) {
                                            $set('currency_code', $currencyCode);
                                        }
                                    }),
                                CreateCurrencySelect::make('currency_code'),
                            ]),
                            Forms\Components\Group::make([
                                Forms\Components\Placeholder::make('invoice_number')
                                    ->label('Invoice number')
                                    ->content('Auto-generated'),
                                Forms\Components\TextInput::make('order_number')
                                    ->label('P.O/S.O Number'),
                                Forms\Components\Placeholder::make('date')
                                    ->label('Invoice date')
                                    ->content('Auto-generated'),
                                Forms\Components\Select::make('payment_terms')
                                    ->label('Payment due')
                                    ->options(PaymentTerms::class)
                                    ->softRequired()
                                    ->default($company->defaultInvoice->payment_terms)
                                    ->live(),
                                Forms\Components\Select::make('discount_method')
                                    ->label('Discount method')
                                    ->options(DocumentDiscountMethod::class)
                                    ->selectablePlaceholder(false)
                                    ->default(DocumentDiscountMethod::PerLineItem)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $discountMethod = DocumentDiscountMethod::parse($state);

                                        if ($discountMethod->isPerDocument()) {
                                            $set('lineItems.*.salesDiscounts', []);
                                        }
                                    })
                                    ->live(),
                            ])->grow(true),
                        ])->from('md'),
                        TableRepeater::make('lineItems')
                            ->relationship()
                            ->saveRelationshipsUsing(null)
                            ->dehydrated(true)
                            ->headers(function (Forms\Get $get) {
                                $hasDiscounts = DocumentDiscountMethod::parse($get('discount_method'))->isPerLineItem();

                                $headers = [
                                    Header::make('Items')->width($hasDiscounts ? '15%' : '20%'),
                                    Header::make('Description')->width($hasDiscounts ? '25%' : '30%'),  // Increase when no discounts
                                    Header::make('Quantity')->width('10%'),
                                    Header::make('Price')->width('10%'),
                                    Header::make('Taxes')->width($hasDiscounts ? '15%' : '20%'),       // Increase when no discounts
                                ];

                                if ($hasDiscounts) {
                                    $headers[] = Header::make('Discounts')->width('15%');
                                }

                                $headers[] = Header::make('Amount')->width('10%')->align('right');

                                return $headers;
                            })
                            ->schema([
                                Forms\Components\Select::make('offering_id')
                                    ->relationship('sellableOffering', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        $offeringId = $state;
                                        $offeringRecord = Offering::with(['salesTaxes', 'salesDiscounts'])->find($offeringId);

                                        if ($offeringRecord) {
                                            $set('description', $offeringRecord->description);
                                            $set('unit_price', $offeringRecord->price);
                                            $set('salesTaxes', $offeringRecord->salesTaxes->pluck('id')->toArray());

                                            $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));
                                            if ($discountMethod->isPerLineItem()) {
                                                $set('salesDiscounts', $offeringRecord->salesDiscounts->pluck('id')->toArray());
                                            }
                                        }
                                    }),
                                Forms\Components\TextInput::make('description'),
                                Forms\Components\TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->live()
                                    ->default(1),
                                Forms\Components\TextInput::make('unit_price')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->live()
                                    ->default(0),
                                Forms\Components\Select::make('salesTaxes')
                                    ->relationship('salesTaxes', 'name')
                                    ->saveRelationshipsUsing(null)
                                    ->dehydrated(true)
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->searchable(),
                                Forms\Components\Select::make('salesDiscounts')
                                    ->relationship('salesDiscounts', 'name')
                                    ->saveRelationshipsUsing(null)
                                    ->dehydrated(true)
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->hidden(function (Forms\Get $get) {
                                        $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));

                                        return $discountMethod->isPerDocument();
                                    })
                                    ->searchable(),
                                Forms\Components\Placeholder::make('total')
                                    ->hiddenLabel()
                                    ->extraAttributes(['class' => 'text-left sm:text-right'])
                                    ->content(function (Forms\Get $get) {
                                        $quantity = max((float) ($get('quantity') ?? 0), 0);
                                        $unitPrice = max((float) ($get('unit_price') ?? 0), 0);
                                        $salesTaxes = $get('salesTaxes') ?? [];
                                        $salesDiscounts = $get('salesDiscounts') ?? [];
                                        $currencyCode = $get('../../currency_code') ?? CurrencyAccessor::getDefaultCurrency();

                                        $subtotal = $quantity * $unitPrice;

                                        $subtotalInCents = CurrencyConverter::convertToCents($subtotal, $currencyCode);

                                        $taxAmountInCents = Adjustment::whereIn('id', $salesTaxes)
                                            ->get()
                                            ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                                if ($adjustment->computation->isPercentage()) {
                                                    return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                                } else {
                                                    return $adjustment->getRawOriginal('rate');
                                                }
                                            });

                                        $discountAmountInCents = Adjustment::whereIn('id', $salesDiscounts)
                                            ->get()
                                            ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                                if ($adjustment->computation->isPercentage()) {
                                                    return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                                } else {
                                                    return $adjustment->getRawOriginal('rate');
                                                }
                                            });

                                        // Final total
                                        $totalInCents = $subtotalInCents + ($taxAmountInCents - $discountAmountInCents);

                                        return CurrencyConverter::formatCentsToMoney($totalInCents, $currencyCode);
                                    }),
                            ]),
                        DocumentTotals::make()
                            ->type(DocumentType::Invoice),
                        Forms\Components\Textarea::make('terms')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Invoice Footer')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Textarea::make('footer')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('next_date')
            ->columns([
                Columns::id(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('schedule')
                    ->label('Schedule')
                    ->getStateUsing(function (RecurringInvoice $record) {
                        return $record->getScheduleDescription();
                    })
                    ->description(function (RecurringInvoice $record) {
                        return $record->getTimelineDescription();
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->showOnTabs(['draft']),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('First invoice')
                    ->date()
                    ->sortable()
                    ->showOnTabs(['draft']),
                Tables\Columns\TextColumn::make('last_date')
                    ->label('Last invoice')
                    ->date()
                    ->sortable()
                    ->hideOnTabs(['draft']),
                Tables\Columns\TextColumn::make('next_date')
                    ->label('Next invoice')
                    ->date()
                    ->sortable()
                    ->hideOnTabs(['draft']),
                Tables\Columns\TextColumn::make('total')
                    ->currencyWithConversion(static fn (RecurringInvoice $record) => $record->currency_code)
                    ->sortable()
                    ->toggleable()
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(RecurringInvoiceStatus::class)
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ActionGroup::make([
                        Tables\Actions\EditAction::make(),
                        Tables\Actions\ViewAction::make(),
                        RecurringInvoice::getManageScheduleAction(Tables\Actions\Action::class),
                    ])->dropdown(false),
                    Tables\Actions\DeleteAction::make(),
                ]),
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
            'index' => Pages\ListRecurringInvoices::route('/'),
            'create' => Pages\CreateRecurringInvoice::route('/create'),
            'view' => Pages\ViewRecurringInvoice::route('/{record}'),
            'edit' => Pages\EditRecurringInvoice::route('/{record}/edit'),
        ];
    }
}
