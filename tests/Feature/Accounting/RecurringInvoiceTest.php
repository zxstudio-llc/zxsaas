<?php

use App\Enums\Accounting\IntervalType;
use App\Models\Accounting\RecurringInvoice;

test('example', function () {
    $recurringInvoice = RecurringInvoice::factory()
        ->custom(IntervalType::Week, 2)
        ->create([
            'start_date' => today(),
            'day_of_week' => today()->dayOfWeek,
        ]);

    $recurringInvoice->refresh();

    $nextInvoiceDate = $recurringInvoice->calculateNextDate();

    expect($nextInvoiceDate)->toEqual(today());

    $recurringInvoice->update([
        'last_date' => $nextInvoiceDate,
    ]);

    $recurringInvoice->refresh();

    $nextInvoiceDate = $recurringInvoice->calculateNextDate();

    expect($nextInvoiceDate)->toEqual(today()->addWeeks(2));
});
