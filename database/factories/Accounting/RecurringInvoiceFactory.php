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
use App\Models\Accounting\DocumentLineItem;
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
            'header' => 'Invoice',
            'subheader' => 'Invoice',
            'order_number' => $this->faker->unique()->numerify('ORD-#####'),
            'payment_terms' => PaymentTerms::Net30,
            'status' => RecurringInvoiceStatus::Draft,
            'currency_code' => 'USD',
            'terms' => $this->faker->sentence,
            'footer' => $this->faker->sentence,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function withLineItems(int $count = 3): static
    {
        return $this->afterCreating(function (RecurringInvoice $recurringInvoice) use ($count) {
            DocumentLineItem::factory()
                ->count($count)
                ->forInvoice($recurringInvoice)
                ->create();

            $this->recalculateTotals($recurringInvoice);
        });
    }

    public function withSchedule(
        ?Frequency $frequency = null,
        ?Carbon $startDate = null,
        ?EndType $endType = null
    ): static {
        $frequency ??= $this->faker->randomElement(Frequency::class);
        $endType ??= EndType::Never;

        // Adjust the start date range based on frequency
        $startDate = match ($frequency) {
            Frequency::Daily => Carbon::parse($this->faker->dateTimeBetween('-30 days')), // At most 30 days back
            default => $startDate ?? Carbon::parse($this->faker->dateTimeBetween('-1 year')),
        };

        return match ($frequency) {
            Frequency::Daily => $this->withDailySchedule($startDate, $endType),
            Frequency::Weekly => $this->withWeeklySchedule($startDate, $endType),
            Frequency::Monthly => $this->withMonthlySchedule($startDate, $endType),
            Frequency::Yearly => $this->withYearlySchedule($startDate, $endType),
            Frequency::Custom => $this->withCustomSchedule($startDate, $endType),
        };
    }

    protected function withDailySchedule(Carbon $startDate, EndType $endType): static
    {
        return $this->state([
            'frequency' => Frequency::Daily,
            'start_date' => $startDate,
            'end_type' => $endType,
        ]);
    }

    protected function withWeeklySchedule(Carbon $startDate, EndType $endType): static
    {
        return $this->state([
            'frequency' => Frequency::Weekly,
            'day_of_week' => DayOfWeek::from($startDate->dayOfWeek),
            'start_date' => $startDate,
            'end_type' => $endType,
        ]);
    }

    protected function withMonthlySchedule(Carbon $startDate, EndType $endType): static
    {
        return $this->state([
            'frequency' => Frequency::Monthly,
            'day_of_month' => DayOfMonth::from($startDate->day),
            'start_date' => $startDate,
            'end_type' => $endType,
        ]);
    }

    protected function withYearlySchedule(Carbon $startDate, EndType $endType): static
    {
        return $this->state([
            'frequency' => Frequency::Yearly,
            'month' => Month::from($startDate->month),
            'day_of_month' => DayOfMonth::from($startDate->day),
            'start_date' => $startDate,
            'end_type' => $endType,
        ]);
    }

    protected function withCustomSchedule(
        Carbon $startDate,
        EndType $endType,
        ?IntervalType $intervalType = null,
        ?int $intervalValue = null
    ): static {
        $intervalType ??= $this->faker->randomElement(IntervalType::class);
        $intervalValue ??= match ($intervalType) {
            IntervalType::Day => $this->faker->numberBetween(1, 7),
            IntervalType::Week => $this->faker->numberBetween(1, 4),
            IntervalType::Month => $this->faker->numberBetween(1, 3),
            IntervalType::Year => 1,
        };

        $state = [
            'frequency' => Frequency::Custom,
            'interval_type' => $intervalType,
            'interval_value' => $intervalValue,
            'start_date' => $startDate,
            'end_type' => $endType,
        ];

        // Add interval-specific attributes
        switch ($intervalType) {
            case IntervalType::Day:
                // No additional attributes needed
                break;

            case IntervalType::Week:
                $state['day_of_week'] = DayOfWeek::from($startDate->dayOfWeek);

                break;

            case IntervalType::Month:
                $state['day_of_month'] = DayOfMonth::from($startDate->day);

                break;

            case IntervalType::Year:
                $state['month'] = Month::from($startDate->month);
                $state['day_of_month'] = DayOfMonth::from($startDate->day);

                break;
        }

        return $this->state($state);
    }

    public function endAfter(int $occurrences = 12): static
    {
        return $this->state([
            'end_type' => EndType::After,
            'max_occurrences' => $occurrences,
        ]);
    }

    public function endOn(?Carbon $endDate = null): static
    {
        $endDate ??= now()->addMonths($this->faker->numberBetween(1, 12));

        return $this->state([
            'end_type' => EndType::On,
            'end_date' => $endDate,
        ]);
    }

    public function autoSend(string $sendTime = '09:00'): static
    {
        return $this->state([
            'auto_send' => true,
            'send_time' => $sendTime,
        ]);
    }

    public function approved(): static
    {
        return $this->afterCreating(function (RecurringInvoice $recurringInvoice) {
            $this->ensureLineItems($recurringInvoice);

            if (! $recurringInvoice->hasSchedule()) {
                $this->withSchedule()->callAfterCreating(collect([$recurringInvoice]));
                $recurringInvoice->refresh();
            }

            if (! $recurringInvoice->canBeApproved()) {
                return;
            }

            $approvedAt = $recurringInvoice->start_date
                ? $recurringInvoice->start_date->copy()->subDays($this->faker->numberBetween(1, 7))
                : now()->subDays($this->faker->numberBetween(1, 30));

            $recurringInvoice->approveDraft($approvedAt);
        });
    }

    public function active(): static
    {
        return $this->withLineItems()
            ->withSchedule()
            ->approved();
    }

    public function ended(): static
    {
        return $this->afterCreating(function (RecurringInvoice $recurringInvoice) {
            if (! $recurringInvoice->canBeEnded()) {
                $this->active()->callAfterCreating(collect([$recurringInvoice]));
            }

            $endedAt = now()->subDays($this->faker->numberBetween(1, 30));

            $recurringInvoice->update([
                'ended_at' => $endedAt,
                'status' => RecurringInvoiceStatus::Ended,
            ]);
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (RecurringInvoice $recurringInvoice) {
            $this->ensureLineItems($recurringInvoice);

            $nextDate = $recurringInvoice->calculateNextDate();

            if ($nextDate) {
                $recurringInvoice->updateQuietly([
                    'next_date' => $nextDate,
                ]);
            }
        });
    }

    protected function ensureLineItems(RecurringInvoice $recurringInvoice): void
    {
        if (! $recurringInvoice->hasLineItems()) {
            $this->withLineItems()->callAfterCreating(collect([$recurringInvoice]));
        }
    }

    protected function recalculateTotals(RecurringInvoice $recurringInvoice): void
    {
        $recurringInvoice->refresh();

        if (! $recurringInvoice->hasLineItems()) {
            return;
        }

        $subtotal = $recurringInvoice->lineItems()->sum('subtotal') / 100;
        $taxTotal = $recurringInvoice->lineItems()->sum('tax_total') / 100;
        $discountTotal = $recurringInvoice->lineItems()->sum('discount_total') / 100;
        $grandTotal = $subtotal + $taxTotal - $discountTotal;

        $recurringInvoice->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discountTotal,
            'total' => $grandTotal,
        ]);
    }
}
