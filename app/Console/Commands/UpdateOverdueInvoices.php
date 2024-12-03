<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateOverdueInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-overdue-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and mark overdue invoices as overdue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing overdue invoices...');
    }
}
