<x-filament-panels::page>
    {{ $this->form }}
    {{ $this->table }}

    @script
    <script>
        const transactionId = localStorage.getItem('openTransactionId');
        if (transactionId) {
            localStorage.removeItem('openTransactionId');
            $wire.openModalForTransaction(transactionId);
        }
    </script>
    @endscript
</x-filament-panels::page>
