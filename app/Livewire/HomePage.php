<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;

class HomePage extends Component
{
    public function render()
    {
        $featuredProducts = Product::active()
            ->featured()
            ->inStock()
            ->with(['category', 'primaryImage', 'variants'])
            ->withReviewAggregates()
            ->limit(8)
            ->get();
        // Count only the products a customer can reach, so a category tile
        // cannot advertise more than the listing behind it will show.
        $categories = Category::active()
            ->sorted()
            ->withCount(['products' => fn (Builder $products) => $products->active()])
            ->limit(6)
            ->get();
        $newArrivals = Product::active()
            ->inStock()
            ->with(['category', 'primaryImage', 'variants'])
            ->withReviewAggregates()
            ->latest()
            ->limit(8)
            ->get();

        return view('livewire.home-page',[
            'featuredProducts' => $featuredProducts,
            'categories' => $categories,
            'newArrivals' => $newArrivals
        ])->layout('components.layouts.front-end-layout');
    }
}
