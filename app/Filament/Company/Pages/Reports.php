<?php

namespace App\Filament\Company\Pages;

use App\Filament\Company\Pages\Reports\AccountBalances;
use App\Filament\Company\Pages\Reports\AccountTransactions;
use App\Filament\Company\Pages\Reports\BalanceSheet;
use App\Filament\Company\Pages\Reports\CashFlowStatement;
use App\Filament\Company\Pages\Reports\IncomeStatement;
use App\Filament\Company\Pages\Reports\TrialBalance;
use App\Infolists\Components\ReportEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Support\Colors\Color;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'filament.company.pages.reports';

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => request()->routeIs([
                    static::getRouteName(),
                    static::getRouteName() . '.*',
                ]))
                ->sort(static::getNavigationSort())
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->url(static::getNavigationUrl()),
        ];
    }

    public function reportsInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->state([])
            ->schema([
                Section::make('Financial Statements')
                    ->aside()
                    ->description('Key financial statements that provide an overview of your company’s financial health and performance.')
                    ->extraAttributes(['class' => 'es-report-card'])
                    ->schema([
                        ReportEntry::make('income_statement')
                            ->hiddenLabel()
                            ->heading('Income Statement')
                            ->description('Shows revenue, expenses, and net earnings over a period, indicating overall financial performance.')
                            ->icon('heroicon-o-chart-bar')
                            ->iconColor(Color::Indigo)
                            ->url(IncomeStatement::getUrl()),
                        ReportEntry::make('balance_sheet')
                            ->hiddenLabel()
                            ->heading('Balance Sheet')
                            ->description('Displays your company’s assets, liabilities, and equity at a single point in time, showing overall financial health and stability.')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->iconColor(Color::Emerald)
                            ->url(BalanceSheet::getUrl()),
                        ReportEntry::make('cash_flow_statement')
                            ->hiddenLabel()
                            ->heading('Cash Flow Statement')
                            ->description('Tracks cash inflows and outflows, giving insight into liquidity and cash management over a period.')
                            ->icon('heroicon-o-document-currency-dollar')
                            ->iconColor(Color::Cyan)
                            ->url(CashFlowStatement::getUrl()),
                    ]),
                Section::make('Detailed Reports')
                    ->aside()
                    ->description('Detailed reports that provide a comprehensive view of your company’s financial transactions and account balances.')
                    ->extraAttributes(['class' => 'es-report-card'])
                    ->schema([
                        ReportEntry::make('account_balances')
                            ->hiddenLabel()
                            ->heading('Account Balances')
                            ->description('Lists all accounts and their balances, including starting, debit, credit, net movement, and ending balances.')
                            ->icon('heroicon-o-currency-dollar')
                            ->iconColor(Color::Teal)
                            ->url(AccountBalances::getUrl()),
                        ReportEntry::make('trial_balance')
                            ->hiddenLabel()
                            ->heading('Trial Balance')
                            ->description('Summarizes all account debits and credits on a specific date to verify the ledger is balanced.')
                            ->icon('heroicon-o-scale')
                            ->iconColor(Color::Sky)
                            ->url(TrialBalance::getUrl()),
                        ReportEntry::make('account_transactions')
                            ->hiddenLabel()
                            ->heading('Account Transactions')
                            ->description('A record of all transactions, essential for monitoring and reconciling financial activity in the ledger.')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->iconColor(Color::Amber)
                            ->url(AccountTransactions::getUrl()),
                    ]),
            ]);
    }
}
