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
    public bool $reviewIsApproved = true;
    // Reporting Properties
    public bool $showReportForm = false;
    public ?int $reportingReviewId = null;
    public string $reportReason = '';
    public string $reportDetails = '';
    public array $reportedReviewIds = [];
    
    public function mount($slug)
    {
        $this->product = Product::where('slug', $slug)
            ->with([
                'category',
                'generalImages',
                'primaryImage',
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

    public function hydrate(): void
    {
        $this->product?->loadMissing([
            'category',
            'generalImages',
            'primaryImage',
            'variants.images',
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

        if (! $this->product->generalImages->contains('image_path', $this->selectedImage)) {
            $this->selectedImage = $this->defaultSharedImagePath();
        }
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

    public function addToCart(CartService $cartService)
    {
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

        if (! $result['success']) {
            $this->dispatch('cart-error', message: $result['message']);

            return;
        }

        $this->dispatch('cart-updated');
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
            'reviewPhotos' => ['nullable', 'array', 'max:5'],
            'reviewPhotos.*' => ['image', 'max:5120'],
            'reviewVideo' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:51200'],
        ], [
            'reviewPhotos.max' => 'You can attach up to 5 photos.',
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
            'video' => $videoPath,
            'is_verified_purchase' => true,
            'is_approved' => true,
        ]);

        $this->reset('reviewTitle', 'reviewComment', 'reviewPhotos', 'reviewVideo');
        $this->reviewRating = 5;

        // Force reload the reviews relationship so the new review is visible instantly
        $this->product->load('approvedReviews.customer');

        $this->loadReviewState();
    }

    public function removeReviewPhoto(int $index): void
    {
        unset($this->reviewPhotos[$index]);
        $this->reviewPhotos = array_values($this->reviewPhotos);
    }

    public function removeReviewVideo(): void
    {
        $this->reviewVideo = null;
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

        // 1. Fetch the review being reported to obtain the target customer's ID
        $review = Review::find($this->reportingReviewId);

        if (! $review) {
            $this->addError('reportReason', 'The review you are trying to report no longer exists.');
            return;
        }

        // 2. Insert record into database using foreign key constraints from your migration
        Report::create([
            'reporter_customer_id' => auth('customer')->id(),
            'reported_customer_id' => $review->customer_id,
            'review_id'            => $review->id,
            'reason'               => $this->reportReason,
            'details'              => $this->reportDetails ?: null,
            'status'               => 'pending',
        ]);

        // 3. Mark in state array so the UI renders "Reported to admin" immediately
        $this->reportedReviewIds[] = $this->reportingReviewId;

        session()->flash('report-status', 'Report submitted successfully. Thank you for helping keep our community safe.');
        $this->cancelReport();
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
        $this->reviewIsApproved = true;

        if (! auth('customer')->check()) {
            return;
        }

        $customerId = (int) auth('customer')->id();

        // Hydrate previously reported review IDs for this session user
        $this->reportedReviewIds = Report::where('reporter_customer_id', $customerId)
            ->pluck('review_id')
            ->toArray();

        $review = Review::where('product_id', $this->product->id)
            ->where('customer_id', $customerId)
            ->first();

        if ($review) {
            $this->hasReview = true;

            return;
        }

        $this->canReview = $this->completedOrderId($customerId) !== null;
    }

    public function render()
    {
        $relatedProducts = Product::active()
            ->where('category_id', $this->product->category_id)
            ->where('id', '!=', $this->product->id)
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