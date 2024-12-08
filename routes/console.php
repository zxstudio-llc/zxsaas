<?php

use App\Console\Commands\UpdateOverdueInvoices;
use Illuminate\Support\Facades\Schedule;

Schedule::command(UpdateOverdueInvoices::class)->everyFiveMinutes();
