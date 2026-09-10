<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Component;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class ProductListing extends Component
{
    use WithPagination;

    #[Url]
    public $category = '';
    #[Url]
    public $search = '';
    #[Url]
    public $minPrice = '';
    #[Url]
    public $maxPrice = '';
    #[Url]
    public $sort = 'newest';
    #[Url]
    public $featured = '';
    public $priceRange = [0, 10000];

    public function mount()
    {
        // Set the price range over what a customer can actually buy: a simple
        // product's own price, or an active variant's price. A variable
        // product's own price column is not for sale, so it is not a ceiling.
        // This must match what inPriceRange() filters on, or the top of the
        // slider excludes products that the filter would have matched.
        $maxSimplePrice = Product::active()
            ->where('has_variants', false)
            ->max('price');

        $maxVariantPrice = ProductVariant::active()
            ->whereHas('product', fn (Builder $product) => $product->active())
            ->max('price');

        $maxProductPrice = max((float) $maxSimplePrice, (float) $maxVariantPrice) ?: 10000;
        $this->priceRange = [0, ceil($maxProductPrice)];

        if (empty($this->maxPrice)) {
            $this->maxPrice = $this->priceRange[1];
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCategory()
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->dispatch('category-changed', category: (string) $this->category);
    }

    public function updatingSort()
    {
        $this->resetPage();
    }

    public function applyPriceFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'category', 'minPrice', 'maxPrice', 'featured']);
        $this->maxPrice = $this->priceRange[1];
        $this->resetPage();
        $this->dispatch('category-changed', category: '');
    }

    public function render()
    {
        $query = Product::query()
            ->active()
            ->with(['category', 'cardImage', 'variants'])
            ->withReviewAggregates();

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        // Category filter. Matching the slug inside the query means an unknown
        // slug returns nothing, rather than dropping the filter and showing the
        // whole catalog under a category heading.
        if ($this->category) {
            $query->whereRelation('category', 'slug', $this->category);
        }

        // Price range filter. inPriceRange() reads a simple product's own price
        // and a variable product's active variant prices -- filtering on
        // products.price would hide variable products whose variants are in
        // range because their own price column is not.
        if ($this->minPrice !== '' || $this->maxPrice !== '') {
            $min = (float) ($this->minPrice ?: 0);
            $max = (float) ($this->maxPrice ?: $this->priceRange[1]);
            $query->inPriceRange($min, $max);
        }

        // Featured filter
        if ($this->featured) {
            $query->featured();
        }

        // Sorting. Price sorts go through orderByDisplayPrice() so a variable
        // product sorts on its cheapest active variant -- the number the card
        // shows -- instead of its own price column.
        //
        // Every branch ends on the id. Products share created_at, names and
        // view counts, and a tie has no defined order, so without it the same
        // product can land on two pages or on none as the customer pages.
        match ($this->sort) {
            'price_low' => $query->orderByDisplayPrice('asc'),
            'price_high' => $query->orderByDisplayPrice('desc'),
            'name_asc' => $query->orderBy('name', 'asc')->orderBy('id'),
            'name_desc' => $query->orderBy('name', 'desc')->orderBy('id'),
            'popular' => $query->orderBy('views_count', 'desc')->orderBy('id'),
            default => $query->latest()->orderBy('id', 'desc'),
        };

        $products = $query->paginate(12);

        // Count only what the listing itself will show, so the sidebar total
        // cannot claim more products than the category actually renders.
        $categories = Category::active()
            ->sorted()
            ->withCount(['products' => fn (Builder $products) => $products->active()])
            ->get();

        return view('livewire.product-listing', [
            'products' => $products,
            'categories' => $categories,
        ])
            ->layout('components.layouts.front-end-layout');
    }
}
