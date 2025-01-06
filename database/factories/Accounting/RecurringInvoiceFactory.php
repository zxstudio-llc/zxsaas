<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\DayOfMonth;
use App\Enums\Accounting\DayOfWeek;
use App\Enums\Accounting\EndType;
use App\Enums\Accounting\Frequency;
use App\Enums\Accounting\IntervalType;
use App\Enums\Accounting\Month;
use App\Enums\Accounting\RecurringInvoiceStatus;
use App\Enums\Setting\PaymentTerms;
use App\Models\Accounting\RecurringInvoice;
use App\Models\Common\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<RecurringInvoice>
 */
class RecurringInvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = RecurringInvoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => 1,
            'client_id' => Client::inRandomOrder()->value('id'),
            'status' => RecurringInvoiceStatus::Draft,

            // Schedule configuration
            'frequency' => Frequency::Monthly,
            'day_of_month' => DayOfMonth::First,

            // Date configuration
            'start_date' => now()->addMonth()->startOfMonth(),
            'end_type' => EndType::Never,

            // Invoice configuration
            'payment_terms' => PaymentTerms::DueUponReceipt,
            'currency_code' => 'USD',

            // Timestamps and user tracking
            'terms' => $this->faker->sentence,
            'footer' => $this->faker->sentence,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function weekly(DayOfWeek $dayOfWeek = DayOfWeek::Monday): self
    {
        return $this->state([
            'frequency' => Frequency::Weekly,
            'day_of_week' => $dayOfWeek,
        ]);
    }

    public function monthly(DayOfMonth $dayOfMonth = DayOfMonth::First): self
    {
        return $this->state([
            'frequency' => Frequency::Monthly,
            'day_of_month' => $dayOfMonth,
        ]);
    }

    public function yearly(Month $month = Month::January, DayOfMonth $dayOfMonth = DayOfMonth::First): self
    {
        return $this->state([
            'frequency' => Frequency::Yearly,
            'month' => $month,
            'day_of_month' => $dayOfMonth,
        ]);
    }

    public function custom(IntervalType $intervalType, int $intervalValue = 1): self
    {
        return $this->state([
            'frequency' => Frequency::Custom,
            'interval_type' => $intervalType,
            'interval_value' => $intervalValue,
        ]);
    }

    public function withEndDate(Carbon $endDate): self
    {
        return $this->state([
            'end_type' => EndType::On,
            'end_date' => $endDate,
        ]);
    }

    public function withMaxOccurrences(int $maxOccurrences): self
    {
        return $this->state([
            'end_type' => EndType::After,
            'max_occurrences' => $maxOccurrences,
        ]);
    }

    public function autoSend(string $time = '09:00'): self
    {
        return $this->state([
            'auto_send' => true,
            'send_time' => $time,
        ]);
    }
}
