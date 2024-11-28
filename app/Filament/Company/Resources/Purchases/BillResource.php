<?php

namespace App\Filament\Company\Resources\Purchases;

use App\Filament\Company\Resources\Purchases\BillResource\Pages;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Common\Offering;
use App\Utilities\Currency\CurrencyAccessor;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Carbon\CarbonInterface;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;

        return $form
            ->schema([
                Forms\Components\Section::make('Bill Details')
                    ->schema([
                        Forms\Components\Split::make([
                            Forms\Components\Group::make([
                                Forms\Components\Select::make('vendor_id')
                                    ->relationship('vendor', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->required(),
                            ]),
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('bill_number')
                                    ->label('Bill Number')
                                    ->default(fn () => Bill::getNextDocumentNumber()),
                                Forms\Components\TextInput::make('order_number')
                                    ->label('P.O/S.O Number'),
                                Forms\Components\DatePicker::make('date')
                                    ->label('Bill Date')
                                    ->default(now()),
                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->default(function () use ($company) {
                                        return now()->addDays($company->defaultBill->payment_terms->getDays());
                                    }),
                            ])->grow(true),
                        ])->from('md'),
                        TableRepeater::make('lineItems')
                            ->relationship()
                            ->saveRelationshipsUsing(function (TableRepeater $component, Forms\Contracts\HasForms $livewire, ?array $state) {
                                if (! is_array($state)) {
                                    $state = [];
                                }

                                $relationship = $component->getRelationship();

                                $existingRecords = $component->getCachedExistingRecords();

                                $recordsToDelete = [];

                                foreach ($existingRecords->pluck($relationship->getRelated()->getKeyName()) as $keyToCheckForDeletion) {
                                    if (array_key_exists("record-{$keyToCheckForDeletion}", $state)) {
                                        continue;
                                    }

                                    $recordsToDelete[] = $keyToCheckForDeletion;
                                    $existingRecords->forget("record-{$keyToCheckForDeletion}");
                                }

                                $relationship
                                    ->whereKey($recordsToDelete)
                                    ->get()
                                    ->each(static fn (Model $record) => $record->delete());

                                $childComponentContainers = $component->getChildComponentContainers(
                                    withHidden: $component->shouldSaveRelationshipsWhenHidden(),
                                );

                                $itemOrder = 1;
                                $orderColumn = $component->getOrderColumn();

                                $translatableContentDriver = $livewire->makeFilamentTranslatableContentDriver();

                                foreach ($childComponentContainers as $itemKey => $item) {
                                    $itemData = $item->getState(shouldCallHooksBefore: false);

                                    if ($orderColumn) {
                                        $itemData[$orderColumn] = $itemOrder;

                                        $itemOrder++;
                                    }

                                    if ($record = ($existingRecords[$itemKey] ?? null)) {
                                        $itemData = $component->mutateRelationshipDataBeforeSave($itemData, record: $record);

                                        if ($itemData === null) {
                                            continue;
                                        }

                                        $translatableContentDriver ?
                                            $translatableContentDriver->updateRecord($record, $itemData) :
                                            $record->fill($itemData)->save();

                                        continue;
                                    }

                                    $relatedModel = $component->getRelatedModel();

                                    $itemData = $component->mutateRelationshipDataBeforeCreate($itemData);

                                    if ($itemData === null) {
                                        continue;
                                    }

                                    if ($translatableContentDriver) {
                                        $record = $translatableContentDriver->makeRecord($relatedModel, $itemData);
                                    } else {
                                        $record = new $relatedModel;
                                        $record->fill($itemData);
                                    }

                                    $record = $relationship->save($record);
                                    $item->model($record)->saveRelationships();
                                    $existingRecords->push($record);
                                }

                                $component->getRecord()->setRelation($component->getRelationshipName(), $existingRecords);

                                /** @var Bill $bill */
                                $bill = $component->getRecord();

                                // Recalculate totals for line items
                                $bill->lineItems()->each(function (DocumentLineItem $lineItem) {
                                    $lineItem->updateQuietly([
                                        'tax_total' => $lineItem->calculateTaxTotal()->getAmount(),
                                        'discount_total' => $lineItem->calculateDiscountTotal()->getAmount(),
                                    ]);
                                });

                                $subtotal = $bill->lineItems()->sum('subtotal') / 100;
                                $taxTotal = $bill->lineItems()->sum('tax_total') / 100;
                                $discountTotal = $bill->lineItems()->sum('discount_total') / 100;
                                $grandTotal = $subtotal + $taxTotal - $discountTotal;

                                $bill->updateQuietly([
                                    'subtotal' => $subtotal,
                                    'tax_total' => $taxTotal,
                                    'discount_total' => $discountTotal,
                                    'total' => $grandTotal,
                                ]);
                            })
                            ->headers([
                                Header::make('Items')->width('15%'),
                                Header::make('Description')->width('25%'),
                                Header::make('Quantity')->width('10%'),
                                Header::make('Price')->width('10%'),
                                Header::make('Taxes')->width('15%'),
                                Header::make('Discounts')->width('15%'),
                                Header::make('Amount')->width('10%')->align('right'),
                            ])
                            ->schema([
                                Forms\Components\Select::make('offering_id')
                                    ->relationship('purchasableOffering', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        $offeringId = $state;
                                        $offeringRecord = Offering::with('purchaseTaxes')->find($offeringId);

                                        if ($offeringRecord) {
                                            $set('description', $offeringRecord->description);
                                            $set('unit_price', $offeringRecord->price);
                                            $set('purchaseTaxes', $offeringRecord->purchaseTaxes->pluck('id')->toArray());
                                            $set('purchaseDiscounts', $offeringRecord->purchaseDiscounts->pluck('id')->toArray());
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
                                Forms\Components\Select::make('purchaseTaxes')
                                    ->relationship('purchaseTaxes', 'name')
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->searchable(),
                                Forms\Components\Select::make('purchaseDiscounts')
                                    ->relationship('purchaseDiscounts', 'name')
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->searchable(),
                                Forms\Components\Placeholder::make('total')
                                    ->hiddenLabel()
                                    ->content(function (Forms\Get $get) {
                                        $quantity = max((float) ($get('quantity') ?? 0), 0);
                                        $unitPrice = max((float) ($get('unit_price') ?? 0), 0);
                                        $purchaseTaxes = $get('purchaseTaxes') ?? [];
                                        $purchaseDiscounts = $get('purchaseDiscounts') ?? [];

                                        $subtotal = $quantity * $unitPrice;

                                        // Calculate tax amount based on subtotal
                                        $taxAmount = 0;
                                        if (! empty($purchaseTaxes)) {
                                            $taxRates = Adjustment::whereIn('id', $purchaseTaxes)->pluck('rate');
                                            $taxAmount = collect($taxRates)->sum(fn ($rate) => $subtotal * ($rate / 100));
                                        }

                                        // Calculate discount amount based on subtotal
                                        $discountAmount = 0;
                                        if (! empty($purchaseDiscounts)) {
                                            $discountRates = Adjustment::whereIn('id', $purchaseDiscounts)->pluck('rate');
                                            $discountAmount = collect($discountRates)->sum(fn ($rate) => $subtotal * ($rate / 100));
                                        }

                                        // Final total
                                        $total = $subtotal + ($taxAmount - $discountAmount);

                                        return money($total, CurrencyAccessor::getDefaultCurrency(), true)->format();
                                    }),
                            ]),
                        Forms\Components\Grid::make(6)
                            ->schema([
                                Forms\Components\ViewField::make('totals')
                                    ->columnStart(5)
                                    ->columnSpan(2)
                                    ->view('filament.forms.components.bill-totals'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->formatStateUsing(function (Tables\Columns\TextColumn $column, mixed $state) {
                        if (blank($state)) {
                            return null;
                        }

                        $date = Carbon::parse($state)
                            ->setTimezone($timezone ?? $column->getTimezone());

                        if ($date->isToday()) {
                            return 'Today';
                        }

                        return $date->diffForHumans([
                            'options' => CarbonInterface::ONE_DAY_WORDS,
                        ]);
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bill_number')
                    ->label('Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vendor.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->currency(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->currency(),
                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->currency(),
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
            'index' => Pages\ListBills::route('/'),
            'create' => Pages\CreateBill::route('/create'),
            'edit' => Pages\EditBill::route('/{record}/edit'),
        ];
    }
}
