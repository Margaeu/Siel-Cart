<div class="bg-gray-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="mb-6 text-sm">
            <ol class="flex items-center gap-2">
                <li><a href="{{ route('home') }}" class="text-gray-500 hover:opacity-80 transition">Home</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="{{ route('products.index') }}" class="text-gray-500 hover:opacity-80 transition">Shop</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="{{ route('products.index', ['category' => $product->category->slug]) }}" 
                       class="text-gray-500 hover:opacity-80 transition">{{ $product->category->name }}</a></li>
                <li class="text-gray-400">/</li>
                <li class="text-gray-900 font-medium">{{ $product->name }}</li>
            </ol>
        </nav>

        <!-- Product Detail -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
            <div class="lg:grid lg:grid-cols-2 lg:gap-8 p-8">
                <!-- Images -->
                <div>
                    <!-- Main Image -->
                    <div class="aspect-square rounded-lg overflow-hidden bg-gray-100 mb-4">
                        @if($selectedImage)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('r2')->url($selectedImage) }}"
                                 alt="{{ $product->name }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-300 to-gray-400">
                                <span class="text-9xl text-gray-500">{{ substr($product->name, 0, 1) }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Thumbnail Images: shared images + the selected variant's own -->
                    @if($galleryImages->count() > 1)
                        <div class="grid grid-cols-4 gap-4">
                            @foreach($galleryImages as $image)
                                <button wire:click="selectImage('{{ $image->image_path }}')"
                                        style="{{ $selectedImage === $image->image_path ? 'border-color: var(--color-primary);' : '' }}"
                                        class="aspect-square rounded-lg overflow-hidden border-2 {{ $selectedImage === $image->image_path ? '' : 'border-gray-200' }} hover:opacity-80 transition">
                                    <img src="{{ $image->url }}"
                                         alt="{{ $product->name }}"
                                         class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Product Info -->
                <div>
                    <!-- Badges -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        @if($product->is_featured)
                            <span class="bg-amber-100 text-amber-800 text-sm font-semibold px-3 py-1 rounded">
                                Featured
                            </span>
                        @endif
                        @if($selectionInStock)
                            <span class="bg-emerald-100 text-emerald-800 text-sm font-semibold px-3 py-1 rounded">
                                In Stock
                            </span>
                        @else
                            <span class="bg-red-100 text-red-800 text-sm font-semibold px-3 py-1 rounded">
                                Out of Stock
                            </span>
                        @endif
                    </div>

                    <!-- Brand -->
                    @if($product->brand)
                        <p class="text-sm text-gray-500 mb-2">{{ $product->brand->name }}</p>
                    @endif

                    <!-- Title -->
                    <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $product->name }}</h1>

                    <!-- Rating -->
                    @if($product->reviews_count > 0)
                        <div class="flex items-center gap-2 mb-4">
                            <div class="flex text-amber-400">
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= floor($product->average_rating))
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                            <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 fill-current text-gray-300" viewBox="0 0 20 20">
                                            <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                        </svg>
                                    @endif
                                @endfor
                            </div>
                            <span class="text-gray-600">{{ number_format($product->average_rating, 1) }} ({{ $product->reviews_count }} reviews)</span>
                        </div>
                    @endif

                    <!-- Price -->
                    <div class="mb-6">
                        @if($selectedVariant)
                            @php
                                $variant = $product->variants->find($selectedVariant);
                            @endphp
                            <span class="text-3xl font-bold text-gray-900">₱{{ number_format($variant->price, 2) }}</span>
                        @else
                            {{--
                                Reached by simple products, and by a variable
                                product with no active variant at all -- mount()
                                selects an inactive-stock variant if one exists,
                                so only an empty active set falls through here.
                                display_price_label prints the plain price for
                             the first case and "Unavailable" for the second,
                                   where the product's own price column names a
                                figure nothing can be bought at.
                            --}}
                            <span class="text-3xl font-bold text-gray-900">{{ $product->display_price_label }}</span>
                        @endif
                    </div>

                    <!-- Short Description -->
                    @if($product->short_description)
                        <p class="text-gray-600 mb-6">{{ $product->short_description }}</p>
                    @endif

                    <!-- Variants -->
                    @if($product->has_variants && $product->variants->isNotEmpty())
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-900 mb-3">Select Variant:</label>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach($product->variants->where('is_active', true) as $variant)
                                    <button wire:click="selectVariant({{ $variant->id }})"
                                            style="{{ $selectedVariant === $variant->id ? 'border-color: var(--color-primary); background-color: #f2f7f4;' : '' }}"
                                            class="border-2 rounded-lg p-3 text-left transition {{ $selectedVariant === $variant->id ? '' : 'border-gray-300 hover:border-gray-400' }}">
                                        <p class="font-medium text-gray-900">{{ $variant->name }}</p>
                                        <p class="text-sm text-gray-600">₱{{ number_format($variant->price, 2) }}</p>
                                        <p class="text-xs {{ $variant->stock_status === 'in_stock' ? 'text-emerald-600' : 'text-red-600' }}">
                                            {{ $variant->stock_status === 'in_stock' ? 'In Stock' : 'Out of Stock' }}
                                        </p>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Quantity -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-900 mb-3">Quantity:</label>
                        <div class="flex items-center gap-3">
                            <button wire:click="decrementQuantity"
                                    class="w-10 h-10 rounded-lg border border-gray-300 hover:bg-gray-100 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                </svg>
                            </button>
                            <input type="number" 
                                   wire:model="quantity"
                                   min="1"
                                   class="w-20 text-center border border-gray-300 rounded-lg py-2">
                            <button wire:click="incrementQuantity"
                                    class="w-10 h-10 rounded-lg border border-gray-300 hover:bg-gray-100 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{--
                        Add to Cart feedback is shown by the shared popup
                        in the layout (<x-cart-toast />), so no flash
                        message block is needed here.
                    --}}

                    <!-- Add to Cart -->
                    @if($selectionInStock)
                        <button wire:click="addToCart"
                                style="background-color: var(--color-primary);"
                                class="w-full text-white py-3 px-6 rounded-lg hover:opacity-90 transition font-semibold text-lg">
                            Add to Cart
                        </button>
                    @else
                        <button disabled
                                class="w-full bg-gray-300 text-gray-500 py-3 px-6 rounded-lg cursor-not-allowed font-semibold text-lg">
                            Out of Stock
                        </button>
                    @endif

                    <!-- Product Details -->
                    <div class="mt-8 border-t pt-6 space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">SKU:</span>
                            <span class="font-medium">{{ $selectedVariant ? $product->variants->find($selectedVariant)?->sku : $product->sku }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Category:</span>
                            <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" 
                               style="color: var(--color-primary);"
                               class="font-medium hover:underline">
                                {{ $product->category->name }}
                            </a>
                        </div>
                        @if($product->brand)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Brand:</span>
                                <a href="{{ route('products.index', ['brand' => $product->brand->slug]) }}" 
                                   style="color: var(--color-primary);"
                                   class="font-medium hover:underline">
                                    {{ $product->brand->name }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs: Description & Reviews -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8" x-data="{ activeTab: (new URLSearchParams(window.location.search).get('tab')) || 'description' }">
            <!-- Tab Headers -->
            <div class="border-b">
                <nav class="flex">
                    <button @click="activeTab = 'description'"
                            :style="activeTab === 'description' ? 'border-color: var(--color-primary); color: var(--color-primary);' : ''"
                            class="px-6 py-4 border-b-2 font-medium transition text-gray-500">
                        Description
                    </button>
                    <button @click="activeTab = 'reviews'"
                            :style="activeTab === 'reviews' ? 'border-color: var(--color-primary); color: var(--color-primary);' : ''"
                            class="px-6 py-4 border-b-2 font-medium transition text-gray-500">
                        Reviews ({{ $product->reviews_count }})
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-8">
                <!-- Description Tab -->
                <div x-show="activeTab === 'description'" x-cloak>
                    <div class="prose max-w-none">
                        {!! $product->description !!}
                    </div>
                </div>

                <!-- Reviews Tab -->
                <div x-show="activeTab === 'reviews'" x-cloak>
                    @if(session('report-status'))
                        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                            {{ session('report-status') }}
                        </div>
                    @endif

                    @if($product->approvedReviews->count() > 0)
                        <div class="space-y-6">
                            @foreach($product->approvedReviews as $review)
                                <div class="border-b pb-6 last:border-b-0">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0">
                                            <div style="background-color: var(--color-primary);" class="w-12 h-12 text-white rounded-full flex items-center justify-center font-bold">
                                                {{ substr($review->customer->name, 0, 1) }}
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-2">
                                                <h4 class="font-semibold">{{ $review->customer->name }}</h4>
                                                @if($review->is_verified_purchase)
                                                    <span class="bg-emerald-100 text-emerald-800 text-xs px-2 py-1 rounded">
                                                        Verified Purchase
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 mb-2">
                                                <div class="flex text-amber-400">
                                                    @for($i = 1; $i <= 5; $i++)
                                                        @if($i <= $review->rating)
                                                            <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                                                <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                                            </svg>
                                                        @else
                                                            <svg class="w-4 h-4 fill-current text-gray-300" viewBox="0 0 20 20">
                                                                <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                                            </svg>
                                                        @endif
                                                    @endfor
                                                </div>
                                                <span class="text-sm text-gray-500">{{ $review->created_at->diffForHumans() }}</span>
                                            </div>
                                            @if($review->title)
                                                <h5 class="font-medium mb-2">{{ $review->title }}</h5>
                                            @endif
                                            @if($review->comment)
                                                <p class="text-gray-700">{{ $review->comment }}</p>
                                            @endif

                                            @if(!empty($review->photos))
                                                <div class="flex flex-wrap gap-2 mt-3">
                                                    @foreach($review->photos as $photo)
                                                        <a href="{{ Illuminate\Support\Facades\Storage::disk('r2')->url($photo) }}" target="_blank" rel="noopener">
                                                            <img src="{{ Illuminate\Support\Facades\Storage::disk('r2')->url($photo) }}"
                                                                 class="w-20 h-20 rounded-lg object-cover border border-gray-200"
                                                                 alt="Review photo">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if($review->video_path)
                                                <video controls class="mt-3 rounded-lg max-h-64" preload="metadata">
                                                    <source src="{{ Illuminate\Support\Facades\Storage::disk('r2')->url($review->video_path) }}">
                                                </video>
                                            @endif

                                            <!-- Report this reviewer -->
                                            @auth('customer')
                                                @if($review->customer_id !== auth('customer')->id())
                                                    <div class="mt-3">
                                                        @if(in_array($review->id, $reportedReviewIds))
                                                            <span class="text-xs text-gray-400">Reported to admin</span>
                                                        @elseif($showReportForm && $reportingReviewId === $review->id)
                                                            <div class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
                                                                <p class="text-sm font-medium text-gray-900">Report this user</p>
                                                                <div>
                                                                    <select wire:model="reportReason"
                                                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                                                        <option value="">Select a reason…</option>
                                                                        <option value="Spam or advertising">Spam or advertising</option>
                                                                        <option value="Abusive or offensive language">Abusive or offensive language</option>
                                                                        <option value="Fake or misleading review">Fake or misleading review</option>
                                                                        <option value="Inappropriate photos/video">Inappropriate photos/video</option>
                                                                        <option value="Other">Other</option>
                                                                    </select>
                                                                    @error('reportReason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                                </div>
                                                                <div>
                                                                    <textarea wire:model="reportDetails" rows="2" maxlength="1000"
                                                                              placeholder="Additional details (optional)"
                                                                              class="w-full rounded-lg border-gray-300 text-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"></textarea>
                                                                    @error('reportDetails') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                                    @error('report') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                                </div>
                                                                <div class="flex gap-2">
                                                                    <button wire:click="submitReport" type="button"
                                                                            wire:loading.attr="disabled" wire:target="submitReport"
                                                                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">
                                                                        Submit Report
                                                                    </button>
                                                                    <button wire:click="cancelReport" type="button"
                                                                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">
                                                                        Cancel
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <button wire:click="startReport({{ $review->id }})" type="button"
                                                                    class="text-xs text-gray-500 hover:text-red-600 underline">
                                                                Report user
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            @endauth
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-gray-500">No reviews yet. Be the first to review this product!</p>
                        </div>
                    @endif

                    @auth('customer')
                        <div class="mt-8 border-t pt-8">
                            @if($hasReview && !$reviewIsApproved)
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800">
                                    Your review has been submitted and is awaiting approval.
                                </div>
                            @elseif($hasReview)
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                                    Thank you for reviewing this product.
                                </div>
                            @elseif($canReview)
                                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800">
                                    <strong>A review is required.</strong> Your order for this product is complete — please share your feedback below.
                                </div>
                                <form wire:submit="submitReview" class="space-y-5" enctype="multipart/form-data">
                                    <h3 class="text-xl font-semibold text-gray-900">Review Required</h3>

                                    <div>
                                        <label for="review-rating" class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                                        <select id="review-rating" wire:model="reviewRating"
                                                class="w-full rounded-lg border-gray-300 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                            <option value="5">5 - Excellent</option>
                                            <option value="4">4 - Good</option>
                                            <option value="3">3 - Average</option>
                                            <option value="2">2 - Poor</option>
                                            <option value="1">1 - Very Poor</option>
                                        </select>
                                        @error('reviewRating') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="review-title" class="block text-sm font-medium text-gray-700 mb-1">Title (optional)</label>
                                        <input id="review-title" type="text" wire:model="reviewTitle" maxlength="255"
                                               class="w-full rounded-lg border-gray-300 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                        @error('reviewTitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="review-comment" class="block text-sm font-medium text-gray-700 mb-1">Review</label>
                                        <textarea id="review-comment" wire:model="reviewComment" rows="4" maxlength="2000"
                                                  class="w-full rounded-lg border-gray-300 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"></textarea>
                                        @error('reviewComment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        @error('review') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="review-photos" class="block text-sm font-medium text-gray-700 mb-1">
                                            Photos (optional, up to 5)
                                        </label>
                                        <input id="review-photos" type="file" wire:model="reviewPhotos" accept="image/*" multiple
                                               class="w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-gray-200">
                                        <div wire:loading wire:target="reviewPhotos" class="text-xs text-gray-500 mt-1">Uploading photos…</div>
                                        @error('reviewPhotos') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        @error('reviewPhotos.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        @if($reviewPhotos)
                                            <div class="flex flex-wrap gap-2 mt-2">
                                                @foreach($reviewPhotos as $photo)
                                                    <img src="{{ $photo->temporaryUrl() }}" class="w-16 h-16 rounded-lg object-cover border border-gray-200">
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div x-data="{
                                            checkDuration(event) {
                                                const file = event.target.files[0];
                                                if (! file) return;
                                                const url = URL.createObjectURL(file);
                                                const videoEl = document.createElement('video');
                                                videoEl.preload = 'metadata';
                                                videoEl.onloadedmetadata = () => {
                                                    URL.revokeObjectURL(url);
                                                    if (videoEl.duration > 60) {
                                                        alert('Please upload a video that is 1 minute or shorter.');
                                                        event.target.value = '';
                                                        $wire.set('reviewVideo', null);
                                                    }
                                                };
                                                videoEl.src = url;
                                            }
                                        }">
                                        <label for="review-video" class="block text-sm font-medium text-gray-700 mb-1">
                                            Video (optional, max 1 minute)
                                        </label>
                                        <input id="review-video" type="file" wire:model="reviewVideo" accept="video/*"
                                               x-on:change="checkDuration($event)"
                                               class="w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-gray-200">
                                        <div wire:loading wire:target="reviewVideo" class="text-xs text-gray-500 mt-1">Uploading video…</div>
                                        @error('reviewVideo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        @if($reviewVideo)
                                            <video src="{{ $reviewVideo->temporaryUrl() }}" controls class="mt-2 rounded-lg max-h-48"></video>
                                        @endif
                                    </div>

                                    <button type="submit"
                                            class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 font-semibold text-white transition hover:bg-[#154522] disabled:opacity-50"
                                            wire:loading.attr="disabled" wire:target="submitReview">
                                        Submit Review
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endauth
                </div>
            </div>
        </div>

        <!-- Related Products -->
        @if($relatedProducts->count() > 0)
            <section>
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Products</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($relatedProducts as $relatedProduct)
                        <livewire:product-card :product="$relatedProduct" :key="'related-' . $relatedProduct->id" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>