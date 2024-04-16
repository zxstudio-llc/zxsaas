<span>Are you sure you want to refresh transactions for the following connected accounts at {{ $institution->name }}?</span>
<ul class="list-disc list-inside p-4">
    @foreach($institution->getEnabledConnectedBankAccounts() as $connectedBankAccount)
        <li>{{ $connectedBankAccount->name }}</li>
    @endforeach
</ul>
<span>Refreshing transactions will update all listed accounts with the latest transactions from the bank. This may take a few moments.</span>

