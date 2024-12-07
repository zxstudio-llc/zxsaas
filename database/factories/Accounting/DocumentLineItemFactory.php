<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\DocumentLineItem;
use App\Models\Common\Offering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentLineItem>
 */
class DocumentLineItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = DocumentLineItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);

        return [
            'company_id' => 1,
            'description' => $this->faker->sentence,
            'quantity' => $quantity,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function forInvoice(): static
    {
        return $this->state(function (array $attributes) {
            $offering = Offering::with(['salesTaxes', 'salesDiscounts'])
                ->where('sellable', true)
                ->inRandomOrder()
                ->first();

            return [
                'offering_id' => $offering->id,
                'unit_price' => $offering->price,
            ];
        })->afterCreating(function (DocumentLineItem $lineItem) {
            $offering = $lineItem->offering;

            if ($offering) {
                $lineItem->adjustments()->syncWithoutDetaching($offering->salesTaxes->pluck('id')->toArray());
                $lineItem->adjustments()->syncWithoutDetaching($offering->salesDiscounts->pluck('id')->toArray());
            }

            $lineItem->refresh();

            $taxTotal = $lineItem->calculateTaxTotal()->getAmount();
            $discountTotal = $lineItem->calculateDiscountTotal()->getAmount();

            $lineItem->updateQuietly([
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
            ]);
        });
    }

    public function forBill(): static
    {
        return $this->state(function (array $attributes) {
            $offering = Offering::with(['purchaseTaxes', 'purchaseDiscounts'])
                ->where('purchasable', true)
                ->inRandomOrder()
                ->first();

            return [
                'offering_id' => $offering->id,
                'unit_price' => $offering->price,
            ];
        })->afterCreating(function (DocumentLineItem $lineItem) {
            $offering = $lineItem->offering;

            if ($offering) {
                $lineItem->adjustments()->syncWithoutDetaching($offering->purchaseTaxes->pluck('id')->toArray());
                $lineItem->adjustments()->syncWithoutDetaching($offering->purchaseDiscounts->pluck('id')->toArray());
            }

            $lineItem->refresh();

            $taxTotal = $lineItem->calculateTaxTotal()->getAmount();
            $discountTotal = $lineItem->calculateDiscountTotal()->getAmount();

            $lineItem->updateQuietly([
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
            ]);
        });
    }
}
