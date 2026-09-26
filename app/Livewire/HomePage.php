<?php

namespace App\Livewire;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Services\HomepageProductRankingService;
use Livewire\Component;

class HomePage extends Component
{
    public function render(HomepageProductRankingService $rankingService)
    {
        $banners = Banner::where('is_active', true)
            ->ordered()
            ->get();

        // withCardData(): everything the cards read, for all eight at once.
        // `category` is no longer eager loaded -- no card reads it.
        $featuredProducts = Product::where('is_active', true)
            ->featured()
            ->inStock()
            ->withCardData()
            ->limit(8)
            ->get();

        $categories = Category::where('is_active', true)
            ->sorted()
            ->withCount('products')
            ->limit(6)
            ->get();

        return view('livewire.home-page', [
            'banners' => $banners,
            'featuredProducts' => $featuredProducts,
            'categories' => $categories,
            'bestSellers' => $rankingService->bestSellers(),
            'topPicks' => $rankingService->topPicks(),
        ])->layout('components.layouts.front-end-layout');
    }
}
