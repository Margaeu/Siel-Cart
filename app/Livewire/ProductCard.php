<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class ProductCard extends Component
{
    public Product $product;

    /**
     * Which homepage ranking section this card is rendered for, or null for
     * every other listing. 'best_seller' | 'top_pick'. Drives the badge
     * only -- eligibility and ranking are decided before the card is ever
     * mounted, by HomepageProductRankingService.
     */
    public ?string $badge = null;

    /** Whether this product currently appears in each sales-ranked section. */
    public bool $isBestSeller = false;

    public bool $isTopPick = false;

    public function mount(
        Product $product,
        ?string $badge = null,
        bool $isBestSeller = false,
        bool $isTopPick = false,
    ): void {
        $this->product = $product;
        $this->badge = $badge;
        $this->isBestSeller = $isBestSeller;
        $this->isTopPick = $isTopPick;
    }

    /**
     * Load only what the card view reads and the model is missing.
     *
     * Done at render time rather than on hydrate: Livewire re-fetches the
     * model on every request of this card with a bare query, which drops
     * the image and aggregates the parent listing loaded, but addToCart() is
     * renderless, so the one request a card makes on its own never needs
     * them.
     *
     * Inside a listing that loaded withCardData(), every check here is a
     * no-op and the card costs no queries of its own. Only the image and the
     * aggregates are loaded -- the card never reads `category` or the
     * variant rows, and loading `variants` here used to cost one query per
     * homepage card, simple products included.
     */
    private function loadCardData(): void
    {
        $this->product->loadMissing('cardImage');
        $this->product->loadCardAggregates();
    }

    /**
     * Add the product to the customer's permanent cart.
     *
     * CartService handles the database cart and stock validation.
     *
     * Renderless because nothing on the card changes when an item is added:
     * the toast and the cart icon react to the dispatched events (the same
     * reasoning as ProductDetails::addToCart()). Rendering here only
     * re-loaded the card's image and aggregates to redraw identical markup.
     */
    #[Renderless]
    public function addToCart(CartService $cartService)
    {
        // A product card has no variant picker, so a product sold by
        // variant cannot be added from here. Adding it would store an
        // item with no variant, which would then be priced from the
        // product instead of the variant and validated against the
        // product's stock instead of the variant's.
        //
        // Send the customer to the product page to choose a variant.
        if ($this->product->has_variants) {
            return $this->redirect(
                route('products.show', $this->product->slug),
                navigate: true
            );
        }

        // A cart belongs to a customer account, so guests are sent
        // to the login page first. After logging in they are returned
        // to this product's page to continue.
        if (! auth('customer')->check()) {
            session()->put('url.intended', route('products.show', $this->product->slug));
            session()->flash('status', 'Please log in to add products to your cart.');

            return $this->redirect(route('login'));
        }

        // Add one quantity of the selected product.
        // The cart is saved in the database instead of the session.
        $result = $cartService->addItem(
            $this->product->id,
            null,
            1
        );

        // If the product cannot be added because of stock
        // or another validation problem, show the error in the
        // popup instead of a flash message the page never renders.
        if (! $result['success']) {
            $this->dispatch(
                'cart-error',
                message: $result['message']
            );

            return;
        }

        // Tell the cart icon that the cart contents changed.
        $this->dispatch('cart-updated');

        // Trigger the Add to Cart popup on the current page.
        // This does not redirect the customer to the cart.
        $this->dispatch(
            'cart-added',
            message: $this->product->name.' has been added to your cart.'
        );
    }

    public function render()
    {
        $this->loadCardData();

        return view('livewire.product-card');
    }
}
