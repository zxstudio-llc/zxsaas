# ERPSAAS

<img width="1920" alt="Screenshot 2024-05-07 at 10 01 46 PM" src="https://github.com/andrewdwallo/erpsaas/assets/104294090/5146c4db-dffc-4207-9095-2ebb80d452e1">
<img width="1920" alt="Screenshot 2024-05-07 at 10 04 05 PM" src="https://github.com/andrewdwallo/erpsaas/assets/104294090/d7115830-6912-4267-ab54-17f7dbcc21cd">
<img width="1920" alt="Screenshot 2024-05-07 at 10 23 31 PM" src="https://github.com/andrewdwallo/erpsaas/assets/104294090/c85862ac-62ff-4c0d-9b2a-f7393ad977ef">
<img width="1920" alt="Screenshot 2024-05-07 at 10 24 11 PM" src="https://github.com/andrewdwallo/erpsaas/assets/104294090/3a4deebc-528c-4b84-91db-9f0515de883d">
<img width="1920" alt="Screenshot 2024-05-07 at 10 24 46 PM" src="https://github.com/andrewdwallo/erpsaas/assets/104294090/c50a899d-ee6f-4300-92a9-4a41c5433972">
<img width="1920" alt="Screenshot 2024-05-07 at 10 55 56 PM" src="https://github.com/andrewdwallo/erpsaas/assets/104294090/6395030a-6688-4b08-bf6c-b12b5e591b31">



This repo is currently a work in progress — PRs and issues welcome!

# Getting started

## Installation

