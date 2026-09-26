<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Services\CartService;
use App\Services\HomepageProductRankingService;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ProductDetails extends Component
{
    use WithFileUploads, WithPagination;

    /**
     * Reviews shown per page. The page used to load and render every
     * approved review (with its customer) on mount *and* again on every
     * Livewire request -- a photo upload, a report click -- so its cost grew
     * with the product's review count.
     */
    public const REVIEWS_PER_PAGE = 10;

    /**
     * The review list's own page name, so paging reviews never collides
     * with any other paginator or query-string state on the page.
     */
    public const REVIEWS_PAGE_NAME = 'reviewsPage';

    public Product $product;

    public $selectedVariant = null;

    public $selectedImage = null;

    public int $reviewRating = 5;

    public string $reviewTitle = '';

    public string $reviewComment = '';

    /**
     * Temporary holding array for newly chosen files before appending
     *
     * @var TemporaryUploadedFile[]
     */
    public array $newReviewPhotos = [];

    /**
     * Persistent accumulated array of photos (up to 5 photos, max 10 MB each)
     *
     * @var TemporaryUploadedFile[]
     */
    public array $reviewPhotos = [];

    /** @var TemporaryUploadedFile|null */
    public $reviewVideo = null;

    public bool $canReview = false;

    public bool $hasReview = false;

    public bool $reviewIsApproved = true;

    // Reporting Properties
    public bool $showReportForm = false;

    public ?int $reportingReviewId = null;

    public string $reportReason = '';

    public string $reportDetails = '';

    public array $reportedReviewIds = [];

    public function mount($slug)
    {
        // active(): an inactive product is unpublished and must 404 like a
        // soft-deleted one (which the default scope already hides). Admins
        // inspect both from the panel, not through this page.
        $this->product = Product::where('slug', $slug)
            ->active()
            ->with([
                'category',
                'generalImages',
                'primaryImage',
                'variants.images',
            ])
            // The rating summary and review count read these aggregates;
            // the reviews themselves are paged in render().
            ->withReviewAggregates()
            ->firstOrFail();

        // Increment views
        $this->product->incrementViews();

        // Set initial image
        $this->selectedImage = $this->defaultSharedImagePath();

        // Select first variant if product has variants
        if ($this->product->has_variants) {
            $activeVariants = $this->product->variants->where('is_active', true);
            $initialVariant = $activeVariants->first(fn ($variant) => $variant->stock_quantity > 0)
                ?? $activeVariants->first();
            if ($initialVariant) {
                $this->selectInitialVariant($initialVariant->id);
            }
        }

        $this->loadReviewState();
    }

    /**
     * Livewire re-fetches the product with a bare query on every request.
     * The approved reviews are no longer part of this -- render() pages them
     * -- and the rating/count aggregates come back in one query instead of
     * re-hydrating every review and its customer.
     */
    public function hydrate(): void
    {
        $this->product?->loadMissing([
            'category',
            'generalImages',
            'primaryImage',
            'variants.images',
        ]);

        $this->product?->loadCardAggregates();
    }

    /**
     * Pick the variant the page opens on.
     *
     * Private on purpose: switching variant is no longer a server action.
     * It used to be a wire:click, and every tap paid for a full round trip
     * that re-fetched the product, reloaded its reviews and images, re-ran
     * the related-products query and re-rendered four nested product cards
     * -- about a second to move one highlight. The picker is Alpine now
     * (see product-details.blade.php); this only seeds the first render, and
     * the client's choice is re-validated in addToCart().
     */
    private function selectInitialVariant($variantId): void
    {
        $variant = $this->product->variants->where('is_active', true)->find($variantId);
        if (! $this->product->has_variants || ! $variant) {
            return;
        }

        $this->selectedVariant = $variant->id;

        $variantImages = $variant->images;

        if ($variantImages->isNotEmpty()) {
            $this->selectedImage = $variantImages->first()->image_path;

            return;
        }

        if (! $this->product->generalImages->contains('image_path', $this->selectedImage)) {
            $this->selectedImage = $this->defaultSharedImagePath();
        }
    }

    /**
     * Resolve the variant id the browser reports as selected.
     *
     * Selection lives in Alpine, so the id is client input and arrives on the
     * one request that needs it. It is matched against this product's own
     * active variants before it goes any further: a stale or forged id must
     * never reach CartService, which would price the line from a variant of
     * some other product and check it against that variant's stock.
     */
    private function resolveVariantId($variantId): ?int
    {
        // A simple product is never sold by variant -- CartService prices it
        // from the product itself, so discard whatever the browser sent.
        if (! $this->product->has_variants) {
            return null;
        }

        // Fall back to the server's own seeded choice when the browser sends
        // nothing, so a customer with JS disabled still adds the variant the
        // page rendered as selected.
        $variantId = filter_var($variantId, FILTER_VALIDATE_INT) ?: $this->selectedVariant;

        return $this->product->variants
            ->first(fn ($variant) => $variant->id === (int) $variantId && $variant->is_active)
            ?->id;
    }

    private function defaultSharedImagePath(): ?string
    {
        return $this->product->primaryImage?->image_path
            ?? $this->product->generalImages->first()?->image_path;
    }

    public function galleryImages()
    {
        $variantImages = $this->product->variants
            ->flatMap(fn ($variant) => $variant->images);

        return $this->product->generalImages
            ->concat($variantImages)
            ->unique('id')
            ->values();
    }

    /**
     * Quantity is owned by Alpine in the view and handed over only here.
     * The +/- buttons used to be wire:click calls, so every tap was a full
     * round trip that re-hydrated the product with its reviews and
     * re-rendered the related product cards -- about a second per tap.
     *
     * Renderless because nothing on the page changes when an item is added:
     * the toast and the cart icon react to the dispatched events. Rendering
     * here repeated that same whole-page work before the toast could show.
     */
    #[Renderless]
    public function addToCart($quantity = 1, $variantId = null)
    {
        $cartService = app(CartService::class);

        // Client input: never trust it to be a positive integer.
        // CartService still rejects anything over the available stock.
        $quantity = max(1, (int) $quantity);

        // Same for the variant: the picker is client-side, so this is the one
        // request that carries the choice and the only place it is checked.
        $variantId = $this->resolveVariantId($variantId);

        if (! auth('customer')->check()) {
            session()->put('url.intended', route('products.show', $this->product->slug));
            session()->flash('status', 'Please log in to add products to your cart.');

            return $this->redirect(route('login'));
        }

        if ($this->product->has_variants && ! $variantId) {
            $this->dispatch('cart-error', message: 'Please select a variant.');

            return;
        }

        // Keep the server's copy in step, so a later full render (a review
        // submission, say) reopens on the variant the customer is looking at.
        $this->selectedVariant = $variantId;

        $result = $cartService->addItem(
            $this->product->id,
            $variantId,
            $quantity
        );

        if (! $result['success']) {
            $this->dispatch('cart-error', message: $result['message']);

            return;
        }

        $this->dispatch('cart-updated');
        $this->dispatch('cart-added', message: $result['message']);
    }

    /**
     * Appends freshly picked photos without wiping existing ones,
     * validating each at a 10 MB ceiling and enforcing a 5-photo maximum.
     */
    public function updatedNewReviewPhotos(): void
    {
        $this->validate([
            'newReviewPhotos.*' => ['image', 'max:10240'],
        ], [
            'newReviewPhotos.*.image' => 'Each file must be a valid image format.',
            'newReviewPhotos.*.max' => 'Each photo must not exceed 10 MB.',
        ]);

        foreach ($this->newReviewPhotos as $photo) {
            if (count($this->reviewPhotos) < 5) {
                $this->reviewPhotos[] = $photo;
            }
        }

        // Reset buffer so the input can accept more photos if limit is not reached
        $this->reset('newReviewPhotos');
    }

    public function removeReviewPhoto(int $index): void
    {
        if (isset($this->reviewPhotos[$index])) {
            unset($this->reviewPhotos[$index]);
            $this->reviewPhotos = array_values($this->reviewPhotos);
        }
    }

    public function removeReviewVideo(): void
    {
        $this->reviewVideo = null;
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

        // completedOrderId() already excludes orders already reviewed for this
        // product, so null here means either no completed order contains it,
        // or every such order has already been reviewed.
        $orderId = $this->completedOrderId($customerId);

        if (! $orderId) {
            $this->loadReviewState();
            $this->addError('review', 'Only customers with a completed order can review this product.');

            return;
        }

        $validated = $this->validate([
            'reviewRating' => ['required', 'integer', 'between:1,5'],
            'reviewTitle' => ['nullable', 'string', 'max:255'],
            'reviewComment' => ['required', 'string', 'min:10', 'max:2000'],
            'reviewPhotos' => ['nullable', 'array', 'max:5'],
            'reviewPhotos.*' => ['image', 'max:10240'],
            'reviewVideo' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:10240'],
        ], [
            'reviewPhotos.max' => 'You can attach up to 5 photos.',
            'reviewPhotos.*.max' => 'Each photo must not exceed 10 MB.',
            'reviewVideo.max' => 'The video must not exceed 10 MB.',
        ]);

        $photoPaths = collect($this->reviewPhotos)
            ->map(fn ($photo) => $photo->store('reviews/photos', 'r2'))
            ->all();

        $videoPath = $this->reviewVideo?->store('reviews/videos', 'r2');

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
        ]);

        $this->reset('reviewTitle', 'reviewComment', 'reviewPhotos', 'newReviewPhotos', 'reviewVideo');
        $this->reviewRating = 5;

        // Reviews are auto-approved, so the new one is already in the paged
        // list. Refresh the rating/count aggregates the summary reads, and go
        // back to the first page -- the list is newest first, so that is
        // where the customer's review appears immediately.
        $this->product->loadCardAggregates(force: true);
        $this->resetPage(self::REVIEWS_PAGE_NAME);

        $this->loadReviewState();
    }

    public function startReport(int $reviewId): void
    {
        $this->reportingReviewId = $reviewId;
        $this->showReportForm = true;
        $this->reportReason = '';
        $this->reportDetails = '';
    }

    public function cancelReport(): void
    {
        $this->showReportForm = false;
        $this->reportingReviewId = null;
        $this->reportReason = '';
        $this->reportDetails = '';
    }

    public function submitReport(): void
    {
        $this->validate([
            'reportReason' => ['required', 'string'],
            'reportDetails' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! auth('customer')->check()) {
            session()->flash('status', 'Please log in to report a review.');

            return;
        }

        if (! $this->reportingReviewId) {
            $this->cancelReport();

            return;
        }

        $review = Review::find($this->reportingReviewId);

        if (! $review) {
            $this->addError('reportReason', 'The review you are trying to report no longer exists.');

            return;
        }

        Report::create([
            'reporter_customer_id' => auth('customer')->id(),
            'reported_customer_id' => $review->customer_id,
            'review_id' => $review->id,
            'reason' => $this->reportReason,
            'details' => $this->reportDetails ?: null,
            'status' => 'pending',
        ]);

        $this->reportedReviewIds[] = $this->reportingReviewId;

        session()->flash('report-status', 'Report submitted successfully. Thank you for helping keep our community safe.');
        $this->cancelReport();
    }

    /**
     * The customer's oldest completed order that contains this product and
     * does not already have a review from them for it -- i.e. the order a
     * new review submission should be attached to. Ordering by a repeat
     * purchase creates a second completed order for the same product, and
     * that order is reviewable on its own even though an earlier order for
     * the same product was already reviewed.
     */
    private function completedOrderId(int $customerId): ?int
    {
        // NOT IN with a NULL in the list matches no rows in SQL, so nulls
        // (a review with no surviving order) must be filtered out first.
        $reviewedOrderIds = Review::where('product_id', $this->product->id)
            ->where('customer_id', $customerId)
            ->pluck('order_id')
            ->filter()
            ->all();

        return Order::where('customer_id', $customerId)
            ->where('status', 'completed')
            ->whereHas('items', fn ($query) => $query->where('product_id', $this->product->id))
            ->whereNotIn('id', $reviewedOrderIds)
            ->value('id');
    }

    private function loadReviewState(): void
    {
        $this->canReview = false;
        $this->hasReview = false;
        $this->reviewIsApproved = true;

        if (! auth('customer')->check()) {
            return;
        }

        $customerId = (int) auth('customer')->id();

        $this->reportedReviewIds = Report::where('reporter_customer_id', $customerId)
            ->pluck('review_id')
            ->toArray();

        if ($this->completedOrderId($customerId) !== null) {
            $this->canReview = true;

            return;
        }

        // No order left to attach a new review to. Only say "already
        // reviewed" if that's actually why -- a customer with no completed
        // order at all for this product should see the generic prompt
        // instead of a message implying they've reviewed it before.
        $this->hasReview = Review::where('product_id', $this->product->id)
            ->where('customer_id', $customerId)
            ->exists();
    }

    /**
     * "Products You May Also Like" -- same category first, topped up from
     * other categories so a product that is the only one in its category
     * still gets a full row instead of an empty section.
     */
    private function relatedProducts()
    {
        $relatedProducts = Product::where('is_active', true)
            ->where('category_id', $this->product->category_id)
            ->where('id', '!=', $this->product->id)
            ->withCardData()
            ->limit(4)
            ->get();

        $remaining = 4 - $relatedProducts->count();

        if ($remaining > 0) {
            $otherCategoryProducts = Product::where('is_active', true)
                ->where('category_id', '!=', $this->product->category_id)
                ->whereNotIn('id', $relatedProducts->pluck('id'))
                ->withCardData()
                ->inRandomOrder()
                ->limit($remaining)
                ->get();

            $relatedProducts = $relatedProducts->concat($otherCategoryProducts);
        }

        return $relatedProducts;
    }

    public function render(HomepageProductRankingService $rankingService)
    {
        $relatedProducts = $this->relatedProducts();

        // Upper bound for the client-side quantity stepper. Only a UX cap --
        // CartService re-checks stock (including what is already in the cart).
        $maxQuantity = (int) ($this->product->has_variants
            ? $this->product->variants->firstWhere('id', $this->selectedVariant)?->stock_quantity
            : $this->product->stock_quantity);

        // Everything the variant/image picker can change, emitted once with the
        // page so Alpine can drive it without asking the server again. Variant
        // keys are cast to string so json_encode always yields an object,
        // never a positional array.
        $galleryImages = $this->galleryImages();

        $variantOptions = $this->product->variants
            ->where('is_active', true)
            ->mapWithKeys(fn ($variant) => [(string) $variant->id => [
                'price' => '₱'.number_format((float) $variant->price, 2),
                'stock' => (int) $variant->stock_quantity,
                'sku' => $variant->sku,
                'image' => $variant->images->first()?->image_path,
            ]])
            ->all();

        // One bounded page of approved reviews. Newest first, with the id
        // breaking ties between reviews created in the same second, so pages
        // never overlap or skip -- the old unpaged list had no order at all.
        // Only approved reviews are listed, so an admin un-approving one
        // removes it here on the next render.
        $reviews = $this->product->approvedReviews()
            ->with('customer')
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::REVIEWS_PER_PAGE, pageName: self::REVIEWS_PAGE_NAME);

        return view('livewire.product-details', [
            'reviews' => $reviews,
            'isBestSeller' => $rankingService->bestSellers()->contains('id', $this->product->id),
            'isTopPick' => $rankingService->topPicks()->contains('id', $this->product->id),
            'relatedProducts' => $relatedProducts,
            'galleryImages' => $galleryImages,
            'variantOptions' => $variantOptions,
            'imageUrls' => $galleryImages->mapWithKeys(fn ($image) => [$image->image_path => $image->url])->all(),
            // The shared (non-variant) photos, so the picker can tell whether
            // the photo on screen still applies after a switch.
            'sharedImagePaths' => $this->product->generalImages->pluck('image_path')->values()->all(),
            'defaultImagePath' => $this->defaultSharedImagePath(),
            'maxQuantity' => max(1, $maxQuantity),
            'selectionInStock' => $this->product->is_active && ($this->product->has_variants
                ? (bool) $this->product->variants->contains(fn ($variant) => $variant->id == $this->selectedVariant && $variant->is_active && $variant->stock_quantity > 0)
                : $this->product->stock_status === 'in_stock'),
        ])->layout('components.layouts.front-end-layout', ['title' => $this->product->name.' - '.config('app.name')]);
    }
}
