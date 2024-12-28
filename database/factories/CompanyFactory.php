<?php

namespace Database\Factories;

use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Transaction;
use App\Models\Common\Client;
use App\Models\Common\Offering;
use App\Models\Common\Vendor;
use App\Models\Company;
use App\Models\Setting\CompanyProfile;
use App\Models\User;
use App\Services\CompanyDefaultService;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'user_id' => User::factory(),
            'personal_company' => true,
        ];
    }

    public function withCompanyProfile(): self
    {
        return $this->afterCreating(function (Company $company) {
            CompanyProfile::factory()->forCompany($company)->withCountry('US')->create();
        });
    }

    /**
     * Set up default settings for the company after creation.
     */
    public function withCompanyDefaults(): self
    {
        return $this->afterCreating(function (Company $company) {
            $countryCode = $company->profile->country;
            $companyDefaultService = app(CompanyDefaultService::class);
            $companyDefaultService->createCompanyDefaults($company, $company->owner, 'USD', $countryCode, 'en');
        });
    }

    public function withTransactions(int $count = 2000): self
    {
        return $this->afterCreating(function (Company $company) use ($count) {
            $defaultBankAccount = $company->default->bankAccount;

            Transaction::factory()
                ->forCompanyAndBankAccount($company, $defaultBankAccount)
                ->count($count)
                ->create([
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);
        });
    }

    public function withClients(int $count = 10): self
    {
        return $this->has(Client::factory()->count($count)->withPrimaryContact()->withAddresses());
    }

    public function withVendors(int $count = 10): self
    {
        return $this->has(Vendor::factory()->count($count)->withContact()->withAddress());
    }

    public function withOfferings(int $count = 10): self
    {
        return $this->afterCreating(function (Company $company) use ($count) {
            Offering::factory()
                ->count($count)
                ->sellable()
                ->withSalesAdjustments()
                ->purchasable()
                ->withPurchaseAdjustments()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);
        });
    }

    public function withInvoices(int $count = 10): self
    {
        return $this->afterCreating(function (Company $company) use ($count) {
            $draftCount = (int) floor($count * 0.2);
            $approvedCount = (int) floor($count * 0.2);
            $paidCount = (int) floor($count * 0.3);
            $partialCount = (int) floor($count * 0.2);
            $overpaidCount = $count - ($draftCount + $approvedCount + $paidCount + $partialCount);

            Invoice::factory()
                ->count($draftCount)
                ->withLineItems()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Invoice::factory()
                ->count($approvedCount)
                ->withLineItems()
                ->approved()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Invoice::factory()
                ->count($paidCount)
                ->withLineItems()
                ->approved()
                ->withPayments(max: 4)
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Invoice::factory()
                ->count($partialCount)
                ->withLineItems()
                ->approved()
                ->withPayments(max: 4, invoiceStatus: InvoiceStatus::Partial)
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Invoice::factory()
                ->count($overpaidCount)
                ->withLineItems()
                ->approved()
                ->withPayments(max: 4, invoiceStatus: InvoiceStatus::Overpaid)
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);
        });
    }

    public function withEstimates(int $count = 10): self
    {
        return $this->afterCreating(function (Company $company) use ($count) {
            $draftCount = (int) floor($count * 0.2);     // 20% drafts
            $approvedCount = (int) floor($count * 0.3);   // 30% approved
            $acceptedCount = (int) floor($count * 0.2);  // 20% accepted
            $declinedCount = (int) floor($count * 0.1);  // 10% declined
            $convertedCount = (int) floor($count * 0.1); // 10% converted to invoices
            $expiredCount = $count - ($draftCount + $approvedCount + $acceptedCount + $declinedCount + $convertedCount); // remaining 10%

            // Create draft estimates
            Estimate::factory()
                ->count($draftCount)
                ->withLineItems()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            // Create pending (approved) estimates
            Estimate::factory()
                ->count($approvedCount)
                ->withLineItems()
                ->approved()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            // Create accepted estimates
            Estimate::factory()
                ->count($acceptedCount)
                ->withLineItems()
                ->accepted()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            // Create declined estimates
            Estimate::factory()
                ->count($declinedCount)
                ->withLineItems()
                ->declined()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            // Create converted estimates
            Estimate::factory()
                ->count($convertedCount)
                ->withLineItems()
                ->accepted()
                ->converted()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            // Create expired estimates (approved but past expiration date)
            Estimate::factory()
                ->count($expiredCount)
                ->withLineItems()
                ->approved()
                ->state([
                    'expiration_date' => now()->subDays(rand(1, 30)),
                ])
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);
        });
    }

    public function withBills(int $count = 10): self
    {
        return $this->afterCreating(function (Company $company) use ($count) {
            $unpaidCount = (int) floor($count * 0.4);
            $paidCount = (int) floor($count * 0.4);
            $partialCount = $count - ($unpaidCount + $paidCount);

            // Create unpaid bills
            Bill::factory()
                ->count($unpaidCount)
                ->withLineItems()
                ->initialized()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            // Create paid bills
            Bill::factory()
                ->count($paidCount)
                ->withLineItems()
                ->initialized()
                ->withPayments(max: 4)
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            // Create partially paid bills
            Bill::factory()
                ->count($partialCount)
                ->withLineItems()
                ->initialized()
                ->withPayments(max: 4, billStatus: BillStatus::Partial)
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);
        });
    }
}
