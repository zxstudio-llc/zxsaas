@use('App\Utilities\Currency\CurrencyAccessor')

@php
    $data = $this->form->getRawState();
    $viewModel = new \App\View\Models\InvoiceTotalViewModel($this->record, $data);
    extract($viewModel->buildViewData(), \EXTR_SKIP);

    $isInvoiceLevelDiscount = $data['discount_method'] === 'invoice';
@endphp

<div class="totals-summary w-full pr-14">
    <table class="w-full text-right table-fixed">
        <colgroup>
            <col class="w-[20%]"> {{-- Items --}}
            <col class="w-[30%]"> {{-- Description --}}
            <col class="w-[10%]"> {{-- Quantity --}}
            <col class="w-[10%]"> {{-- Price --}}
            <col class="w-[20%]"> {{-- Taxes --}}
            <col class="w-[10%]"> {{-- Amount --}}
        </colgroup>
        <tbody>
            <tr>
                <td colspan="4"></td>
                <td class="text-sm px-4 py-2 font-medium leading-6 text-gray-950 dark:text-white">Subtotal:</td>
                <td class="text-sm pl-4 py-2 leading-6">{{ $subtotal }}</td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td class="text-sm px-4 py-2 font-medium leading-6 text-gray-950 dark:text-white">Taxes:</td>
                <td class="text-sm pl-4 py-2 leading-6">{{ $taxTotal }}</td>
            </tr>
            @if($isInvoiceLevelDiscount)
                <tr>
                    <td colspan="4" class="text-sm px-4 py-2 font-medium leading-6 text-gray-950 dark:text-white text-right">Discount:</td>
                    <td class="text-sm px-4 py-2">
                        <div class="flex justify-between space-x-2">
                            @foreach($getChildComponentContainer()->getComponents() as $component)
                                <div class="flex-1">{{ $component }}</div>
                            @endforeach
                        </div>
                    </td>
                    <td class="text-sm pl-4 py-2 leading-6">({{ $discountTotal }})</td>
                </tr>
            @else
                <tr>
                    <td colspan="4"></td>
                    <td class="text-sm px-4 py-2 font-medium leading-6 text-gray-950 dark:text-white">Discounts:</td>
                    <td class="text-sm pl-4 py-2 leading-6">({{ $discountTotal }})</td>
                </tr>
            @endif
            <tr class="font-semibold">
                <td colspan="4"></td>
                <td class="text-sm px-4 py-2 font-medium leading-6 text-gray-950 dark:text-white">Total:</td>
                <td class="text-sm pl-4 py-2 leading-6">{{ $grandTotal }}</td>
            </tr>
        </tbody>
    </table>
</div>
