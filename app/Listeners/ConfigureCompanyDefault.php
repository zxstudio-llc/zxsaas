<?php

namespace App\Listeners;

use App\Enums\Setting\PrimaryColor;
use App\Enums\Setting\RecordsPerPage;
use App\Events\CompanyConfigured;
use App\Services\CompanySettingsService;
use App\Utilities\Currency\ConfigureCurrencies;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Resources\Components\Tab as ResourcesTab;
use Filament\Support\Facades\FilamentColor;
use Filament\Tables\Table;

class ConfigureCompanyDefault
{
    /**
     * Handle the event.
     */
    public function handle(CompanyConfigured $event): void
    {
        $company = $event->company;
        $companyId = $company->id;

        session(['current_company_id' => $companyId]);

        $settings = CompanySettingsService::getSettings($companyId);

        app()->setLocale($settings['default_language']);
        locale_set_default($settings['default_language']);
        config(['app.timezone' => $settings['default_timezone']]);
        date_default_timezone_set($settings['default_timezone']);

        $paginationPageOptions = RecordsPerPage::caseValues();

        Table::configureUsing(static function (Table $table) use ($settings, $paginationPageOptions): void {

            $table
                ->paginationPageOptions($paginationPageOptions)
                ->defaultSort(column: 'id', direction: $settings['default_sort'])
                ->defaultPaginationPageOption($settings['default_pagination_page_option']);
        }, isImportant: true);

        FilamentColor::register([
            'primary' => PrimaryColor::from($settings['default_primary_color'])->getColor(),
        ]);

        Filament::getPanel('company')
            ->font($settings['default_font'])
            ->brandName($company->name);

        DatePicker::configureUsing(static function (DatePicker $component) use ($settings) {
            $component
                ->displayFormat($settings['default_date_format'])
                ->firstDayOfWeek($settings['default_week_start']);
        });

        Tab::configureUsing(static function (Tab $tab) {
            $label = $tab->getLabel();

            if ($label) {
                $translatedLabel = translate($label);

                $tab->label(ucwords($translatedLabel));
            }
        }, isImportant: true);

        Section::configureUsing(static function (Section $section): void {
            $heading = $section->getHeading();

            if ($heading) {
                $translatedHeading = translate($heading);

                $section->heading(ucfirst($translatedHeading));
            }
        }, isImportant: true);

        ResourcesTab::configureUsing(static function (ResourcesTab $tab): void {
            $tab->localizeLabel();
        }, isImportant: true);

        ConfigureCurrencies::syncCurrencies();
    }
}
