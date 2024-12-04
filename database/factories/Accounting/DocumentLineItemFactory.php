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
        $offering = Offering::with(['salesTaxes', 'salesDiscounts'])->inRandomOrder()->first();

        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $offering->price;

        return [
            'company_id' => 1,
            'offering_id' => $offering->id,
            'description' => $this->faker->sentence,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (DocumentLineItem $lineItem) {
            $offering = $lineItem->offering;

            if ($offering) {
                $lineItem->salesTaxes()->sync($offering->salesTaxes->pluck('id')->toArray());
                $lineItem->salesDiscounts()->sync($offering->salesDiscounts->pluck('id')->toArray());
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
