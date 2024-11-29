@use('App\Utilities\Currency\CurrencyConverter')

<div class="space-y-1">
    <!-- Create Section -->
    <x-filament::section>
        <div class="flex justify-between items-start">
            <!-- Left section -->
            <div class="flex items-center space-x-3">
                <!-- Icon -->
                <x-filament::icon
                    icon="heroicon-o-document-text"
                    class="h-8 w-8 text-primary-600 dark:text-primary-500 flex-shrink-0 mr-4"
                />
                <!-- Text content -->
                <div>
                    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Create</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Created: {{ $getRecord()->created_at->diffForHumans() }}</p>
                </div>
            </div>

            <!-- Right section -->
            <div class="flex flex-row items-center space-x-2">
                @if($getRecord()->status->value === 'draft')
                    {{ $getAction('approveDraft') }}
                @endif
                {{ $getAction('edit') }}
            </div>
        </div>
    </x-filament::section>

    <div class="border-l-4 h-8 border-solid border-gray-300 dark:border-gray-700 ms-8"></div>

    <!-- Send Section -->
    <x-filament::section :class="$getRecord()->status->value === 'draft' ? 'opacity-50 pointer-events-none' : ''">
        <div class="flex justify-between items-start">
            <!-- Left section -->
            <div class="flex items-center space-x-3">
                <!-- Icon -->
                <x-filament::icon
                    icon="heroicon-o-paper-airplane"
                    class="h-8 w-8 text-primary-600 dark:text-primary-500 flex-shrink-0 mr-4"
                />
                <!-- Text content -->
                <div>
                    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Send</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Last Sent: just a moment ago</p>
                </div>
            </div>

            <!-- Right section -->
            @if($getRecord()->status->value !== 'draft')
                <div class="flex flex-row items-center space-x-2">
                    @if($getRecord()->status->value !== 'sent')
                        {{ $getAction('markAsSent') }}
                    @endif
                    {{ $getAction('sendInvoice') }}
                </div>
            @endif
        </div>
    </x-filament::section>

    <div class="border-l-4 h-8 border-solid border-gray-300 dark:border-gray-700 ms-8"></div>

    <!-- Manage Payments Section -->
    <x-filament::section :class="$getRecord()->status->value === 'draft' ? 'opacity-50 pointer-events-none' : ''">
        <div class="flex justify-between items-start">
            <!-- Left section -->
            <div class="flex items-center space-x-3">
                <!-- Icon -->
                <x-filament::icon
                    icon="heroicon-o-credit-card"
                    class="h-8 w-8 text-primary-600 dark:text-primary-500 flex-shrink-0 mr-4"
                />
                <!-- Text content -->
                <div>
                    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Manage Payments</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Amount Due: {{ CurrencyConverter::formatToMoney($getRecord()->amount_due) }}</p>
                </div>
            </div>

            <!-- Right section -->
            @if($getRecord()->status->value !== 'draft')
                <div class="flex flex-row items-center space-x-2">
                    {{ $getAction('recordPayment') }}
                </div>
            @endif
        </div>
    </x-filament::section>
</div>
