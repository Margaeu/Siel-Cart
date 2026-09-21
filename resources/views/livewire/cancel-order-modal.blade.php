<div>
    @if($order->status === 'pending')
        <button type="button"
                wire:click="$set('isOpen', true)"
                class="inline-flex min-h-11 items-center rounded-full border border-red-200 bg-white px-5 text-sm font-semibold text-red-600 transition hover:border-red-300 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">
            Cancel Order
        </button>
    @endif

    @if($isOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4">
            <div class="bg-white rounded-2xl border border-gray-100 p-6 max-w-md w-full shadow-xl"
                 role="dialog" aria-modal="true" aria-labelledby="cancel-order-title">
                <h3 id="cancel-order-title" class="text-base font-bold text-gray-900 mb-1">Cancel Order #{{ $order->order_number }}</h3>
                <p class="text-sm text-gray-500 mb-5">Please select a reason for cancelling this order:</p>

                <form wire:submit.prevent="cancelOrder">
                    <div class="mb-5">
                        <select wire:model="reason" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-red-500 focus:ring-red-500">
                            <option value="">Select a reason...</option>
                            <option value="change_of_mind">Change of mind</option>
                            <option value="incorrect_items">Added wrong item/quantity</option>
                        </select>
                        @error('reason') <span class="block text-red-600 text-xs mt-1.5">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <button type="button" wire:click="$set('isOpen', false)"
                                class="inline-flex min-h-11 items-center justify-center rounded-full border border-gray-300 bg-white px-5 text-sm font-semibold text-gray-800 transition hover:border-gray-400 hover:bg-gray-50">
                            Keep Order
                        </button>
                        <button type="submit"
                                class="inline-flex min-h-11 items-center justify-center rounded-full bg-red-600 px-5 text-sm font-semibold text-white transition hover:bg-red-700">
                            Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
