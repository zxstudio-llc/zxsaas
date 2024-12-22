<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\EstimateStatus;
use App\Filament\Company\Resources\Sales\EstimateResource\Pages;
use App\Filament\Company\Resources\Sales\EstimateResource\Widgets;
use App\Filament\Forms\Components\CreateCurrencySelect;
use App\Filament\Forms\Components\DocumentTotals;
use App\Filament\Tables\Actions\ReplicateBulkAction;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Estimate;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EstimateResource extends Resource
{
    protected static ?string $model = Estimate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;

        return $form
            ->schema([
                Forms\Components\Section::make('Estimate Header')
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
                                    ->default('Estimate'),
                                Forms\Components\TextInput::make('subheader'),
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
                Forms\Components\Section::make('Estimate Details')
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
                                Forms\Components\TextInput::make('estimate_number')
                                    ->label('Estimate Number')
                                    ->default(fn () => Estimate::getNextDocumentNumber()),
                                Forms\Components\TextInput::make('reference_number')
                                    ->label('P.O/S.O Number'),
                                Forms\Components\DatePicker::make('date')
                                    ->label('Estimate Date')
                                    ->live()
                                    ->default(now())
                                    ->disabled(function (?Estimate $record) {
                                        return $record?->hasPayments();
                                    })
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        $date = $state;
                                        $dueDate = $get('expiration_date');

                                        if ($date && $dueDate && $date > $dueDate) {
                                            $set('expiration_date', $date);
                                        }
                                    }),
                                Forms\Components\DatePicker::make('expiration_date')
                                    ->label('Payment Due')
                                    ->default(function () use ($company) {
                                        return now()->addDays($company->defaultEstimate->payment_terms->getDays());
                                    })
                                    ->minDate(static function (Forms\Get $get) {
                                        return $get('date') ?? now();
                                    }),
                                Forms\Components\Select::make('discount_method')
                                    ->label('Discount Method')
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
                            ->type(DocumentType::Estimate),
                        Forms\Components\Textarea::make('terms')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Estimate Footer')
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
            ->defaultSort('expiration_date')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiration_date')
                    ->label('Due')
                    ->asRelativeDay()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimate_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                    ->currencyWithConversion(static fn (Estimate $record) => $record->currency_code)
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(EstimateStatus::class)
                    ->native(false),
                DateRangeFilter::make('date')
                    ->fromLabel('From Date')
                    ->untilLabel('To Date')
                    ->indicatorLabel('Date'),
                DateRangeFilter::make('expiration_date')
                    ->fromLabel('From Expiration Date')
                    ->untilLabel('To Expiration Date')
                    ->indicatorLabel('Due'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    //                    Estimate::getReplicateAction(Tables\Actions\ReplicateAction::class),
                    //                    Estimate::getApproveDraftAction(Tables\Actions\Action::class),
                    //                    Estimate::getMarkAsSentAction(Tables\Actions\Action::class),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ReplicateBulkAction::make()
                        ->label('Replicate')
                        ->modalWidth(MaxWidth::Large)
                        ->modalDescription('Replicating estimates will also replicate their line items. Are you sure you want to proceed?')
                        ->successNotificationTitle('Estimates Replicated Successfully')
                        ->failureNotificationTitle('Failed to Replicate Estimates')
                        ->databaseTransaction()
                        ->deselectRecordsAfterCompletion()
                        ->excludeAttributes([
                            'status',
                            'amount_paid',
                            'amount_due',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                            'estimate_number',
                            'date',
                            'expiration_date',
                        ])
                        ->beforeReplicaSaved(function (Estimate $replica) {
                            $replica->status = EstimateStatus::Draft;
                            $replica->estimate_number = Estimate::getNextDocumentNumber();
                            $replica->date = now();
                            $replica->expiration_date = now()->addDays($replica->company->defaultEstimate->payment_terms->getDays());
                        })
                        ->withReplicatedRelationships(['lineItems'])
                        ->withExcludedRelationshipAttributes('lineItems', [
                            'subtotal',
                            'total',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                        ]),
                    Tables\Actions\BulkAction::make('approveDrafts')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates Approved')
                        ->failureNotificationTitle('Failed to Approve Estimates')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $containsNonDrafts = $records->contains(fn (Estimate $record) => ! $record->isDraft());

                            if ($containsNonDrafts) {
                                Notification::make()
                                    ->title('Approval Failed')
                                    ->body('Only draft estimates can be approved. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->approveDraft();
                            });

                            $action->success();
                        }),
                    Tables\Actions\BulkAction::make('markAsSent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates Sent')
                        ->failureNotificationTitle('Failed to Mark Estimates as Sent')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $doesntContainUnsent = $records->contains(fn (Estimate $record) => $record->status !== EstimateStatus::Unsent);

                            if ($doesntContainUnsent) {
                                Notification::make()
                                    ->title('Sending Failed')
                                    ->body('Only unsent estimates can be marked as sent. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->updateQuietly([
                                    'status' => EstimateStatus::Sent,
                                ]);
                            });

                            $action->success();
                        }),
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
            'index' => Pages\ListEstimates::route('/'),
            'create' => Pages\CreateEstimate::route('/create'),
            'view' => Pages\ViewEstimate::route('/{record}'),
            'edit' => Pages\EditEstimate::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\EstimateOverview::class,
        ];
    }
}
