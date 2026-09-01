<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Component;

class ProductDetails extends Component
{
    public Product $product;
    public $selectedVariant = null;
    public $quantity = 1;
    public $selectedImage = null;

    public function mount($slug){
        $this->product = Product::where('slug',$slug)
        ->with(['category','images','variants','approvedReviews.customer'])
        ->firstOrFail();

        // increment the views
        $this->product->incrementViews();

        // set the initial image 
        $this->selectedImage = $this->product->primaryImage?->image_path ?? $this->product->images->first()?->image_path;

        // select first variant if product has variants
        if ($this->product->has_variants && $this->product->variants->isNotEmpty()) {
            $this->selectedVariant = $this->product->variants->first()->id;
        }
    }

    public function selectVariant($variantId){
        $this->selectedVariant = $variantId;
    }
    public function selectImage($imagePath){
        $this->selectedImage = $imagePath;
    }

    public function incrementQuantity()
    {
        $this->quantity++;
    }
    public function decrementQuantity()
    {
        if ($this->quantity > 1) {
            $this->quantity--;
        }
    }

    /**
     * Add the selected product/variant to the customer's permanent cart.
     *
     * CartService handles the database cart and stock validation.
     */
    public function addToCart(CartService $cartService){
        // A cart belongs to a customer account, so guests are sent
        // to the login page first and returned here afterwards.
        if (!auth('customer')->check()) {
            session()->put('url.intended', route('products.show', $this->product->slug));
            session()->flash('status', 'Please log in to add products to your cart.');

            return $this->redirect(route('login'));
        }

        if ($this->product->has_variants && !$this->selectedVariant) {
            $this->dispatch('cart-error', message: 'Please select a variant.');
            return;
        }

        $result = $cartService->addItem(
            $this->product->id,
            $this->selectedVariant,
            (int) $this->quantity
        );

        // Stock validation failed.
        if (!$result['success']) {
            $this->dispatch('cart-error', message: $result['message']);
            return;
        }

        // Update the cart icon in the header.
        $this->dispatch('cart-updated');

        // Show the shared Add to Cart popup.
        $this->dispatch('cart-added', message: $result['message']);
    }
    public function render()
    {
        $relatedProducts = Product::active()
        ->where('category_id',$this->product->category_id)
        ->where('id', '!=', $this->product->id)
        ->with(['primaryImage'])
        ->limit(4)
        ->get();

        return view('livewire.product-details',[
            'relatedProducts' => $relatedProducts
        ])->layout('components.layouts.front-end-layout',['title' => $this->product->name . ' - ' . config('app.name')]);
    }
}
