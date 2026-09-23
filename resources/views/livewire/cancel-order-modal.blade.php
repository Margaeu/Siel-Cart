<div>
    @if($order->status === 'pending')
        <button type="button"
                wire:click="$set('isOpen', true)"
                class="inline-flex min-h-11 items-center rounded-full border border-red-200 bg-white px-5 text-sm font-semibold text-red-600 transition hover:border-red-300 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">
            Cancel Order
        </button>
    @endif

    @if($isOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto bg-gray-950/50 backdrop-blur-[2px]"
            wire:keydown.escape.window="$set('isOpen', false)"
        >
            <div class="flex min-h-full items-center justify-center p-4" wire:click.self="$set('isOpen', false)">
                <div
                    class="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl shadow-gray-950/20"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="cancel-order-title"
                    aria-describedby="cancel-order-description"
                >
                <div class="flex items-start gap-3 border-b border-gray-100 px-5 py-5 sm:px-6">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600" aria-hidden="true">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.38c.866-1.5 3.03-1.5 3.896 0l7.355 12.746ZM12 16.5h.008v.008H12V16.5Z" />
                        </svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <h3 id="cancel-order-title" class="text-base font-bold leading-6 text-gray-950">
                            Cancel order #{{ $order->order_number }}?
                        </h3>
                        <p id="cancel-order-description" class="mt-1 text-sm leading-5 text-gray-500">
                            Choose the reason that best describes this cancellation.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="$set('isOpen', false)"
                        class="-mr-2 -mt-2 inline-flex size-10 shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                        aria-label="Close cancellation dialog"
                    >
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="cancelOrder" class="px-5 py-5 sm:px-6">
                    <fieldset>
                        <legend class="text-sm font-semibold text-gray-900">Reason for cancelling</legend>

                        <div class="mt-3 grid gap-2.5">
                            <label class="group relative flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-white p-3.5 transition hover:border-red-200 hover:bg-red-50/40 has-[:checked]:border-red-500 has-[:checked]:bg-red-50 has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-red-500">
                                <input
                                    type="radio"
                                    name="cancellation_reason"
                                    value="change_of_mind"
                                    wire:model="reason"
                                    class="peer sr-only"
                                >
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-900">Changed my mind</span>
                                    <span class="mt-0.5 block text-xs leading-4 text-gray-500">I no longer want to continue with this order.</span>
                                </span>
                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 bg-white transition after:size-2 after:rounded-full after:bg-red-600 after:opacity-0 group-has-[:checked]:border-red-600 group-has-[:checked]:after:opacity-100" aria-hidden="true"></span>
                            </label>

                            <label class="group relative flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-white p-3.5 transition hover:border-red-200 hover:bg-red-50/40 has-[:checked]:border-red-500 has-[:checked]:bg-red-50 has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-red-500">
                                <input
                                    type="radio"
                                    name="cancellation_reason"
                                    value="incorrect_items"
                                    wire:model="reason"
                                    class="peer sr-only"
                                >
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-900">Wrong item or quantity</span>
                                    <span class="mt-0.5 block text-xs leading-4 text-gray-500">I need to correct the items in my order.</span>
                                </span>
                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-gray-300 bg-white transition after:size-2 after:rounded-full after:bg-red-600 after:opacity-0 group-has-[:checked]:border-red-600 group-has-[:checked]:after:opacity-100" aria-hidden="true"></span>
                            </label>
                        </div>

                        @error('reason')
                            <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-red-600" role="alert">
                                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v3.75m0 3.75h.008v.008H12V16.5Zm9-4.5a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </fieldset>

                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            wire:click="$set('isOpen', false)"
                            class="inline-flex min-h-11 items-center justify-center rounded-full border border-gray-300 bg-white px-5 text-sm font-semibold text-gray-800 transition hover:border-gray-400 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500 focus-visible:ring-offset-2"
                        >
                            Keep order
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="cancelOrder"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-red-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60"
                        >
                            <svg wire:loading wire:target="cancelOrder" class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
                            </svg>
                            <span>Cancel order</span>
                        </button>
                    </div>
                </form>
                </div>
            </div>
        </div>
    @endif
</div>
