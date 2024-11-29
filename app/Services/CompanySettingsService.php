<?php

namespace App\Services;

use App\Enums\Setting\DateFormat;
use App\Enums\Setting\Font;
use App\Enums\Setting\PrimaryColor;
use App\Enums\Setting\RecordsPerPage;
use App\Enums\Setting\TableSortDirection;
use App\Enums\Setting\WeekStart;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;

class CompanySettingsService
{
    public static function getSettings(int $companyId): array
    {
        $cacheKey = "company_settings_{$companyId}";

        return Cache::rememberForever($cacheKey, function () use ($companyId) {
            $company = Company::with(['locale', 'appearance'])->find($companyId);

            if (! $company) {
                return self::getDefaultSettings();
            }

            return [
                'default_language' => $company->locale->language ?? config('transmatic.source_locale'),
                'default_timezone' => $company->locale->timezone ?? config('app.timezone'),
                'default_currency' => $company->currency_code ?? 'USD',
                'default_pagination_page_option' => $company->appearance->records_per_page->value ?? RecordsPerPage::DEFAULT,
                'default_sort' => $company->appearance->table_sort_direction->value ?? TableSortDirection::DEFAULT,
                'default_primary_color' => $company->appearance->primary_color->value ?? PrimaryColor::DEFAULT,
                'default_font' => $company->appearance->font->value ?? Font::DEFAULT,
                'default_date_format' => $company->locale->date_format->value ?? DateFormat::DEFAULT,
                'default_week_start' => $company->locale->week_start->value ?? WeekStart::DEFAULT,
            ];
        });
    }

    public static function invalidateSettings(int $companyId): void
    {
        $cacheKey = "company_settings_{$companyId}";
        Cache::forget($cacheKey);
    }

    public static function getDefaultSettings(): array
    {
        return [
            'default_language' => config('transmatic.source_locale'),
            'default_timezone' => config('app.timezone'),
            'default_currency' => 'USD',
            'default_pagination_page_option' => RecordsPerPage::DEFAULT,
            'default_sort' => TableSortDirection::DEFAULT,
            'default_primary_color' => PrimaryColor::DEFAULT,
            'default_font' => Font::DEFAULT,
            'default_date_format' => DateFormat::DEFAULT,
            'default_week_start' => WeekStart::DEFAULT,
        ];
    }

    public static function getSpecificSetting(int $companyId, string $key, $default = null)
    {
        $settings = self::getSettings($companyId);

        return $settings[$key] ?? $default;
    }

    public static function getDefaultLanguage(int $companyId): string
    {
        return self::getSpecificSetting($companyId, 'default_language', config('transmatic.source_locale'));
    }

    public static function getDefaultTimezone(int $companyId): string
    {
        return self::getSpecificSetting($companyId, 'default_timezone', config('app.timezone'));
    }

    public static function getDefaultCurrency(int $companyId): string
    {
        return self::getSpecificSetting($companyId, 'default_currency', 'USD');
    }

    public static function getDefaultPaginationOption(int $companyId): int
    {
        return self::getSpecificSetting($companyId, 'default_pagination_page_option', RecordsPerPage::DEFAULT);
    }

    public static function getDefaultPrimaryColor(int $companyId): string
    {
        return self::getSpecificSetting($companyId, 'default_primary_color', PrimaryColor::DEFAULT);
    }
}
