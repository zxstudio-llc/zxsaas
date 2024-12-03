<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Accounting\PaymentMethod;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Banking\BankAccount;
use App\Models\Common\Client;
use App\Utilities\Currency\CurrencyConverter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Invoice::class;

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
            'invoice_number' => $this->faker->unique()->numerify('INV-#####'),
            'order_number' => $this->faker->unique()->numerify('ORD-#####'),
            'date' => $this->faker->dateTimeBetween('-1 year'),
            'due_date' => $this->faker->dateTimeBetween('-2 months', '+2 months'),
            'status' => InvoiceStatus::Draft,
            'currency_code' => 'USD',
            'terms' => $this->faker->sentence,
            'footer' => $this->faker->sentence,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function withLineItems(int $count = 3): self
    {
        return $this->has(DocumentLineItem::factory()->count($count), 'lineItems');
    }

    public function approved(): static
    {
        return $this->afterCreating(function (Invoice $invoice) {
            if (! $invoice->isDraft()) {
                return;
            }

            $invoice->approveDraft();
        });
    }

    public function withPayments(?int $min = 1, ?int $max = null, InvoiceStatus $invoiceStatus = InvoiceStatus::Paid): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($invoiceStatus, $max, $min) {
            if ($invoice->isDraft()) {
                $invoice->approveDraft();
            }

            $this->recalculateTotals($invoice);

            $invoice->refresh();

            $totalAmountDue = $invoice->getRawOriginal('amount_due');

            if ($invoiceStatus === InvoiceStatus::Overpaid) {
                $totalAmountDue += random_int(1000, 10000);
            } elseif ($invoiceStatus === InvoiceStatus::Partial) {
                $totalAmountDue = (int) floor($totalAmountDue * 0.5);
            }

            if ($totalAmountDue <= 0 || empty($totalAmountDue)) {
                return;
            }

            $paymentCount = $max && $min ? $this->faker->numberBetween($min, $max) : $min;

            $paymentAmount = (int) floor($totalAmountDue / $paymentCount);
            $remainingAmount = $totalAmountDue;

            for ($i = 0; $i < $paymentCount; $i++) {
                $amount = $i === $paymentCount - 1 ? $remainingAmount : $paymentAmount;

                if ($amount <= 0) {
                    break;
                }

                $data = [
                    'posted_at' => $invoice->date->addDay(),
                    'amount' => CurrencyConverter::convertCentsToFormatSimple($amount, $invoice->currency_code),
                    'payment_method' => $this->faker->randomElement(PaymentMethod::class),
                    'bank_account_id' => BankAccount::inRandomOrder()->value('id'),
                    'notes' => $this->faker->sentence,
                ];

                $invoice->recordPayment($data);

                $remainingAmount -= $amount;
            }
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Invoice $invoice) {
            // Use the invoice's ID to generate invoice and order numbers
            $paddedId = str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT);

            $invoice->updateQuietly([
                'invoice_number' => "INV-{$paddedId}",
                'order_number' => "ORD-{$paddedId}",
            ]);

            $this->recalculateTotals($invoice);

            if ($invoice->due_date->isBefore(today()) && $invoice->canBeOverdue()) {
                $invoice->updateQuietly([
                    'status' => InvoiceStatus::Overdue,
                ]);
            }
        });
    }

    protected function recalculateTotals(Invoice $invoice): void
    {
        if ($invoice->lineItems()->exists()) {
            $subtotal = $invoice->lineItems()->sum('subtotal') / 100;
            $taxTotal = $invoice->lineItems()->sum('tax_total') / 100;
            $discountTotal = $invoice->lineItems()->sum('discount_total') / 100;
            $grandTotal = $subtotal + $taxTotal - $discountTotal;

            $invoice->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'total' => $grandTotal,
            ]);
        }
    }
}
