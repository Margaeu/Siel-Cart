<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Services\CartService;
use Livewire\Component;

class ProductDetails extends Component
{
    public Product $product;

    public $selectedVariant = null;

    public $quantity = 1;

    public $selectedImage = null;

    public int $reviewRating = 5;

    public string $reviewTitle = '';

    public string $reviewComment = '';

    public bool $canReview = false;

    public bool $hasReview = false;

    public bool $reviewIsApproved = false;

    public function mount($slug)
    {
        $this->product = Product::where('slug', $slug)
            ->with([
                'category',
                'generalImages',
                'primaryImage',
                // `variants.images` loads each variant's gallery. Loading
                // `images.variant` instead would walk the relation the wrong
                // way and still leave `$variant->images` lazy.
                'variants.images',
                'approvedReviews.customer',
            ])
            ->firstOrFail();

        // increment the views
        $this->product->incrementViews();

        // set the initial image
        $this->selectedImage = $this->defaultSharedImagePath();

        // select first variant if product has variants
        if ($this->product->has_variants) {
            $activeVariants = $this->product->variants->where('is_active', true);
            $initialVariant = $activeVariants->first(fn ($variant) => $variant->stock_quantity > 0)
                ?? $activeVariants->first();
            if ($initialVariant) {
                $this->selectVariant($initialVariant->id);
            }
        }

        $this->loadReviewState();
    }

    /**
     * Livewire re-fetches the model on every request and drops the relations
     * loaded in mount(), so restore them before any variant lookup runs.
     */
    public function hydrate(): void
    {
        $this->product?->loadMissing([
            'category',
            'generalImages',
            'primaryImage',
            'variants.images',
            // The page reads reviews_count and average_rating; without the
            // relation each read falls back to its own query.
            'approvedReviews.customer',
        ]);
    }

    public function selectVariant($variantId)
    {
        $variant = $this->product->variants->where('is_active', true)->find($variantId);
        if (! $this->product->has_variants || ! $variant) {
            return;
        }

        $this->selectedVariant = $variant->id;

        $variantImages = $this->product->variants->find($variantId)?->images ?? collect();

        if ($variantImages->isNotEmpty()) {
            $this->selectedImage = $variantImages->first()->image_path;

            return;
        }

        // The variant has no photos of its own. Fall back to the shared gallery
        // rather than leaving the previous variant's photo on screen.
        if (! $this->product->generalImages->contains('image_path', $this->selectedImage)) {
            $this->selectedImage = $this->defaultSharedImagePath();
        }
    }

    /**
     * The product-level image to show when no variant image applies.
     */
    private function defaultSharedImagePath(): ?string
    {
        return $this->product->primaryImage?->image_path
            ?? $this->product->generalImages->first()?->image_path;
    }

    /**
     * Thumbnails for the current selection: the shared product images plus the
     * selected variant's own images. Other variants' photos stay hidden, so
     * picking "Beige" never shows a blue or black thumbnail.
     */
    public function galleryImages()
    {
        $variantImages = $this->product->variants
            ->flatMap(fn ($variant) => $variant->images);

        return $this->product->generalImages
            ->concat($variantImages)
            ->unique('id')
            ->values();
    }

    public function selectImage($imagePath)
    {
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
    public function addToCart(CartService $cartService)
    {
        // A cart belongs to a customer account, so guests are sent
        // to the login page first and returned here afterwards.
        if (! auth('customer')->check()) {
            session()->put('url.intended', route('products.show', $this->product->slug));
            session()->flash('status', 'Please log in to add products to your cart.');

            return $this->redirect(route('login'));
        }

        if ($this->product->has_variants && ! $this->selectedVariant) {
            $this->dispatch('cart-error', message: 'Please select a variant.');

            return;
        }

        $result = $cartService->addItem(
            $this->product->id,
            $this->selectedVariant,
            (int) $this->quantity
        );

        // Stock validation failed.
        if (! $result['success']) {
            $this->dispatch('cart-error', message: $result['message']);

            return;
        }

        // Update the cart icon in the header.
        $this->dispatch('cart-updated');

        // Show the shared Add to Cart popup.
        $this->dispatch('cart-added', message: $result['message']);
    }

    public function submitReview(): void
    {
        if (! auth('customer')->check()) {
            session()->put('url.intended', route('products.show', $this->product->slug));
            session()->flash('status', 'Please log in to review this product.');
            $this->redirect(route('login'));

            return;
        }

        $customerId = (int) auth('customer')->id();
        $existingReview = Review::where('product_id', $this->product->id)
            ->where('customer_id', $customerId)
            ->first();

        if ($existingReview) {
            $this->loadReviewState();
            $this->addError('review', 'You have already reviewed this product.');

            return;
        }

        $orderId = $this->completedOrderId($customerId);

        if (! $orderId) {
            $this->addError('review', 'Only customers with a completed order can review this product.');

            return;
        }

        $validated = $this->validate([
            'reviewRating' => ['required', 'integer', 'between:1,5'],
            'reviewTitle' => ['nullable', 'string', 'max:255'],
            'reviewComment' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        Review::create([
            'product_id' => $this->product->id,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'rating' => $validated['reviewRating'],
            'title' => $validated['reviewTitle'] ?: null,
            'comment' => $validated['reviewComment'],
            'is_verified_purchase' => true,
            'is_approved' => false,
        ]);

        $this->reset('reviewTitle', 'reviewComment');
        $this->reviewRating = 5;
        $this->loadReviewState();
    }

    private function completedOrderId(int $customerId): ?int
    {
        return Order::where('customer_id', $customerId)
            ->where('status', 'completed')
            ->whereHas('items', fn ($query) => $query->where('product_id', $this->product->id))
            ->value('id');
    }

    private function loadReviewState(): void
    {
        $this->canReview = false;
        $this->hasReview = false;
        $this->reviewIsApproved = false;

        if (! auth('customer')->check()) {
            return;
        }

        $customerId = (int) auth('customer')->id();
        $review = Review::where('product_id', $this->product->id)
            ->where('customer_id', $customerId)
            ->first();

        if ($review) {
            $this->hasReview = true;
            $this->reviewIsApproved = $review->is_approved;

            return;
        }

        $this->canReview = $this->completedOrderId($customerId) !== null;
    }

    public function render()
    {
        $relatedProducts = Product::active()
            ->where('category_id', $this->product->category_id)
            ->where('id', '!=', $this->product->id)
            // Related cards render the same product-card view as any listing,
            // and that view reads the category name.
            ->with(['category', 'primaryImage', 'variants'])
            ->withReviewAggregates()
            ->limit(4)
            ->get();

        return view('livewire.product-details', [
            'relatedProducts' => $relatedProducts,
            'galleryImages' => $this->galleryImages(),
            'selectionInStock' => $this->product->is_active && ($this->product->has_variants
                ? (bool) $this->product->variants->contains(fn ($variant) =>
                    $variant->id == $this->selectedVariant && $variant->is_active && $variant->stock_quantity > 0)
                : $this->product->stock_status === 'in_stock'),
        ])->layout('components.layouts.front-end-layout', ['title' => $this->product->name.' - '.config('app.name')]);
    }
}
