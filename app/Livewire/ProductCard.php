<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;

class ProductCard extends Component
{
    public Product $product;
    public function addToCart(){
        if ($this->product->stock_status !== 'in_stock') {
            session()->flash('error','This product is current;y out of stock');
            return;
        }
         // Get current cart from session
         $cart = session()->get('cart', []);
        
         $cartKey = 'product_' . $this->product->id;
 
         // If product already in cart, increment quantity
         if (isset($cart[$cartKey])) {
             $cart[$cartKey]['quantity']++;
         } else {
             // Add new product to cart
             $cart[$cartKey] = [
                 'product_id' => $this->product->id,
                 'variant_id' => null,
                 'name' => $this->product->name,
                 'variant_name' => null,
                 'price' => $this->product->price,
                 'image' => $this->product->primaryImage?->image_path,
                 'quantity' => 1,
             ];
         }
 
         // Save cart to session
         session()->put('cart', $cart);
 
         // Dispatch event to update cart icon
         $this->dispatch('cart-updated');

         session()->flash('success',$this->product->name . ' has been added to your cart.');
    }
    public function render()
    {
        return view('livewire.product-card');
    }
}
