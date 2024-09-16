<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Banking\BankAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => 1,
            'bank_account_id' => 1,
            'account_id' => $this->faker->numberBetween(2, 30),
            'type' => $this->faker->randomElement([TransactionType::Deposit, TransactionType::Withdrawal]),
            'description' => $this->faker->sentence,
            'notes' => $this->faker->paragraph,
            'amount' => $this->faker->numberBetween(100, 5000),
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
                'type' => JournalEntryType::Debit,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'created_by' => $transaction->created_by,
                'updated_by' => $transaction->updated_by,
            ]);

            $creditAccount->journalEntries()->create([
                'company_id' => $transaction->company_id,
                'transaction_id' => $transaction->id,
                'type' => JournalEntryType::Credit,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'created_by' => $transaction->created_by,
                'updated_by' => $transaction->updated_by,
            ]);
        });
    }

    public function forCompanyAndBankAccount(Company $company, BankAccount $bankAccount): static
    {
        return $this->state(function (array $attributes) use ($bankAccount, $company) {
            $type = $this->faker->randomElement([TransactionType::Deposit, TransactionType::Withdrawal]);

            $associatedAccountTypes = match ($type) {
                TransactionType::Deposit => ['asset', 'liability', 'equity', 'revenue'],
                TransactionType::Withdrawal => ['asset', 'liability', 'equity', 'expense'],
            };

            $accountIdForBankAccount = $bankAccount->account->id;

            $account = Account::where('category', $this->faker->randomElement($associatedAccountTypes))
                ->where('company_id', $company->id)
                ->where('id', '<>', $accountIdForBankAccount)
                ->inRandomOrder()
                ->first();

            // If no matching account is found, use a fallback
            if (! $account) {
                $account = Account::where('company_id', $company->id)
                    ->where('id', '<>', $accountIdForBankAccount)
                    ->inRandomOrder()
                    ->firstOrFail(); // Ensure there is at least some account
            }

            return [
                'company_id' => $company->id,
                'bank_account_id' => $bankAccount->id,
                'account_id' => $account->id,
                'type' => $type,
            ];
        });
    }
}
