<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class ProductListing extends Component
{
    /**
     * Products added by each "Load more".
     */
    public const PAGE_SIZE = 12;

    /**
     * How many of the matching products are on screen. The listing always
     * queries the first N rather than "page N", so products already shown
     * stay in place and in order when more are loaded -- page-based
     * pagination replaced the grid with the next twelve, and the view's
     * "Load more" button called a loadMore() that did not exist.
     *
     * Locked so a client cannot ask for the whole catalog in one request.
     */
    #[Locked]
    public int $visible = self::PAGE_SIZE;

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

    #[Url]
    public $inStock = false;

    public $priceRange = [0, 10000];

    public function mount()
    {
        // Set the price range over what a customer can actually buy: a simple
        // product's own price, or an active variant's price. A variable
        // product's own price column is not for sale, so it is not a ceiling.
        // This must match what inPriceRange() filters on, or the top of the
        // slider excludes products that the filter would have matched.
        $maxSimplePrice = Product::where('is_active', true)
            ->where('has_variants', false)
            ->max('price');

        $maxVariantPrice = ProductVariant::where('is_active', true)
            ->whereHas('product', fn (Builder $product) => $product->active())
            ->max('price');

        $maxProductPrice = max((float) $maxSimplePrice, (float) $maxVariantPrice) ?: 10000;
        $this->priceRange = [0, ceil($maxProductPrice)];

        if (empty($this->maxPrice)) {
            $this->maxPrice = $this->priceRange[1];
        }
    }

    public function loadMore(): void
    {
        $this->visible += self::PAGE_SIZE;
    }

    /**
     * A new filter or sort is a new result set, so it starts from the first
     * twelve again rather than keeping a count earned on the previous one.
     */
    private function resetVisible(): void
    {
        $this->visible = self::PAGE_SIZE;
    }

    public function updatingSearch()
    {
        $this->resetVisible();
    }

    public function updatingCategory()
    {
        $this->resetVisible();
    }

    public function updatedCategory(): void
    {
        $this->dispatch('category-changed', category: (string) $this->category);
    }

    public function updatingSort()
    {
        $this->resetVisible();
    }

    public function updatingFeatured()
    {
        $this->resetVisible();
    }

    public function updatingInStock()
    {
        $this->resetVisible();
    }

    public function applyPriceFilter()
    {
        $this->resetVisible();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'category', 'minPrice', 'maxPrice', 'featured', 'inStock']);
        $this->maxPrice = $this->priceRange[1];
        $this->resetVisible();
        $this->dispatch('category-changed', category: '');
    }

    public function render()
    {
        // withCardData() loads the image and the stock/review/price
        // aggregates each card reads, instead of every variant row of every
        // variable product on the page (which grew with each "Load more").
        $query = Product::query()
            ->active()
            ->withCardData();

        // Search. A variable product has no SKU of its own -- its SKUs live on
        // the variants -- so match an active variant's SKU too. Inactive
        // variants are hidden from the storefront and must not be findable.
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%')
                    ->orWhere('sku', 'like', '%'.$this->search.'%')
                    ->orWhereHas('variants', fn (Builder $variants) => $variants
                        ->active()
                        ->where('sku', 'like', '%'.$this->search.'%'));
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

        // Availability filter. inStock() reads a simple product's own stock and
        // a variable product's active variant stock, so a variable product
        // stays visible while any one of its variants can still be sold --
        // filtering on products.stock_quantity would hide it, because that
        // column means nothing once has_variants is set.
        if ($this->inStock) {
            $query->inStock();
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

        // Always page 1 of size N: total() and hasMorePages() still drive the
        // progress text and the button, and the first N rows are the ones
        // already on screen plus the newly loaded ones.
        $products = $query->paginate($this->visible, ['*'], 'page', 1);

        // Keep the "All Products" count independent from the filters applied
        // to the paginated listing. Using $products->total() in the sidebar
        // makes this number become the selected category's result count.
        $allProductsCount = Product::where('is_active', true)->count();

        // Count only what the listing itself will show, so the sidebar total
        // cannot claim more products than the category actually renders.
        $categories = Category::where('is_active', true)
            ->sorted()
            ->withCount(['products' => fn (Builder $products) => $products->active()])
            ->get();

        return view('livewire.product-listing', [
            'products' => $products,
            'categories' => $categories,
            'allProductsCount' => $allProductsCount,
        ])
            ->layout('components.layouts.front-end-layout');
    }
}
