<?php

namespace App\Filament\Company\Pages\Accounting;

use App\Enums\Accounting\AccountCategory;
use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\TransactionType;
use App\Enums\DateFormat;
use App\Forms\Components\JournalEntryRepeater;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Banking\BankAccount;
use App\Models\Setting\Localization;
use App\Services\AccountService;
use App\Traits\HasJournalEntryActions;
use Awcodes\TableRepeater\Header;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\StaticAction;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property Form $form
 */
class Transactions extends Page implements HasTable
{
    use HasJournalEntryActions;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.company.pages.accounting.transactions';

    protected static ?string $model = Transaction::class;

    public ?string $bankAccountIdFiltered = 'all';

    protected AccountService $accountService;

    public function boot(AccountService $accountService): void
    {
        $this->accountService = $accountService;
    }

    public static function getModel(): string
    {
        return static::$model;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->buildTransactionAction('addIncome', 'Add Income', TransactionType::Deposit),
            $this->buildTransactionAction('addExpense', 'Add Expense', TransactionType::Withdrawal),
            ActionGroup::make([
                CreateAction::make('addJournalTransaction')
                    ->label('Add Journal Transaction')
                    ->fillForm(fn (): array => $this->getFormDefaultsForType(TransactionType::Journal))
                    ->modalWidth(MaxWidth::Screen)
                    ->model(static::getModel())
                    ->form(fn (Form $form) => $this->journalTransactionForm($form))
                    ->modalSubmitAction(fn (StaticAction $action) => $action->disabled(! $this->isJournalEntryBalanced()))
                    ->groupedIcon(null)
                    ->modalHeading('Journal Entry')
                    ->mutateFormDataUsing(static fn (array $data) => array_merge($data, ['type' => TransactionType::Journal]))
                    ->afterFormFilled(fn () => $this->resetJournalEntryAmounts()),

            ])
                ->label('More')
                ->button()
                ->outlined()
                ->dropdownWidth('max-w-fit')
                ->dropdownPlacement('bottom-end')
                ->icon('heroicon-c-chevron-down')
                ->iconSize(IconSize::Small)
                ->iconPosition(IconPosition::After),
        ];
    }

    protected function getFormDefaultsForType(TransactionType $type): array
    {
        $commonDefaults = [
            'posted_at' => now()->format('Y-m-d'),
        ];

        return match ($type) {
            TransactionType::Deposit, TransactionType::Withdrawal => array_merge($commonDefaults, $this->transactionDefaults($type)),
            TransactionType::Journal => array_merge($commonDefaults, $this->journalEntryDefaults()),
        };
    }

    protected function journalEntryDefaults(): array
    {
        return [
            'journalEntries' => [
                $this->defaultEntry(JournalEntryType::Debit),
                $this->defaultEntry(JournalEntryType::Credit),
            ],
        ];
    }

    protected function defaultEntry(JournalEntryType $journalEntryType): array
    {
        return [
            'type' => $journalEntryType,
            'account_id' => static::getUncategorizedAccountByType($journalEntryType->isDebit() ? TransactionType::Withdrawal : TransactionType::Deposit)?->id,
            'amount' => '0.00',
        ];
    }

    public function buildTransactionAction(string $name, string $label, TransactionType $type): CreateAction
    {
        return CreateAction::make($name)
            ->label($label)
            ->modalWidth(MaxWidth::ThreeExtraLarge)
            ->model(static::getModel())
            ->fillForm(fn (): array => $this->getFormDefaultsForType($type))
            ->form(fn (Form $form) => $this->transactionForm($form))
            ->button()
            ->outlined();
    }

    protected function transactionDefaults(TransactionType $type): array
    {
        return [
            'type' => $type,
            'bank_account_id' => BankAccount::where('enabled', true)->first()?->id,
            'amount' => '0.00',
            'account_id' => static::getUncategorizedAccountByType($type)?->id,
        ];
    }

    public static function getUncategorizedAccountByType(TransactionType $type): ?Account
    {
        [$category, $accountName] = match ($type) {
            TransactionType::Deposit => [AccountCategory::Revenue, 'Uncategorized Income'],
            TransactionType::Withdrawal => [AccountCategory::Expense, 'Uncategorized Expense'],
            default => [null, null],
        };

        return Account::where('category', $category)
            ->where('name', $accountName)
            ->first();
    }

    public function transactionForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('posted_at')
                    ->label('Date')
                    ->required()
                    ->displayFormat('Y-m-d'),
                Forms\Components\TextInput::make('description')
                    ->label('Description'),
                Forms\Components\Select::make('bank_account_id')
                    ->label('Account')
                    ->options(fn () => $this->getBankAccountOptions())
                    ->live()
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->live()
                    ->options([
                        TransactionType::Deposit->value => TransactionType::Deposit->getLabel(),
                        TransactionType::Withdrawal->value => TransactionType::Withdrawal->getLabel(),
                    ])
                    ->required()
                    ->afterStateUpdated(static fn (Forms\Set $set, $state) => $set('account_id', static::getUncategorizedAccountByType(TransactionType::parse($state))?->id)),
                Forms\Components\TextInput::make('amount')
                    ->label('Amount')
                    ->money(static fn (Forms\Get $get) => BankAccount::find($get('bank_account_id'))?->account?->currency_code ?? 'USD')
                    ->required(),
                Forms\Components\Select::make('account_id')
                    ->label('Category')
                    ->options(fn (Forms\Get $get) => $this->getChartAccountOptions(type: TransactionType::parse($get('type')), nominalAccountsOnly: true))
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->autosize()
                    ->rows(10)
                    ->columnSpanFull(),
            ])
            ->columns();
    }

    public function journalTransactionForm(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->contained(false)
                    ->tabs([
                        $this->getJournalTransactionFormEditTab(),
                        $this->getJournalTransactionFormNotesTab(),
                    ]),
            ])
            ->columns(1);
    }

    protected function getJournalTransactionFormEditTab(): Tab
    {
        return Tab::make('Edit')
            ->label('Edit')
            ->icon('heroicon-o-pencil-square')
            ->schema([
                $this->getTransactionDetailsGrid(),
                $this->getJournalEntriesTableRepeater(),
            ]);
    }

    protected function getJournalTransactionFormNotesTab(): Tab
    {
        return Tab::make('Notes')
            ->label('Notes')
            ->icon('heroicon-o-clipboard')
            ->id('notes')
            ->schema([
                $this->getTransactionDetailsGrid(),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(10)
                    ->autosize(),
            ]);
    }

    protected function getTransactionDetailsGrid(): Grid
    {
        return Grid::make(8)
            ->schema([
                DatePicker::make('posted_at')
                    ->label('Date')
                    ->softRequired()
                    ->displayFormat('Y-m-d'),
                TextInput::make('description')
                    ->label('Description')
                    ->columnSpan(2),
            ]);
    }

    protected function getJournalEntriesTableRepeater(): JournalEntryRepeater
    {
        return JournalEntryRepeater::make('journalEntries')
            ->relationship('journalEntries')
            ->hiddenLabel()
            ->columns(4)
            ->headers($this->getJournalEntriesTableRepeaterHeaders())
            ->schema($this->getJournalEntriesTableRepeaterSchema())
            ->streamlined()
            ->deletable(fn (JournalEntryRepeater $repeater) => $repeater->getItemsCount() > 2)
            ->minItems(2)
            ->defaultItems(2)
            ->addable(false)
            ->footerItem(fn (): View => $this->getJournalTransactionModalFooter())
            ->extraActions([
                $this->buildAddJournalEntryAction(JournalEntryType::Debit),
                $this->buildAddJournalEntryAction(JournalEntryType::Credit),
            ]);
    }

    protected function getJournalEntriesTableRepeaterHeaders(): array
    {
        return [
            Header::make('type')
                ->width('150px')
                ->label('Type'),
            Header::make('description')
                ->width('320px')
                ->label('Description'),
            Header::make('account_id')
                ->width('320px')
                ->label('Account'),
            Header::make('amount')
                ->width('192px')
                ->label('Amount'),
        ];
    }

    protected function getJournalEntriesTableRepeaterSchema(): array
    {
        return [
            Select::make('type')
                ->label('Type')
                ->options(JournalEntryType::class)
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?string $old) {
                    $this->adjustJournalEntryAmountsForTypeChange(JournalEntryType::parse($state), JournalEntryType::parse($old), $get('amount'));
                })
                ->softRequired(),
            TextInput::make('description')
                ->label('Description'),
            Select::make('account_id')
                ->label('Account')
                ->options(fn (): array => $this->getChartAccountOptions())
                ->live()
                ->softRequired()
                ->searchable(),
            TextInput::make('amount')
                ->label('Amount')
                ->live()
                ->mask(RawJs::make('$money($input)'))
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?string $old) {
                    $this->updateJournalEntryAmount(JournalEntryType::parse($get('type')), $state, $old);
                })
                ->softRequired(),
        ];
    }

    protected function buildAddJournalEntryAction(JournalEntryType $type): Action
    {
        $typeLabel = $type->getLabel();

        return Action::make("add{$typeLabel}Entry")
            ->label("Add {$typeLabel} Entry")
            ->button()
            ->outlined()
            ->color($type->isDebit() ? 'primary' : 'gray')
            ->iconSize(IconSize::Small)
            ->iconPosition(IconPosition::Before)
            ->action(function (JournalEntryRepeater $component) use ($type) {
                $state = $component->getState();
                $newUuid = (string) Str::uuid();
                $state[$newUuid] = $this->defaultEntry($type);

                $component->state($state);
            });
    }

    public function getJournalTransactionModalFooter(): View
    {
        return view(
            'filament.company.components.actions.journal-entry-footer',
            [
                'debitAmount' => $this->getFormattedDebitAmount(),
                'creditAmount' => $this->getFormattedCreditAmount(),
                'difference' => $this->getFormattedBalanceDifference(),
                'isJournalBalanced' => $this->isJournalEntryBalanced(),
            ],
        );
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('bankAccountIdFiltered')
                    ->label('Account')
                    ->hiddenLabel()
                    ->allowHtml()
                    ->options($this->getBankAccountOptions(true, true))
                    ->live()
                    ->selectablePlaceholder(false)
                    ->columnSpan(4),
            ])
            ->columns(14);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::getEloquentQuery())
            ->modifyQueryUsing(function (Builder $query) {
                if ($this->bankAccountIdFiltered !== 'all') {
                    $query->where('bank_account_id', $this->bankAccountIdFiltered);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('posted_at')
                    ->label('Date')
                    ->sortable()
                    ->formatStateUsing(static function ($state) {
                        $dateFormat = Localization::firstOrFail()->date_format->value ?? DateFormat::DEFAULT;

                        return Carbon::parse($state)->translatedFormat($dateFormat);
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->label('Description'),
                Tables\Columns\TextColumn::make('bankAccount.account.name')
                    ->label('Account'),
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Category')
                    ->state(static fn (Transaction $record) => $record->account->name ?? 'Journal Entry'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->weight(static fn (Transaction $record) => $record->reviewed ? null : FontWeight::SemiBold)
                    ->color(
                        static fn (Transaction $record) => match ($record->type) {
                            TransactionType::Deposit => Color::rgb('rgb(' . Color::Green[700] . ')'),
                            TransactionType::Journal => 'primary',
                            default => null,
                        }
                    )
                    ->currency(static fn (Transaction $record) => $record->bankAccount->account->currency_code ?? 'USD', true)
                    ->state(fn (Transaction $record) => $record->type === TransactionType::Journal ? $record->journalEntries->first()->amount : $record->amount),
            ])
            ->recordClasses(static fn (Transaction $record) => $record->reviewed ? 'bg-primary-300/10' : null)
            ->defaultSort('posted_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('filters')
                    ->columnSpanFull()
                    ->form([
                        Grid::make()
                            ->schema([
                                Select::make('account_id')
                                    ->label('Category')
                                    ->options(fn () => $this->getChartAccountOptions(nominalAccountsOnly: true))
                                    ->multiple()
                                    ->searchable(),
                                Select::make('reviewed')
                                    ->label('Status')
                                    ->native(false)
                                    ->options([
                                        '1' => 'Reviewed',
                                        '0' => 'Not Reviewed',
                                    ]),
                                Select::make('type')
                                    ->label('Type')
                                    ->options(TransactionType::class)
                                    ->multiple(),
                            ])
                            ->extraAttributes([
                                'class' => 'border-b border-gray-200 dark:border-white/10 pb-8',
                            ]),
                        Grid::make()
                            ->schema([
                                Select::make('posted_at_date_range')
                                    ->label('Posted Date')
                                    ->placeholder('Select a date range')
                                    ->options([
                                        'all' => 'All Dates', // Handle this later
                                        'custom' => 'Custom Date Range',
                                    ]),
                                DatePicker::make('posted_at_start_date')
                                    ->label('Posted From')
                                    ->displayFormat('Y-m-d')
                                    ->columnStart(1),
                                DatePicker::make('posted_at_end_date')
                                    ->label('Posted To')
                                    ->displayFormat('Y-m-d'),
                                TextInput::make('posted_at_combined_dates')
                                    ->hidden(),
                            ])
                            ->extraAttributes([
                                'class' => 'border-b border-gray-200 dark:border-white/10 pb-8',
                            ]),
                        Grid::make()
                            ->schema([
                                Select::make('updated_at_date_range')
                                    ->label('Last Modified Date')
                                    ->placeholder('Select a date range')
                                    ->options([
                                        'all' => 'All Dates', // Handle this later
                                        'custom' => 'Custom Date Range',
                                    ]),
                                DatePicker::make('updated_at_start_date')
                                    ->label('Last Modified From')
                                    ->displayFormat('Y-m-d')
                                    ->columnStart(1),
                                DatePicker::make('updated_at_end_date')
                                    ->label('Last Modified To')
                                    ->displayFormat('Y-m-d'),
                                TextInput::make('updated_at_combined_dates')
                                    ->label('Updated Date Range')
                                    ->hidden(),
                            ]),
                    ])->query(function (Builder $query, array $data): Builder {
                        if (filled($data['reviewed'])) {
                            $reviewedStatus = $data['reviewed'] === '1';
                            $query->where('reviewed', $reviewedStatus);
                        }

                        $query
                            ->when($data['account_id'], fn (Builder $query, $accountIds) => $query->whereIn('account_id', $accountIds))
                            ->when($data['type'], fn (Builder $query, $types) => $query->whereIn('type', $types))
                            ->when($data['posted_at_start_date'], fn (Builder $query, $startDate) => $query->whereDate('posted_at', '>=', $startDate))
                            ->when($data['posted_at_end_date'], fn (Builder $query, $endDate) => $query->whereDate('posted_at', '<=', $endDate))
                            ->when($data['updated_at_start_date'], fn (Builder $query, $startDate) => $query->whereDate('updated_at', '>=', $startDate))
                            ->when($data['updated_at_end_date'], fn (Builder $query, $endDate) => $query->whereDate('updated_at', '<=', $endDate));

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        $this->addIndicatorForSingleSelection($data, 'reviewed', $data['reviewed'] === '1' ? 'Reviewed' : 'Not Reviewed', $indicators);
                        $this->addMultipleSelectionIndicator($data, 'account_id', fn ($accountId) => Account::find($accountId)->name, 'account_id', $indicators);
                        $this->addMultipleSelectionIndicator($data, 'type', fn ($type) => TransactionType::parse($type)->getLabel(), 'type', $indicators);
                        $this->addIndicatorForDateRange($data, 'posted_at_start_date', 'posted_at_end_date', 'Posted', 'posted_at_combined_dates', $indicators);
                        $this->addIndicatorForDateRange($data, 'updated_at_start_date', 'updated_at_end_date', 'Last Modified', 'updated_at_combined_dates', $indicators);

                        return $indicators;
                    }),
            ], layout: Tables\Enums\FiltersLayout::Modal)
            ->deferFilters()
            ->filtersFormColumns(2)
            ->filtersTriggerAction(
                fn (Tables\Actions\Action $action) => $action
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalCancelAction(false)
                    ->extraModalFooterActions(function (Table $table) use ($action) {
                        return [
                            $table->getFiltersApplyAction()
                                ->close(),
                            StaticAction::make('cancel')
                                ->label($action->getModalCancelActionLabel())
                                ->button()
                                ->close()
                                ->color('gray'),
                            Tables\Actions\Action::make('resetFilters')
                                ->label(__('Clear All'))
                                ->color('primary')
                                ->link()
                                ->extraAttributes([
                                    'class' => 'me-auto',
                                ])
                                ->action('resetTableFiltersForm'),
                        ];
                    })
            )
            ->actions([
                Tables\Actions\Action::make('markAsReviewed')
                    ->label('Mark as Reviewed')
                    ->view('filament.company.components.tables.actions.mark-as-reviewed')
                    ->icon(static fn (Transaction $record) => $record->reviewed ? 'heroicon-s-check-circle' : 'heroicon-o-check-circle')
                    ->color(static fn (Transaction $record, Tables\Actions\Action $action) => match (static::determineTransactionState($record, $action)) {
                        'reviewed' => 'primary',
                        'unreviewed' => Color::rgb('rgb(' . Color::Gray[600] . ')'),
                        'uncategorized' => 'gray',
                    })
                    ->tooltip(static fn (Transaction $record, Tables\Actions\Action $action) => match (static::determineTransactionState($record, $action)) {
                        'reviewed' => 'Reviewed',
                        'unreviewed' => 'Mark as Reviewed',
                        'uncategorized' => 'Categorize first to mark as reviewed',
                    })
                    ->disabled(fn (Transaction $record): bool => $record->isUncategorized())
                    ->action(fn (Transaction $record) => $record->update(['reviewed' => ! $record->reviewed])),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make('updateTransaction')
                        ->label('Edit Transaction')
                        ->modalHeading('Edit Transaction')
                        ->modalWidth(MaxWidth::ThreeExtraLarge)
                        ->form(fn (Form $form) => $this->transactionForm($form))
                        ->hidden(static fn (Transaction $record) => $record->type === TransactionType::Journal),
                    Tables\Actions\EditAction::make('updateJournalTransaction')
                        ->label('Edit Journal Transaction')
                        ->modalHeading('Journal Entry')
                        ->modalWidth(MaxWidth::Screen)
                        ->form(fn (Form $form) => $this->journalTransactionForm($form))
                        ->afterFormFilled(function (Transaction $record) {
                            $debitAmounts = $record->journalEntries->where('type', JournalEntryType::Debit)->sum('amount');
                            $creditAmounts = $record->journalEntries->where('type', JournalEntryType::Credit)->sum('amount');

                            $this->setDebitAmount($debitAmounts);
                            $this->setCreditAmount($creditAmounts);
                        })
                        ->hidden(static fn (Transaction $record) => $record->type !== TransactionType::Journal),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\ReplicateAction::make()
                        ->excludeAttributes(['created_by', 'updated_by', 'created_at', 'updated_at'])
                        ->modal(false)
                        ->beforeReplicaSaved(static function (Transaction $replica) {
                            $replica->description = '(Copy of) ' . $replica->description;
                        }),
                ])
                    ->dropdownPlacement('bottom-start')
                    ->dropdownWidth('max-w-fit'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function addIndicatorForSingleSelection($data, $key, $label, &$indicators): void
    {
        if (filled($data[$key])) {
            $indicators[] = Tables\Filters\Indicator::make($label)
                ->removeField($key);
        }
    }

    protected function addMultipleSelectionIndicator($data, $key, callable $labelRetriever, $field, &$indicators): void
    {
        if (filled($data[$key])) {
            $labels = collect($data[$key])->map($labelRetriever);
            $additionalCount = $labels->count() - 1;
            $indicatorLabel = $additionalCount > 0 ? "{$labels->first()} + {$additionalCount}" : $labels->first();
            $indicators[] = Tables\Filters\Indicator::make($indicatorLabel)
                ->removeField($field);
        }
    }

    protected function addIndicatorForDateRange($data, $startKey, $endKey, $labelPrefix, $combinedFieldKey, &$indicators): void
    {
        $formattedStartDate = filled($data[$startKey]) ? Carbon::parse($data[$startKey])->toFormattedDateString() : null;
        $formattedEndDate = filled($data[$endKey]) ? Carbon::parse($data[$endKey])->toFormattedDateString() : null;
        if ($formattedStartDate && $formattedEndDate) {
            $indicators[] = Tables\Filters\Indicator::make("{$labelPrefix}: {$formattedStartDate} - {$formattedEndDate}")
                ->removeField($combinedFieldKey); // Associate with the hidden combined_dates field for removal
        } else {
            if ($formattedStartDate) {
                $indicators[] = Tables\Filters\Indicator::make("{$labelPrefix} After: {$formattedStartDate}")
                    ->removeField($startKey);
            }

            if ($formattedEndDate) {
                $indicators[] = Tables\Filters\Indicator::make("{$labelPrefix} Before: {$formattedEndDate}")
                    ->removeField($endKey);
            }
        }
    }

    protected static function determineTransactionState(Transaction $record, Tables\Actions\Action $action): string
    {
        if ($record->reviewed) {
            return 'reviewed';
        }

        if ($record->reviewed === false && $action->isEnabled()) {
            return 'unreviewed';
        }

        return 'uncategorized';
    }

    protected function getChartAccountOptions(?TransactionType $type = null, bool $nominalAccountsOnly = false): array
    {
        $excludedCategory = match ($type) {
            TransactionType::Deposit => AccountCategory::Expense,
            TransactionType::Withdrawal => AccountCategory::Revenue,
            default => null,
        };

        return Account::query()
            ->when($nominalAccountsOnly, fn (Builder $query) => $query->whereNull('accountable_type'))
            ->when($excludedCategory, fn (Builder $query) => $query->whereNot('category', $excludedCategory))
            ->get()
            ->groupBy(fn (Account $account) => $account->category->getPluralLabel())
            ->map(fn (Collection $accounts, string $category) => $accounts->pluck('name', 'id'))
            ->toArray();
    }

    protected function getBankAccountOptions(?bool $onlyWithTransactions = null, bool $isFilter = false): array
    {
        $onlyWithTransactions ??= false;

        $options = $isFilter ? [
            '' => ['all' => "All Accounts <span class='float-right'>{$this->getBalanceForAllAccounts()}</span>"],
        ] : [];

        $bankAccountOptions = BankAccount::with('account.subtype')
            ->when($onlyWithTransactions, fn (Builder $query) => $query->has('transactions'))
            ->get()
            ->groupBy('account.subtype.name')
            ->mapWithKeys(function (Collection $bankAccounts, string $subtype) use ($isFilter) {
                return [$subtype => $bankAccounts->mapWithKeys(function (BankAccount $bankAccount) use ($isFilter) {
                    $label = $bankAccount->account->name;
                    if ($isFilter) {
                        $balance = $this->getAccountBalance($bankAccount->account);
                        $label .= "<span class='float-right'>{$balance}</span>";
                    }

                    return [$bankAccount->id => $label];
                })];
            })
            ->toArray();

        return array_merge($options, $bankAccountOptions);
    }

    public function getAccountBalance(Account $account): ?string
    {
        $company = $account->company;
        $startDate = $company->locale->fiscalYearStartDate();
        $endDate = $company->locale->fiscalYearEndDate();

        return $this->accountService->getEndingBalance($account, $startDate, $endDate)?->formatted();
    }

    public function getBalanceForAllAccounts(): string
    {
        $company = auth()->user()->currentCompany;
        $startDate = $company->locale->fiscalYearStartDate();
        $endDate = $company->locale->fiscalYearEndDate();

        return $this->accountService->getTotalBalanceForAllBankAccount($startDate, $endDate)->formatted();
    }
}
