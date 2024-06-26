<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => 1,
            'account_id' => $this->faker->numberBetween(1, 30),
            'bank_account_id' => 1,
            'type' => $this->faker->randomElement(['deposit', 'withdrawal', 'journal']),
            'payment_channel' => $this->faker->randomElement(['online', 'in store', 'other']),
            'description' => $this->faker->sentence,
            'notes' => $this->faker->paragraph,
            'reference' => $this->faker->word,
            'amount' => $this->faker->numberBetween(100, 5000),
            'pending' => $this->faker->boolean,
            'reviewed' => $this->faker->boolean,
            'posted_at' => $this->faker->dateTimeBetween('-2 years'),
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Transaction $transaction) {
            $chartAccount = $transaction->account;
            $bankAccount = $transaction->bankAccount->account;

            $debitAccount = $transaction->type->isWithdrawal() ? $chartAccount : $bankAccount;
            $creditAccount = $transaction->type->isWithdrawal() ? $bankAccount : $chartAccount;

            if ($debitAccount === null || $creditAccount === null) {
                return;
            }

            $debitAccount->journalEntries()->create([
                'company_id' => $transaction->company_id,
                'transaction_id' => $transaction->id,
                'type' => 'debit',
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'created_by' => $transaction->created_by,
                'updated_by' => $transaction->updated_by,
            ]);

            $creditAccount->journalEntries()->create([
                'company_id' => $transaction->company_id,
                'transaction_id' => $transaction->id,
                'type' => 'credit',
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'created_by' => $transaction->created_by,
                'updated_by' => $transaction->updated_by,
            ]);
        });
    }
}
