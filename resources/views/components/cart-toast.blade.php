{{--
    Cart notification toast.

    This is rendered once per page in the front-end layout so that a
    single popup is shown no matter how many product cards are on the
    page. It listens for the events dispatched by ProductCard and
    ProductDetails:

    - cart-added : the product was added to the cart
    - cart-error : the product could not be added (stock, variant, etc.)
--}}
<div
    x-data="{
        show: false,
        isError: false,
        message: '',
        timer: null,

        notify(isError, message) {
            this.isError = isError;
            this.message = message;
            this.show = true;

            // Reset the timer when another message arrives.
            clearTimeout(this.timer);

            // Automatically close the popup after 3 seconds.
            this.timer = setTimeout(() => {
                this.show = false;
            }, 3000);
        }
    }"
    @cart-added.window="notify(false, $event.detail.message)"
    @cart-error.window="notify(true, $event.detail.message)"
    x-cloak
    x-show="show"
    x-transition
    class="fixed top-24 right-6 z-[100] max-w-sm"
>
    <div class="bg-white border border-gray-200 shadow-lg rounded-lg p-4 flex items-start gap-3">

        <!-- Status Icon -->
        <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center"
             :class="isError ? 'bg-red-100' : 'bg-green-100'">

            <!-- Success Icon -->
            <svg
                x-show="!isError"
                class="w-5 h-5 text-[#557F13]"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                />
            </svg>

            <!-- Error Icon -->
            <svg
                x-show="isError"
                class="w-5 h-5 text-red-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                />
            </svg>

        </div>

        <!-- Message -->
        <div class="flex-1">

            <p class="font-semibold text-gray-900"
               x-text="isError ? 'Could Not Add to Cart' : 'Added to Cart'"></p>

            <p class="text-sm text-gray-600 mt-1" x-text="message"></p>

        </div>

        <!-- Manual Close Button -->
        <button
            type="button"
            @click="show = false"
            class="text-gray-400 hover:text-gray-600"
        >
            <svg
                class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                />
            </svg>
        </button>

    </div>
</div>
