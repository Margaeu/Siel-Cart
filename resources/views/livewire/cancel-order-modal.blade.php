<div>
    @if($order->status === 'pending')
        <button wire:click="$set('isOpen', true)" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-md hover:bg-red-700 transition">
            Cancel Order
        </button>
    @endif

    @if($isOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full shadow-xl">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Cancel Order #{{ $order->order_number }}</h3>
                <p class="text-sm text-gray-600 mb-4">Please select a reason for cancelling this order:</p>

                <form wire:submit.prevent="cancelOrder">
                    <div class="mb-4">
                        <select wire:model="reason" class="w-full border-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500">
                            <option value="">Select a reason...</option>
                            <option value="change_of_mind">Change of mind</option>
                            <option value="incorrect_items">Added wrong item/quantity</option>
                            <option value="found_better_price">Found a better price</option>
                            <option value="other">Other reason</option>
                        </select>
                        @error('reason') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('isOpen', false)" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                            Keep Order
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                            Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>