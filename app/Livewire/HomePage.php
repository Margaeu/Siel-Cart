<?php

namespace App\Livewire;

use App\Models\Banner;
use App\Models\Product;
use Livewire\Component;
use App\Models\Category;

class HomePage extends Component
{
    public function render()
    {
        $banners = Banner::where('is_active', true)
            ->ordered()
            ->get();

        $featuredProducts = Product::where('is_active', true)
            ->featured()
            ->inStock()
            ->with(['category', 'cardImage'])
            ->limit(8)
            ->get();

        $categories = Category::where('is_active', true)
            ->sorted()
            ->withCount('products')
            ->limit(6)
            ->get(); 
        $newArrivals = Product::where('is_active', true)
            ->inStock()
            ->with(['category', 'cardImage'])
            ->latest()
            ->limit(8)
            ->get();

        return view('livewire.home-page',[
            'banners' => $banners,
            'featuredProducts' => $featuredProducts,
            'categories' => $categories,
            'newArrivals' => $newArrivals
        ])->layout('components.layouts.front-end-layout');
    }
}
