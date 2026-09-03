<div class="max-w-3xl mx-auto py-8 px-4">
    <h2 class="text-2xl font-bold mb-6">Request Return / Refund for Order #{{ $order->order_number }}</h2>

    <form wire:submit.prevent="submitRefund" class="space-y-6 bg-white p-6 rounded-lg shadow-sm border">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Return</label>
            <select wire:model="reason" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500">
                <option value="defective">Product has a defect</option>
                <option value="damaged">Product is damaged</option>
                <option value="item_mismatch">Does not match description / wrong item</option>
            </select>
            @error('reason') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description / Additional Details</label>
            <textarea wire:model="customer_description" rows="4" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-amber-500 focus:border-amber-500" placeholder="Please describe the defect or issue in detail..."></textarea>
            @error('customer_description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Photo Proof (1-5 Images)</label>
            <input type="file" wire:model="proof_images" multiple accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
            @error('proof_images') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            @error('proof_images.*') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div class="pt-4">
            <button type="submit" class="w-full bg-amber-600 text-white font-bold py-2 px-4 rounded-md hover:bg-amber-700 transition">
                Submit Refund Request
            </button>
        </div>
    </form>
</div>