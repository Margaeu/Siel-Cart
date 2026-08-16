<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

class CartPage extends Component
{
    public $cart = [];
    public function mount(){
        $this->loadCart();
    }

    public function loadCart(){
        $this->cart = session()->get('cart',[]);
    }

    public function updateQuantity($cartKey, $quantity){
        if ($quantity < 1) {
            return;
        }

        $cart = session()->get('cart',[]);
        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] = $quantity;
            session()->put('cart',$cart);
            $this->loadCart();
            $this->dispatch('cart-updated');
        }
    }

    public function removeItem($cartKey){
        $cart = session()->get('cart',[]);
        if (isset($cart[$cartKey])) {
            unset($cart[$cartKey]);
            session()->put('cart',$cart);
            $this->loadCart();
            $this->dispatch('cart-updated');

            session()->flash('success','Item removed from cart');
        }
    }

    public function clearCart()
    {
        session()->forget('cart');
        $this->loadCart();
        $this->dispatch('cart-updated');
        
        session()->flash('success', 'Cart cleared');
    }
    #[Computed]
    public function subtotal(){
        return array_sum(array_map(function ($item){
            return $item['price'] * $item['quantity'];
        },$this->cart));
    }
    public function render()
    {
        return view('livewire.cart-page')->layout('components.layouts.front-end-layout', ['title' => 'Shopping Cart']);
    }
}
