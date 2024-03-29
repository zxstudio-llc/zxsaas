<?php

namespace App\Traits;

use App\Enums\Accounting\JournalEntryType;

trait HasJournalEntryActions
{
    public string $debitAmount = '0.00';

    public string $creditAmount = '0.00';

    public function setDebitAmount(string $amount): void
    {
        $this->debitAmount = $amount;
    }

    public function getDebitAmount(): string
    {
        return $this->debitAmount;
    }

    public function getFormattedDebitAmount(): string
    {
        return money($this->getDebitAmount(), 'USD', true)->format();
    }

    public function setCreditAmount(string $amount): void
    {
        $this->creditAmount = $amount;
    }

    public function getCreditAmount(): string
    {
        return $this->creditAmount;
    }

    public function getFormattedCreditAmount(): string
    {
        return money($this->getCreditAmount(), 'USD', true)->format();
    }

    public function getBalanceDifference(): string
    {
        return bcsub($this->getDebitAmount(), $this->getCreditAmount(), 2);
    }

    public function getFormattedBalanceDifference(): string
    {
        $difference = $this->getBalanceDifference();
        $absoluteDifference = abs((float) $difference);

        return money($absoluteDifference, 'USD', true)->format();
    }

    public function isJournalEntryBalanced(): bool
    {
        return bccomp($this->getDebitAmount(), $this->getCreditAmount(), 2) === 0;
    }

    public function resetJournalEntryAmounts(): void
    {
        $this->reset(['debitAmount', 'creditAmount']);
    }

    public function adjustJournalEntryAmountsForTypeChange(JournalEntryType $newType, JournalEntryType $oldType, ?string $amount): void
    {
        if ($newType !== $oldType) {
            $normalizedAmount = $amount === null ? '0.00' : rtrim($amount, '.');
            $normalizedAmount = $this->sanitizeAndFormatAmount($normalizedAmount);

            if (bccomp($normalizedAmount, '0.00', 2) === 0) {
                return;
            }

            if ($oldType->isDebit() && $newType->isCredit()) {
                $this->setDebitAmount(bcsub($this->getDebitAmount(), $normalizedAmount, 2));
                $this->setCreditAmount(bcadd($this->getCreditAmount(), $normalizedAmount, 2));
            } elseif ($oldType->isCredit() && $newType->isDebit()) {
                $this->setDebitAmount(bcadd($this->getDebitAmount(), $normalizedAmount, 2));
                $this->setCreditAmount(bcsub($this->getCreditAmount(), $normalizedAmount, 2));
            }
        }
    }

    public function updateJournalEntryAmount(JournalEntryType $journalEntryType, ?string $newAmount, ?string $oldAmount): void
    {
        if ($newAmount === $oldAmount) {
            return;
        }

        $normalizedNewAmount = $newAmount === null ? '0.00' : rtrim($newAmount, '.');
        $normalizedOldAmount = $oldAmount === null ? '0.00' : rtrim($oldAmount, '.');

        $formattedNewAmount = $this->sanitizeAndFormatAmount($normalizedNewAmount);
        $formattedOldAmount = $this->sanitizeAndFormatAmount($normalizedOldAmount);

        $difference = bcsub($formattedNewAmount, $formattedOldAmount, 2);

        if ($journalEntryType->isDebit()) {
            $this->setDebitAmount(bcadd($this->getDebitAmount(), $difference, 2));
        } else {
            $this->setCreditAmount(bcadd($this->getCreditAmount(), $difference, 2));
        }
    }

    protected function sanitizeAndFormatAmount(string $amount): string
    {
        return (string) money($amount, 'USD')->getAmount();
    }
}