Please check the official laravel installation guide for server requirements before you start. [Official Documentation](https://laravel.com/docs/10.x)

Clone the repository

    git clone git@github.com:zxstudio-llc/zxsaas.git

Switch to the repo folder

    cd zxsaas

Install all the dependencies using composer and npm

    composer install
    npm install

Copy the example env file and make the required configuration changes in the .env file

    cp .env.example .env

Generate a new application key

    php artisan key:generate

Run the database migrations (**Set the database connection in .env before migrating**)

    php artisan migrate

Build your assets & start the local development server

    php artisan filament:assets
    npm run build
    npm run dev

**Command list**

    git clone git@github.com:zxstudio-llc/zxsaas.git
    cd zxsaas
    composer install
    npm install
    cp .env.example .env
    php artisan key:generate
    php artisan migrate
    npm run build
    npm run dev

## Database seeding

**You may populate the database to help you get started quickly**

Open the DatabaseSeeder and set the property values as per your requirement

    database/seeders/DatabaseSeeder.php

Default login information:

    email: admin@gmail.com
    password: password

Run the database seeder

    php artisan db:seed

***Note*** : It's recommended to have a clean database before seeding. You can refresh your migrations at any point to clean the database by running the following command

    php artisan migrate:refresh

## Generating PDFs for Reports

To generate PDFs for reports, the application uses Laravel Snappy. The Laravel Snappy package is already included in the application, but you need to install Wkhtmltopdf separately.

### Wkhtmltopdf Installation

1. **Download and install Wkhtmltopdf**
   - [Wkhtmltopdf Downloads](https://wkhtmltopdf.org/downloads.html)
   
   - Alternatively, if you are using Homebrew on macOS, you can install it using the following command:
     ```bash
     brew install wkhtmltopdf
     ```

2. **Configure the binary paths**
   - If needed, you can change the paths to the Wkhtmltopdf binaries in the Snappy configuration file (`config/snappy.php`).

For detailed installation instructions, refer to the [Laravel Snappy documentation](https://github.com/barryvdh/laravel-snappy).

## Live Currency

### Overview

This application offers support for real-time currency exchange rates. This feature is disabled by default. To enable it, you must first register for an API key at [ExchangeRate-API](https://www.exchangerate-api.com/). The application uses this service due to its generous provision of up to 1,500 free API calls per month, which should be enough for development and testing purposes.

**Disclaimer**: There is no affiliation between this application and ExchangeRate-API.

Once you have your API key, you can enable the feature by setting the `CURRENCY_API_KEY` environment variable in your `.env` file.

### Initial Setup

After setting your API key in the `.env` file, it is essential to prepare your database to store the currency data. Start by running a fresh database migration:

```bash
php artisan migrate:fresh
```

This ensures that your database is in the correct state to store the currency information. Afterward, use the following command to generate and populate the Currency List with supported currencies for the Live Currency page:

```bash
php artisan currency:init
```

This command fetches and stores the list of currencies supported by your configured exchange rate service.

### Configuration

Of course, you may use any service you wish to retrieve currency exchange rates. If you decide to use a different service, you can update the `config/services.php` file with your choice:

```php
'currency_api' => [
    'key' => env('CURRENCY_API_KEY'),
    'base_url' => 'https://v6.exchangerate-api.com/v6',
],
```

Then, adjust the implementation of the `App\Services\CurrencyService` class to use your chosen service.

### Live Currency Page

Once enabled, the "Live Currency" feature provides access to a dedicated page in the application, listing all supported currencies from the configured exchange rate service. Users can view available currencies and update exchange rates for their company's currencies as needed.

### Important Information

- To use the currency exchange rate feature, you must first obtain an API key from a service provider. This application is configured to use a service that offers a free tier suitable for development and testing purposes.
- Your API key is sensitive information and should be kept secret. Do not commit it to your repository or share it with anyone.
- Note that API rate limits may apply depending on the service you choose. Make sure to review the terms for your chosen service.

## Automatic Translation

The application now supports automatic translation, leveraging machine translation services provided by AWS, as facilitated by the [andrewdwallo/transmatic](https://github.com/andrewdwallo/transmatic) package. This integration significantly enhances the application's accessibility for a global audience. The application currently offers support for several languages, including English, Arabic, German, Spanish, French, Indonesian, Italian, Dutch, Portuguese, Turkish, and Chinese, with English as the default language.

### Configuration & Usage

To utilize this feature for additional languages or custom translations:
1. Follow the documentation provided in the [andrewdwallo/transmatic](https://github.com/andrewdwallo/transmatic) package.
2. Configure the package with your preferred translation service credentials.
3. Run the translation commands as per the package instructions to generate new translations.

Once you have configured the package, you may update the following method in the `app/Models/Setting/Localization.php` file to generate translations based on the selected language in the application UI:

Change to the following:
```php
public static function getAllLanguages(): array
{
    return Languages::getNames(app()->getLocale());
}
```

## Plaid Integration

To integrate [Plaid](https://plaid.com/) with your application for enhanced financial data connectivity, you must first create an account with Plaid and obtain your credentials. Set your credentials in the `.env` file as follows:

```env
PLAID_CLIENT_ID=your-client-id
PLAID_CLIENT_SECRET=your-secret
PLAID_ENVIRONMENT=sandbox # Can be sandbox, development, or production
PLAID_WEBHOOK_URL=https://my-static-domain.ngrok-free.app/api/plaid/webhook # Must have /api/plaid/webhook appended
```

The `PLAID_WEBHOOK_URL` is essential as it enables your application to receive real-time updates on transactions from connected bank accounts. This webhook URL must contain a static domain, which can be obtained from services like ngrok that offer a free static domain upon signup. Alternatively, you may use any other service that provides a static domain.

After integrating Plaid, you can connect your account on the "Connected Accounts" page and link your financial institution. Before importing transactions, ensure to run the following command to process the queued transactions:

```bash
php artisan queue:work --queue=transactions
```

## Dependencies

- [filamentphp/filament](https://github.com/filamentphp/filament) - A collection of beautiful full-stack components
- [andrewdwallo/filament-companies](https://github.com/andrewdwallo/filament-companies) - A complete authentication system kit based on companies built for Filament
- [andrewdwallo/transmatic](https://github.com/andrewdwallo/transmatic) - A package for automatic translation using machine translation services
- [akaunting/laravel-money](https://github.com/akaunting/laravel-money) - Currency formatting and conversion package for Laravel
- [squirephp/squire](https://github.com/squirephp/squire) - A library of static Eloquent models for common fixture data
- [awcodes/filament-table-repeater](https://github.com/awcodes/filament-table-repeater) - A modified version of the Filament Forms Repeater to display it as a table. 

***Note*** : It is recommended to read the documentation for all dependencies to get yourself familiar with how the application works.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
