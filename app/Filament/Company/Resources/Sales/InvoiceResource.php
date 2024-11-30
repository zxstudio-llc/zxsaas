<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\InvoiceStatus;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Banking\BankAccount;
use App\Models\Common\Offering;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Carbon\CarbonInterface;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

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
                                Forms\Components\TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->default(fn () => Invoice::getNextDocumentNumber()),
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

                                /** @var Invoice $invoice */
                                $invoice = $component->getRecord();

                                // Recalculate totals for line items
                                $invoice->lineItems()->each(function (DocumentLineItem $lineItem) {
                                    $lineItem->updateQuietly([
                                        'tax_total' => $lineItem->calculateTaxTotal()->getAmount(),
                                        'discount_total' => $lineItem->calculateDiscountTotal()->getAmount(),
                                    ]);
                                });

                                $subtotal = $invoice->lineItems()->sum('subtotal') / 100;
                                $taxTotal = $invoice->lineItems()->sum('tax_total') / 100;
                                $discountTotal = $invoice->lineItems()->sum('discount_total') / 100;
                                $grandTotal = $subtotal + $taxTotal - $discountTotal;

                                $invoice->updateQuietly([
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
                                    ->relationship('sellableOffering', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        $offeringId = $state;
                                        $offeringRecord = Offering::with('salesTaxes')->find($offeringId);

                                        if ($offeringRecord) {
                                            $set('description', $offeringRecord->description);
                                            $set('unit_price', $offeringRecord->price);
                                            $set('salesTaxes', $offeringRecord->salesTaxes->pluck('id')->toArray());
                                            $set('salesDiscounts', $offeringRecord->salesDiscounts->pluck('id')->toArray());
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
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->searchable(),
                                Forms\Components\Select::make('salesDiscounts')
                                    ->relationship('salesDiscounts', 'name')
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->searchable(),
                                Forms\Components\Placeholder::make('total')
                                    ->hiddenLabel()
                                    ->content(function (Forms\Get $get) {
                                        $quantity = max((float) ($get('quantity') ?? 0), 0);
                                        $unitPrice = max((float) ($get('unit_price') ?? 0), 0);
                                        $salesTaxes = $get('salesTaxes') ?? [];
                                        $salesDiscounts = $get('salesDiscounts') ?? [];

                                        $subtotal = $quantity * $unitPrice;

                                        // Calculate tax amount based on subtotal
                                        $taxAmount = 0;
                                        if (! empty($salesTaxes)) {
                                            $taxRates = Adjustment::whereIn('id', $salesTaxes)->pluck('rate');
                                            $taxAmount = collect($taxRates)->sum(fn ($rate) => $subtotal * ($rate / 100));
                                        }

                                        // Calculate discount amount based on subtotal
                                        $discountAmount = 0;
                                        if (! empty($salesDiscounts)) {
                                            $discountRates = Adjustment::whereIn('id', $salesDiscounts)->pluck('rate');
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
                                    ->view('filament.forms.components.invoice-totals'),
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
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->currency(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount Paid')
                    ->currency(),
                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->currency(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\ReplicateAction::make()
                        ->label('Duplicate')
                        ->excludeAttributes(['status', 'amount_paid', 'amount_due', 'created_by', 'updated_by', 'created_at', 'updated_at', 'invoice_number', 'date', 'due_date'])
                        ->modal(false)
                        ->beforeReplicaSaved(function (Invoice $original, Invoice $replica) {
                            $replica->status = InvoiceStatus::Draft;
                            $replica->invoice_number = Invoice::getNextDocumentNumber();
                            $replica->date = now();
                            $replica->due_date = now()->addDays($original->company->defaultInvoice->payment_terms->getDays());
                        })
                        ->after(function (Invoice $original, Invoice $replica) {
                            $original->lineItems->each(function (DocumentLineItem $lineItem) use ($replica) {
                                $replicaLineItem = $lineItem->replicate([
                                    'documentable_id',
                                    'documentable_type',
                                    'subtotal',
                                    'total',
                                    'created_by',
                                    'updated_by',
                                    'created_at',
                                    'updated_at',
                                ]);

                                $replicaLineItem->documentable_id = $replica->id;
                                $replicaLineItem->documentable_type = $replica->getMorphClass();

                                $replicaLineItem->save();

                                $replicaLineItem->adjustments()->sync($lineItem->adjustments->pluck('id'));
                            });
                        })
                        ->successRedirectUrl(function (Invoice $replica) {
                            return InvoiceResource::getUrl('edit', ['record' => $replica]);
                        }),
                    Tables\Actions\Action::make('approveDraft')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->visible(function (Invoice $record) {
                            return $record->isDraft();
                        })
                        ->action(function (Invoice $record) {
                            $record->updateQuietly([
                                'status' => InvoiceStatus::Unsent,
                            ]);
                        }),
                    Tables\Actions\Action::make('markAsSent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(function (Invoice $record) {
                            return $record->status === InvoiceStatus::Unsent;
                        })
                        ->action(function (Invoice $record) {
                            $record->updateQuietly([
                                'status' => InvoiceStatus::Sent,
                            ]);
                        }),
                    Tables\Actions\Action::make('recordPayment')
                        ->label('Record Payment')
                        ->modalWidth(MaxWidth::TwoExtraLarge)
                        ->icon('heroicon-o-credit-card')
                        ->visible(function (Invoice $record) {
                            return $record->canRecordPayment();
                        })
                        ->mountUsing(function (Invoice $record, Form $form) {
                            $form->fill([
                                'date' => now(),
                                'amount' => $record->amount_due,
                            ]);
                        })
                        ->form([
                            Forms\Components\DatePicker::make('date')
                                ->label('Payment Date'),
                            Forms\Components\TextInput::make('amount')
                                ->label('Amount')
                                ->required()
                                ->money(),
                            Forms\Components\Select::make('payment_method')
                                ->label('Payment Method')
                                ->options([
                                    'bank_payment' => 'Bank Payment',
                                    'cash' => 'Cash',
                                    'check' => 'Check',
                                    'credit_card' => 'Credit Card',
                                    'paypal' => 'PayPal',
                                    'other' => 'Other',
                                ]),
                            Forms\Components\Select::make('bank_account_id')
                                ->label('Account')
                                ->options(BankAccount::query()
                                    ->get()
                                    ->pluck('account.name', 'id'))
                                ->searchable(),
                            Forms\Components\Textarea::make('notes')
                                ->label('Notes'),
                        ])
                        ->action(function (Invoice $record, array $data) {
                            $payment = $record->payments()->create([
                                'date' => $data['date'],
                                'amount' => $data['amount'],
                                'payment_method' => $data['payment_method'],
                                'bank_account_id' => $data['bank_account_id'],
                                'notes' => $data['notes'],
                            ]);

                            $amountPaid = $record->getRawOriginal('amount_paid');
                            $paymentAmount = $payment->getRawOriginal('amount');
                            $totalAmount = $record->getRawOriginal('total');

                            $newAmountPaid = $amountPaid + $paymentAmount;

                            $record->amount_paid = CurrencyConverter::convertCentsToFloat($newAmountPaid);

                            if ($newAmountPaid > $totalAmount) {
                                $record->status = InvoiceStatus::Overpaid;
                            } elseif ($newAmountPaid === $totalAmount) {
                                $record->status = InvoiceStatus::Paid;
                            } else {
                                $record->status = InvoiceStatus::Partial;
                            }

                            $record->save();
                        }),
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
