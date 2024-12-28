<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\EstimateStatus;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Estimate;
use App\Models\Common\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Estimate>
 */
class EstimateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Estimate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $estimateDate = $this->faker->dateTimeBetween('-1 year');

        return [
            'company_id' => 1,
            'client_id' => Client::inRandomOrder()->value('id'),
            'header' => 'Estimate',
            'subheader' => 'Estimate',
            'estimate_number' => $this->faker->unique()->numerify('EST-#####'),
            'reference_number' => $this->faker->unique()->numerify('REF-#####'),
            'date' => $estimateDate,
            'expiration_date' => Carbon::parse($estimateDate)->addDays($this->faker->numberBetween(14, 30)),
            'status' => EstimateStatus::Draft,
            'currency_code' => 'USD',
            'terms' => $this->faker->sentence,
            'footer' => $this->faker->sentence,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function withLineItems(int $count = 3): self
    {
        return $this->has(DocumentLineItem::factory()->forEstimate()->count($count), 'lineItems');
    }

    public function approved(): static
    {
        return $this->afterCreating(function (Estimate $estimate) {
            if (! $estimate->isDraft()) {
                return;
            }

            $this->recalculateTotals($estimate);

            $approvedAt = Carbon::parse($estimate->date)->addHours($this->faker->numberBetween(1, 24));

            $estimate->approveDraft($approvedAt);
        });
    }

    public function accepted(): static
    {
        return $this->afterCreating(function (Estimate $estimate) {
            if (! $estimate->isApproved()) {
                $this->approved()->create();
            }

            $acceptedAt = Carbon::parse($estimate->approved_at)
                ->addDays($this->faker->numberBetween(1, 7));

            $estimate->markAsAccepted($acceptedAt);
        });
    }

    public function converted(): static
    {
        return $this->afterCreating(function (Estimate $estimate) {
            if (! $estimate->isAccepted()) {
                $this->accepted()->create();
            }

            $convertedAt = Carbon::parse($estimate->accepted_at)
                ->addDays($this->faker->numberBetween(1, 7));

            $estimate->convertToInvoice($convertedAt);
        });
    }

    public function declined(): static
    {
        return $this->afterCreating(function (Estimate $estimate) {
            if (! $estimate->isApproved()) {
                $this->approved()->create();
            }

            $declinedAt = Carbon::parse($estimate->approved_at)
                ->addDays($this->faker->numberBetween(1, 7));

            $estimate->update([
                'status' => EstimateStatus::Declined,
                'declined_at' => $declinedAt,
            ]);
        });
    }

    public function sent(): static
    {
        return $this->afterCreating(function (Estimate $estimate) {
            if (! $estimate->isDraft()) {
                return;
            }

            $this->recalculateTotals($estimate);

            $sentAt = Carbon::parse($estimate->date)->addHours($this->faker->numberBetween(1, 24));

            $estimate->markAsSent($sentAt);
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Estimate $estimate) {
            $paddedId = str_pad((string) $estimate->id, 5, '0', STR_PAD_LEFT);

            $estimate->updateQuietly([
                'estimate_number' => "EST-{$paddedId}",
                'reference_number' => "REF-{$paddedId}",
            ]);

            $this->recalculateTotals($estimate);

            if ($estimate->approved_at && $estimate->is_currently_expired) {
                $estimate->updateQuietly([
                    'status' => EstimateStatus::Expired,
                ]);
            }
        });
    }

    protected function recalculateTotals(Estimate $estimate): void
    {
        if ($estimate->lineItems()->exists()) {
            $estimate->refresh();
            $subtotal = $estimate->lineItems()->sum('subtotal') / 100;
            $taxTotal = $estimate->lineItems()->sum('tax_total') / 100;
            $discountTotal = $estimate->lineItems()->sum('discount_total') / 100;
            $grandTotal = $subtotal + $taxTotal - $discountTotal;

            $estimate->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'total' => $grandTotal,
            ]);
        }
    }
}
