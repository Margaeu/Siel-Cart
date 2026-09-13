<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Services\CartService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductDetails extends Component
{
    use WithFileUploads;

    public Product $product;

    public $selectedVariant = null;

    public $quantity = 1;

    public $selectedImage = null;

    public int $reviewRating = 5;

    public string $reviewTitle = '';

    public string $reviewComment = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $reviewPhotos = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $reviewVideo = null;

    public bool $canReview = false;

    public bool $hasReview = false;

    public bool $reviewIsApproved = false;

    /**
     * IDs of reviews the logged-in customer has already reported, so the
     * "Report" button can be swapped out for a "Reported" note instead of
     * letting them file the same report twice.
     */
    public array $reportedReviewIds = [];

    public bool $showReportForm = false;

    public ?int $reportingReviewId = null;

    public string $reportReason = '';

    public string $reportDetails = '';

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
            // Up to 5 photos, standard image types, 5MB each.
            'reviewPhotos' => ['nullable', 'array', 'max:5'],
            'reviewPhotos.*' => ['image', 'max:5120'],
            // A single short clip. Exact duration (~1 minute) is enforced
            // client-side before upload; this is the server-side backstop
            // (type + a generous size cap, since duration can't be reliably
            // read without a media-processing library).
            'reviewVideo' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:51200'],
        ]);

        $photoPaths = collect($this->reviewPhotos)
            ->map(fn ($photo) => $photo->store("reviews/{$this->product->id}/{$customerId}/photos", 'r2'))
            ->values()
            ->all();

        $videoPath = $this->reviewVideo?->store("reviews/{$this->product->id}/{$customerId}/video", 'r2');

        Review::create([
            'product_id' => $this->product->id,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'rating' => $validated['reviewRating'],
            'title' => $validated['reviewTitle'] ?: null,
            'comment' => $validated['reviewComment'],
            'photos' => $photoPaths ?: null,
            'video_path' => $videoPath,
            'is_verified_purchase' => true,
            'is_approved' => true,
            $this->product->load('approvedReviews.customer')
        ]);

        $this->reset('reviewTitle', 'reviewComment', 'reviewPhotos', 'reviewVideo');
        $this->reviewRating = 5;
        $this->loadReviewState();
    }

    /**
     * Open the small inline "report this user" form for a given review.
     */
    public function startReport(int $reviewId): void
    {
        if (! auth('customer')->check()) {
            session()->put('url.intended', route('products.show', $this->product->slug));
            session()->flash('status', 'Please log in to report a review.');
            $this->redirect(route('login'));

            return;
        }

        $this->reportingReviewId = $reviewId;
        $this->reportReason = '';
        $this->reportDetails = '';
        $this->showReportForm = true;
    }

    public function cancelReport(): void
    {
        $this->showReportForm = false;
        $this->reportingReviewId = null;
    }

    /**
     * File a report against the author of a review. Goes straight to the
     * admin queue (Filament "Reports" resource) for moderation.
     */
    public function submitReport(): void
    {
        if (! auth('customer')->check()) {
            $this->redirect(route('login'));

            return;
        }

        $customerId = (int) auth('customer')->id();
        $review = Review::find($this->reportingReviewId);

        if (! $review) {
            $this->cancelReport();

            return;
        }

        if ($review->customer_id === $customerId) {
            $this->addError('report', 'You cannot report your own review.');

            return;
        }

        if (in_array($review->id, $this->reportedReviewIds, true)) {
            $this->addError('report', 'You have already reported this review.');

            return;
        }

        $validated = $this->validate([
            'reportReason' => ['required', 'string', 'max:255'],
            'reportDetails' => ['nullable', 'string', 'max:1000'],
        ]);

        Report::create([
            'reporter_customer_id' => $customerId,
            'reported_customer_id' => $review->customer_id,
            'review_id' => $review->id,
            'reason' => $validated['reportReason'],
            'details' => $validated['reportDetails'] ?: null,
        ]);

        $this->reportedReviewIds[] = $review->id;
        $this->showReportForm = false;
        $this->reportingReviewId = null;
        $this->reset('reportReason', 'reportDetails');

        session()->flash('report-status', 'Thanks — this has been reported to the admin team.');
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
        $this->reportedReviewIds = [];

        if (! auth('customer')->check()) {
            return;
        }

        $customerId = (int) auth('customer')->id();

        $this->reportedReviewIds = Report::where('reporter_customer_id', $customerId)
            ->whereIn('review_id', $this->product->approvedReviews->pluck('id'))
            ->pluck('review_id')
            ->all();

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
            ->with(['category', 'cardImage', 'variants'])
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