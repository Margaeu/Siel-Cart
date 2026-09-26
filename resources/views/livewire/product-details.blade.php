<div class="bg-gray-50 py-10 min-h-screen">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        
        <!-- Breadcrumb -->
        <nav class="mb-8" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-1.5 sm:gap-2 text-xs sm:text-sm text-gray-500">
                <li class="inline-flex items-center">
                    <a href="{{ route('home') }}" class="font-medium hover:text-[var(--color-primary)] transition-colors">
                        Home
                    </a>
                </li>

                <li class="inline-flex items-center" aria-hidden="true">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </li>

                <li class="inline-flex items-center">
                    <a href="{{ route('products.index') }}" class="font-medium hover:text-[var(--color-primary)] transition-colors">
                        Products
                    </a>
                </li>

                <li class="inline-flex items-center" aria-hidden="true">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </li>

                <li class="inline-flex items-center">
                    <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" class="font-medium hover:text-[var(--color-primary)] transition-colors">
                        {{ $product->category->name }}
                    </a>
                </li>

                <li class="inline-flex items-center" aria-hidden="true">
                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </li>

                <li class="inline-flex items-center text-gray-900 font-semibold truncate max-w-[180px] sm:max-w-xs" aria-current="page">
                    {{ $product->name }}
                </li>
            </ol>
        </nav>

        <!-- Product Detail Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-12">
            {{--
                Variant and photo selection are client-side. They used to be
                wire:click actions, and every tap cost a full round trip: the
                component re-fetched the product, reloaded its reviews and
                images, re-ran the related-products query and re-rendered four
                nested product cards -- about a second to move one highlight.

                Nothing in this block needs the server. Everything the choice
                can change is emitted once, below, and Alpine does the rest;
                the server only hears the choice at addToCart(), which
                re-validates it against the product's own active variants.

                The server's own $selectedVariant stays on whatever the page
                opened with, which is fine: Alpine re-applies its bindings after
                a Livewire morph, so a render triggered from elsewhere (a review
                submission, a photo upload) redraws this block from the server's
                stale selection and Alpine immediately puts the customer's back.
                Everything below is bound rather than interpolated for that
                reason -- a value written straight into the HTML would not be.
            --}}
            <div class="lg:grid lg:grid-cols-12 lg:gap-12 p-6 sm:p-8 lg:p-10"
                 x-data="{
                     variants: @js($variantOptions),
                     imageUrls: @js($imageUrls),
                     sharedImages: @js($sharedImagePaths),
                     defaultImage: @js($defaultImagePath),
                     hasVariants: @js((bool) $product->has_variants),
                     productStock: @js((int) $product->stock_quantity),
                     fallbackPrice: @js($product->display_price_label),
                     fallbackSku: @js($product->sku),

                     selected: @js($selectedVariant),
                     image: @js($selectedImage),
                     qty: 1,
                     adding: false,

                     zoomOpen: false,
                     zoom: 1,
                     panX: 0,
                     panY: 0,
                     dragging: false,
                     didDrag: false,
                     pointerStartX: 0,
                     pointerStartY: 0,
                     panStartX: 0,
                     panStartY: 0,
                     returnFocus: null,
                     previousBodyOverflow: '',

                     get variant() { return this.variants[this.selected] ?? null },
                     get stock() { return this.hasVariants ? (this.variant?.stock ?? 0) : this.productStock },
                     get inStock() { return this.stock > 0 },
                     get max() { return Math.max(1, this.stock) },
                     get price() { return this.variant ? this.variant.price : this.fallbackPrice },
                     get sku() { return this.variant ? this.variant.sku : this.fallbackSku },
                     get imageUrl() { return this.imageUrls[this.image] ?? null },
                     get imagePaths() { return Object.keys(this.imageUrls) },

                     selectImage(path) {
                         this.image = path;
                         this.resetZoom();
                     },
                     resetZoom() {
                         this.zoom = 1;
                         this.panX = 0;
                         this.panY = 0;
                         this.dragging = false;
                         this.didDrag = false;
                     },
                     setZoom(value) {
                         this.zoom = Math.round(Math.min(4, Math.max(1, value)) * 100) / 100;

                         if (this.zoom === 1) {
                             this.panX = 0;
                             this.panY = 0;
                         }
                     },
                     openZoom(event) {
                         if (! this.imageUrl) return;

                         this.returnFocus = event?.currentTarget ?? null;
                         this.resetZoom();
                         this.previousBodyOverflow = document.body.style.overflow;
                         document.body.style.overflow = 'hidden';
                         this.zoomOpen = true;
                         this.$nextTick(() => this.$refs.zoomClose?.focus());
                     },
                     closeZoom() {
                         if (! this.zoomOpen) return;

                         this.zoomOpen = false;
                         document.body.style.overflow = this.previousBodyOverflow;
                         this.$nextTick(() => this.returnFocus?.focus());
                     },
                     toggleZoom() {
                         if (this.didDrag) {
                             this.didDrag = false;
                             return;
                         }

                         this.setZoom(this.zoom > 1 ? 1 : 2);
                     },
                     startPan(event) {
                         if (this.zoom <= 1) return;

                         this.dragging = true;
                         this.didDrag = false;
                         this.pointerStartX = event.clientX;
                         this.pointerStartY = event.clientY;
                         this.panStartX = this.panX;
                         this.panStartY = this.panY;
                         event.currentTarget.setPointerCapture?.(event.pointerId);
                     },
                     movePan(event) {
                         if (! this.dragging) return;

                         const deltaX = event.clientX - this.pointerStartX;
                         const deltaY = event.clientY - this.pointerStartY;
                         this.didDrag = this.didDrag || Math.abs(deltaX) > 3 || Math.abs(deltaY) > 3;
                         this.panX = this.panStartX + deltaX;
                         this.panY = this.panStartY + deltaY;
                     },
                     endPan() {
                         this.dragging = false;
                     },
                     cycleImage(direction) {
                         if (this.imagePaths.length < 2) return;

                         const current = Math.max(0, this.imagePaths.indexOf(this.image));
                         const next = (current + direction + this.imagePaths.length) % this.imagePaths.length;
                         this.selectImage(this.imagePaths[next]);
                     },

                     pick(id) {
                         const variant = this.variants[id];
                         if (! variant) return;

                         this.selected = id;
                         // The new variant caps the quantity differently and
                         // the standing count may not fit under it.
                         this.qty = 1;

                         // Mirrors what selectInitialVariant() does on the
                         // server: a variant with photos of its own shows the
                         // first of them, otherwise whatever shared photo is
                         // already up stays, falling back to the default.
                         if (variant.image) {
                             this.image = variant.image;
                         } else if (! this.sharedImages.includes(this.image)) {
                             this.image = this.defaultImage;
                         }
                     },
                     clamp(value) {
                         const n = parseInt(value, 10);
                         if (Number.isNaN(n) || n < 1) return 1;
                         return Math.min(n, this.max);
                     },
                     step(by) { this.qty = this.clamp(this.clamp(this.qty) + by); },
                     typed(el) {
                         // Digits only; an empty box is allowed while typing
                         // and becomes 1 again on blur.
                         const digits = el.value.replace(/\D/g, '');
                         el.value = digits;
                         this.qty = digits;
                     },
                     add() {
                         if (this.adding) return;
                         this.qty = this.clamp(this.qty);
                         this.adding = true;
                         $wire.addToCart(this.qty, this.selected).finally(() => this.adding = false);
                     },
                 }">
                
                <!-- Left Column: Gallery -->
                <div class="lg:col-span-6 flex flex-col gap-4 mb-8 lg:mb-0">
                    <!-- Main Image Frame -->
                    @if($selectedImage)
                        <button type="button"
                                @click="openZoom($event)"
                                aria-label="Open product image viewer"
                                aria-haspopup="dialog"
                                class="group relative aspect-square overflow-hidden rounded-xl border border-gray-100 bg-gray-50 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2">
                            {{-- :src, not a Blade expression: the photo follows
                                 the variant and the thumbnails without a round
                                 trip. The server still renders the opening one
                                 so there is no blank frame before Alpine boots. --}}
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('r2')->url($selectedImage) }}"
                                 :src="imageUrl"
                                 alt="{{ $product->name }}"
                                 draggable="false"
                                 class="h-full w-full object-cover object-center transition-transform duration-500 ease-out group-hover:scale-105 group-focus-visible:scale-105">

                            <span class="absolute bottom-3 right-3 z-10 inline-flex items-center gap-2 rounded-full bg-white/95 px-3 py-2 text-xs font-semibold text-gray-800 shadow-lg ring-1 ring-black/5 backdrop-blur-sm transition group-hover:bg-white">
                                <svg class="size-4 text-[var(--color-primary)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="11" cy="11" r="7"></circle>
                                    <path stroke-linecap="round" d="m20 20-4-4m-5-8v6m-3-3h6"></path>
                                </svg>
                                <span class="hidden sm:inline">Click to zoom</span>
                            </span>

                            <x-storefront.product-badges :product="$product" :is-best-seller="$isBestSeller" :is-top-pick="$isTopPick" />
                        </button>
                    @else
                        <div class="relative flex aspect-square items-center justify-center overflow-hidden rounded-xl border border-gray-100 bg-gradient-to-br from-gray-100 to-gray-200">
                            <span class="select-none text-8xl font-black text-gray-300">{{ substr($product->name, 0, 1) }}</span>
                            <x-storefront.product-badges :product="$product" :is-best-seller="$isBestSeller" :is-top-pick="$isTopPick" />
                        </div>
                    @endif

                    <!-- Thumbnails -->
                    @if($galleryImages->count() > 1)
                        <div class="grid grid-cols-5 gap-3">
                            @foreach($galleryImages as $image)
                                <button type="button"
                                        @click="selectImage(@js($image->image_path))"
                                        aria-label="View {{ $product->name }} image {{ $loop->iteration }}"
                                        class="relative aspect-square rounded-lg overflow-hidden border-2 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[var(--color-primary)]"
                                        :class="image === @js($image->image_path) ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary)]/20 scale-95' : 'border-gray-200 hover:border-gray-300 opacity-70 hover:opacity-100'">
                                    <img src="{{ $image->url }}" alt="" class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Right Column: Info & Actions -->
                <div class="lg:col-span-6 flex flex-col justify-between">
                    <div>
                        @if($product->brand)
                            <a href="{{ route('products.index', ['brand' => $product->brand->slug]) }}"
                               class="inline-block mb-3 text-xs font-bold tracking-wider uppercase text-[var(--color-primary)] hover:underline">
                                {{ $product->brand->name }}
                            </a>
                        @endif

                        <!-- Product heading and availability -->
                        <div class="flex items-center justify-between gap-4 mb-3">
                            <h1 class="min-w-0 flex-1 text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                                {{ $product->name }}
                            </h1>

                            {{-- One element for both states so the count can
                                 follow the variant without a round trip. The
                                 server renders the opening state into the same
                                 markup, so nothing flickers before Alpine boots. --}}
                            <span @class([
                                      'shrink-0 inline-flex items-center gap-1.5 whitespace-nowrap',
                                      'text-sm sm:text-base font-semibold text-gray-900' => $selectionInStock,
                                      'bg-rose-50 text-rose-700 border border-rose-200/60 text-xs font-semibold px-2.5 py-1 rounded-full' => ! $selectionInStock,
                                  ])
                                  :class="{
                                      'text-sm sm:text-base font-semibold text-gray-900': inStock,
                                      'bg-rose-50 text-rose-700 border border-rose-200/60 text-xs font-semibold px-2.5 py-1 rounded-full': ! inStock,
                                  }">
                                <span x-cloak x-show="! inStock" class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                <span x-text="inStock ? max + ' in Stock' : 'Out of Stock'">{{ $selectionInStock ? $maxQuantity.' in Stock' : 'Out of Stock' }}</span>
                            </span>
                        </div>

                        <!-- Ratings Summary -->
                        @if($product->reviews_count > 0)
                            <div class="flex items-center gap-3 mb-6">
                                <div class="flex items-center text-amber-400">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-4 h-4 {{ $i <= floor($product->average_rating) ? 'fill-current' : 'text-gray-200' }}" viewBox="0 0 20 20">
                                            <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($product->average_rating, 1) }}</span>
                                <span class="text-gray-300">•</span>
                                <a href="#reviews-tab" @click="activeTab = 'reviews'" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                                    {{ $product->reviews_count }} {{ Str::plural('review', $product->reviews_count) }}
                                </a>
                            </div>
                        @endif

                        <!-- Price -->
                        <div class="mb-6 flex items-baseline gap-2">
                            {{-- Already formatted server-side, per variant, in
                                 render() -- so no Intl work and no round trip. --}}
                            <span class="text-3xl font-black text-gray-900"
                                  x-text="price">{{ $selectedVariant
                                      ? '₱'.number_format($product->variants->find($selectedVariant)->price, 2)
                                      : $product->display_price_label }}</span>
                        </div>

                        <!-- Short Description -->
                        @if($product->short_description)
                            <p class="text-gray-600 text-sm leading-relaxed mb-6">
                                {{ $product->short_description }}
                            </p>
                        @endif

                        <!-- Options: Variant Selection -->
                        @if($product->has_variants && $product->variants->isNotEmpty())
                            <div class="mb-6 space-y-3">
                                <label class="block text-sm font-semibold text-gray-900">Option / Variant</label>
                                <div class="grid grid-cols-2 gap-2.5">
                                    @foreach($product->variants->where('is_active', true) as $variant)
                                        <button type="button"
                                                @click="pick({{ $variant->id }})"
                                                @class([
                                                    'relative p-3 rounded-xl border text-left transition-all duration-200 focus:outline-none flex flex-col justify-between gap-1',
                                                    'border-[var(--color-primary)] bg-[var(--color-primary)]/5 ring-1 ring-[var(--color-primary)]' => $selectedVariant === $variant->id,
                                                    'border-gray-200 hover:border-gray-300 bg-white' => $selectedVariant !== $variant->id,
                                                ])
                                                :class="{
                                                    'border-[var(--color-primary)] bg-[var(--color-primary)]/5 ring-1 ring-[var(--color-primary)]': selected === {{ $variant->id }},
                                                    'border-gray-200 hover:border-gray-300 bg-white': selected !== {{ $variant->id }},
                                                }">
                                            <div>
                                                <p class="font-semibold text-xs text-gray-900">{{ $variant->name }}</p>
                                            </div>
                                            <span class="text-[0.625rem] font-medium tracking-wider uppercase {{ $variant->stock_status === 'in_stock' ? 'text-emerald-600' : 'text-rose-500' }}">
                                                {{ $variant->stock_status === 'in_stock' ? $variant->stock_quantity.' In Stock' : 'Out of Stock' }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{--
                            Quantity is owned by Alpine too, for the same reason
                            the variant picker is: the +/- buttons used to be
                            wire:click calls, so every tap re-rendered the whole
                            page on the server. The value only reaches the server
                            with addToCart(), alongside the variant id.

                            pick() resets it to 1, since the cap moves with the
                            variant and the standing count may not fit under it.
                        --}}
                        <!-- Quantity Selector -->
                        <div class="mb-8">
                            <label class="block text-sm font-semibold text-gray-900 mb-3">Quantity</label>
                            <div class="inline-flex items-center rounded-xl border border-gray-200 p-1 bg-white shadow-sm">
                                <button type="button"
                                        @click="step(-1)"
                                        :disabled="clamp(qty) <= 1"
                                        aria-label="Decrease quantity"
                                        class="w-9 h-9 rounded-lg text-gray-600 hover:bg-gray-100 flex items-center justify-center transition-colors active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                </button>
                                {{-- type="text", not "number": a number input changes value on
                                     mouse-wheel scroll and shows browser spinner arrows. --}}
                                <input type="text"
                                       inputmode="numeric"
                                       pattern="[0-9]*"
                                       autocomplete="off"
                                       aria-label="Quantity"
                                       :value="qty"
                                       @input="typed($event.target)"
                                       @blur="qty = clamp(qty)"
                                       @keydown.enter.prevent="$event.target.blur()"
                                       class="w-14 text-center text-sm font-bold text-gray-900 border-none focus:ring-0 p-0">
                                <button type="button"
                                        @click="step(1)"
                                        :disabled="clamp(qty) >= max"
                                        aria-label="Increase quantity"
                                        class="w-9 h-9 rounded-lg text-gray-600 hover:bg-gray-100 flex items-center justify-center transition-colors active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- CTA Actions -->
                    <div>
                        {{-- One button for both states, so switching to a sold
                             out variant disables it on the spot. The inline
                             brand colour has to go through :style as well --
                             an out of stock button must not stay green. --}}
                        <button type="button"
                                @click="add()"
                                :disabled="adding || ! inStock"
                                @disabled(! $selectionInStock)
                                @class([
                                    'w-full py-4 px-6 rounded-xl transition-all duration-150 text-base flex items-center justify-center gap-2',
                                    'bg-[var(--color-primary)] text-white font-bold hover:brightness-110 active:scale-[0.99] shadow-lg shadow-[var(--color-primary)]/20 disabled:opacity-80 disabled:cursor-wait' => $selectionInStock,
                                    'bg-gray-100 text-gray-400 font-semibold border border-gray-200 cursor-not-allowed' => ! $selectionInStock,
                                ])
                                :class="{
                                    'bg-[var(--color-primary)] text-white font-bold hover:brightness-110 active:scale-[0.99] shadow-lg shadow-[var(--color-primary)]/20 disabled:opacity-80 disabled:cursor-wait': inStock,
                                    'bg-gray-100 text-gray-400 font-semibold border border-gray-200 cursor-not-allowed': ! inStock,
                                }">
                            <svg x-show="inStock && ! adding" @if(! $selectionInStock) x-cloak @endif class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <svg x-show="adding" x-cloak class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            <span x-text="! inStock ? 'Out of Stock' : (adding ? 'Adding…' : 'Add to Cart')">{{ $selectionInStock ? 'Add to Cart' : 'Out of Stock' }}</span>
                        </button>

                        <!-- Product Meta Info -->
                        <div class="mt-8 pt-6 border-t border-gray-100 text-xs space-y-2.5 text-gray-500">
                            <div class="flex justify-between items-center">
                                <span>SKU</span>
                                <span class="font-medium text-gray-800" x-text="sku">{{ $selectedVariant ? $product->variants->find($selectedVariant)?->sku : $product->sku }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span>Category</span>
                                <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" 
                                   class="font-medium text-gray-800 hover:text-[var(--color-primary)] transition-colors">
                                    {{ $product->category->name }}
                                </a>
                            </div>
                            @if($product->brand)
                                <div class="flex justify-between items-center">
                                    <span>Brand</span>
                                    <a href="{{ route('products.index', ['brand' => $product->brand->slug]) }}" 
                                       class="font-medium text-gray-800 hover:text-[var(--color-primary)] transition-colors">
                                        {{ $product->brand->name }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Product Image Viewer -->
                <template x-teleport="body">
                    <div x-show="zoomOpen"
                         x-cloak
                         x-transition.opacity.duration.200ms
                         @click.self="closeZoom()"
                         @keydown.escape.window="closeZoom()"
                         @keydown.window="
                             if (! zoomOpen) return;
                             if ($event.key === 'ArrowLeft') cycleImage(-1);
                             if ($event.key === 'ArrowRight') cycleImage(1);
                             if ($event.key === '+' || $event.key === '=') setZoom(zoom + 0.25);
                             if ($event.key === '-') setZoom(zoom - 0.25);
                         "
                         class="fixed inset-0 z-[70] flex flex-col bg-gray-950/95 text-white backdrop-blur-sm"
                         role="dialog"
                         aria-modal="true"
                         aria-label="Product image viewer">
                        <div class="flex shrink-0 items-center justify-between gap-4 border-b border-white/10 px-4 py-3 sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold sm:text-base">{{ $product->name }}</p>
                                <p class="mt-0.5 text-xs text-white/60">Click the image to zoom. Drag while zoomed.</p>
                            </div>
                            <button type="button"
                                    x-ref="zoomClose"
                                    @click="closeZoom()"
                                    aria-label="Close product image viewer"
                                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden p-4 sm:p-8"
                             @wheel.prevent="setZoom(zoom + ($event.deltaY < 0 ? 0.25 : -0.25))">
                            <button type="button"
                                    x-show="imagePaths.length > 1"
                                    @click="cycleImage(-1)"
                                    aria-label="Previous product image"
                                    class="absolute left-3 z-10 inline-flex size-11 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] sm:left-6">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"></path>
                                </svg>
                            </button>

                            <img :src="imageUrl"
                                 alt="{{ $product->name }} enlarged"
                                 draggable="false"
                                 @click="toggleZoom()"
                                 @dragstart.prevent
                                 @pointerdown.prevent="startPan($event)"
                                 @pointermove.prevent="movePan($event)"
                                 @pointerup="endPan()"
                                 @pointercancel="endPan()"
                                 :style="`transform: translate3d(${panX}px, ${panY}px, 0) scale(${zoom})`"
                                 :class="{
                                     'cursor-zoom-in': zoom === 1,
                                     'cursor-grab': zoom > 1 && ! dragging,
                                     'cursor-grabbing': dragging,
                                     'transition-none': dragging,
                                     'transition-transform duration-150 ease-out': ! dragging,
                                 }"
                                 class="max-h-full max-w-full select-none object-contain [touch-action:none]">

                            <button type="button"
                                    x-show="imagePaths.length > 1"
                                    @click="cycleImage(1)"
                                    aria-label="Next product image"
                                    class="absolute right-3 z-10 inline-flex size-11 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)] sm:right-6">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="shrink-0 border-t border-white/10 bg-black/20 px-4 py-3 sm:px-6">
                            <div class="mx-auto flex max-w-xl items-center justify-center gap-2">
                                <button type="button"
                                        @click="setZoom(zoom - 0.25)"
                                        :disabled="zoom <= 1"
                                        aria-label="Zoom out"
                                        class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-35 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">
                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" d="M6 12h12"></path>
                                    </svg>
                                </button>
                                <span class="w-16 text-center text-sm font-semibold tabular-nums" x-text="`${Math.round(zoom * 100)}%`">100%</span>
                                <button type="button"
                                        @click="setZoom(zoom + 0.25)"
                                        :disabled="zoom >= 4"
                                        aria-label="Zoom in"
                                        class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-35 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">
                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" d="M12 6v12M6 12h12"></path>
                                    </svg>
                                </button>
                                <button type="button"
                                        x-show="zoom > 1"
                                        @click="resetZoom()"
                                        class="ml-2 rounded-full px-3 py-2 text-xs font-semibold text-white/75 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-secondary)]">
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Information Tabs Section -->
        <div id="reviews-tab" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-16" x-data="{ activeTab: 'description' }">
            <div class="border-b border-gray-100 px-6 sm:px-8">
                <nav class="flex gap-8 -mb-px">
                    <button @click="activeTab = 'description'"
                            :class="activeTab === 'description' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-5 border-b-2 font-bold text-sm tracking-wide transition-colors">
                        Description
                    </button>
                    <button @click="activeTab = 'reviews'"
                            :class="activeTab === 'reviews' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="py-5 border-b-2 font-bold text-sm tracking-wide transition-colors flex items-center gap-2">
                        Reviews
                        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full font-medium">
                            {{ $product->reviews_count }}
                        </span>
                    </button>
                </nav>
            </div>

            <div class="p-6 sm:p-8 lg:p-10">
                <!-- Description Panel -->
                <div x-show="activeTab === 'description'" x-cloak class="transition-opacity duration-200">
                    {{--
                        text-sm on phones: the description inherited the 1rem body size,
                        which on top of the fluid root read too large next to the text-sm
                        tabs and short description. `prose` alone does not size it -- the
                        typography plugin is not loaded -- so the size is set explicitly.
                    --}}
                    <div class="prose max-w-none prose-gray text-sm leading-relaxed text-gray-600 sm:text-base">
                        {!! $product->description !!}
                    </div>
                </div>

                <!-- Reviews Panel -->
                <div x-show="activeTab === 'reviews'" x-cloak class="transition-opacity duration-200">
                    @if(session('report-status'))
                        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 text-emerald-800 text-sm font-medium flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ session('report-status') }}
                        </div>
                    @endif

                    {{-- One page of approved reviews (ProductDetails::render()); the count in the tab and summary comes from aggregates, not from this page. --}}
                    @if($reviews->total() > 0)
                        <div class="divide-y divide-gray-100 space-y-8">
                            @foreach($reviews as $review)
                                <div class="pt-8 first:pt-0">
                                    <div class="flex items-start gap-4">
                                        <!-- Customer Avatar -->
                                        <div class="flex-shrink-0">
                                            <div style="background-color: var(--color-primary);" 
                                                 class="w-10 h-10 text-white rounded-full flex items-center justify-center font-bold text-sm shadow-sm">
                                                {{ $review->customer->trashed() ? '?' : substr($review->customer->name, 0, 1) }}
                                            </div>
                                        </div>

                                        <!-- Review Body -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                                                <div class="flex items-center gap-2">
                                                    <h4 class="font-bold text-gray-900 text-sm">{{ $review->customer->name }}</h4>
                                                    @if($review->is_verified_purchase)
                                                        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[0.625rem] font-semibold px-2 py-0.5 rounded-full">
                                                            Verified Purchase
                                                        </span>
                                                    @endif
                                                </div>
                                                <span class="text-xs text-gray-400">{{ $review->created_at->diffForHumans() }}</span>
                                            </div>

                                            <div class="flex text-amber-400 mb-3">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <svg class="w-3.5 h-3.5 {{ $i <= $review->rating ? 'fill-current' : 'text-gray-200' }}" viewBox="0 0 20 20">
                                                        <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                                    </svg>
                                                @endfor
                                            </div>

                                            @if($review->title)
                                                <h5 class="font-bold text-gray-900 text-sm mb-1">{{ $review->title }}</h5>
                                            @endif

                                            @if($review->comment)
                                                <p class="text-gray-600 text-sm leading-relaxed mb-4">{{ $review->comment }}</p>
                                            @endif

                                            <!-- Photos & Video Attachments -->
                                            @php
                                                // Resolve Photos
                                                $photos = $review->photo_urls ?? [];
                                                if (empty($photos) && !empty($review->photos)) {
                                                    $rawPhotos = is_array($review->photos) ? $review->photos : json_decode($review->photos, true);
                                                    if (is_array($rawPhotos)) {
                                                        $photos = array_map(fn($p) => \Illuminate\Support\Facades\Storage::disk('r2')->url($p), $rawPhotos);
                                                    }
                                                }

                                                // Resolve Video URL directly from string column
                                                $videoUrl = null;
                                                if (!empty($review->video_path)) {
                                                    $videoUrl = Str::startsWith($review->video_path, ['http://', 'https://']) 
                                                        ? $review->video_path 
                                                        : \Illuminate\Support\Facades\Storage::disk('r2')->url($review->video_path);
                                                }
                                            @endphp

                                            @if(count($photos) > 0 || $videoUrl)
                                                <div x-data="{ lightbox: null }" class="mt-4 mb-3">
                                                    <div class="flex flex-wrap items-center gap-2.5">
                                                        @foreach($photos as $photoUrl)
                                                            <button type="button" @click="lightbox = '{{ $photoUrl }}'"
                                                                    class="w-16 h-16 rounded-xl overflow-hidden border border-gray-200 hover:ring-2 hover:ring-[var(--color-primary)] transition-all">
                                                                <img src="{{ $photoUrl }}" alt="Review photo" class="w-full h-full object-cover">
                                                            </button>
                                                        @endforeach

                                                        @if($videoUrl)
                                                            <div class="w-28 h-16 rounded-xl overflow-hidden border border-gray-200 bg-black">
                                                                <video src="{{ $videoUrl }}" controls preload="metadata" class="w-full h-full object-cover"></video>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <!-- Lightbox Modal -->
                                                    <div x-show="lightbox" x-cloak @click="lightbox = null"
                                                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
                                                        <img :src="lightbox" alt="Enlarged photo" class="max-h-[85vh] max-w-full rounded-xl shadow-2xl" @click.stop>
                                                        <button type="button" @click="lightbox = null" aria-label="Close enlarged photo" class="absolute top-4 right-4 inline-flex size-11 items-center justify-center rounded-full text-white text-3xl font-light hover:bg-white/10">&times;</button>
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- Redesigned Report User Section -->
                                            <div class="mt-4 pt-3 border-t border-gray-50">
                                                @auth('customer')
                                                    @if($review->customer_id !== auth('customer')->id())
                                                        @if(in_array($review->id, $reportedReviewIds ?? []))
                                                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 font-medium bg-gray-50 border border-gray-200 px-2.5 py-1 rounded-full">
                                                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                                Reported to admin
                                                            </span>
                                                        @elseif(($showReportForm ?? false) && ($reportingReviewId ?? null) === $review->id)
                                                            <!-- Expanded Report Box -->
                                                            <div class="mt-2 rounded-2xl border border-rose-100 bg-rose-50/30 p-5 space-y-4 shadow-sm">
                                                                <div class="flex items-center justify-between">
                                                                    <div class="flex items-center gap-2">
                                                                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-100 text-rose-600">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                                        </span>
                                                                        <div>
                                                                            <h5 class="text-xs font-bold uppercase tracking-wider text-gray-900">Report Review</h5>
                                                                            <p class="text-[0.7rem] text-gray-500">Help us keep the marketplace safe and accurate.</p>
                                                                        </div>
                                                                    </div>
                                                                    <button type="button" wire:click="cancelReport" aria-label="Close report form" class="inline-flex size-9 shrink-0 items-center justify-center rounded-full text-lg leading-none text-gray-400 hover:bg-gray-100 hover:text-gray-600">&times;</button>
                                                                </div>

                                                                <div>
                                                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Reason for reporting</label>
                                                                    <select wire:model="reportReason" class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-xs text-gray-800 shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                                                        <option value="">Select a reason…</option>
                                                                        <option value="Spam or advertising">Spam or advertising</option>
                                                                        <option value="Abusive or offensive language">Abusive or offensive language</option>
                                                                        <option value="Fake or misleading review">Fake or misleading review</option>
                                                                        <option value="Inappropriate photos/video">Inappropriate photos/video</option>
                                                                        <option value="Other">Other</option>
                                                                    </select>
                                                                    @error('reportReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                                </div>

                                                                <div>
                                                                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Additional context <span class="text-gray-400 font-normal">(optional)</span></label>
                                                                    <textarea wire:model="reportDetails" rows="2" maxlength="1000" placeholder="Please provide any details that could assist our moderators..." class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-xs text-gray-800 placeholder:text-gray-400 shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)]"></textarea>
                                                                    @error('reportDetails') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                                </div>

                                                                <div class="flex items-center gap-2 pt-1">
                                                                    <button wire:click="submitReport" type="button" wire:loading.attr="disabled" class="inline-flex min-h-9 items-center gap-1.5 rounded-xl bg-rose-600 px-4 text-xs font-semibold text-white shadow-sm hover:bg-rose-700 active:scale-95 transition-all">
                                                                        <span wire:loading.remove wire:target="submitReport">Submit Report</span>
                                                                        <span wire:loading wire:target="submitReport">Submitting...</span>
                                                                    </button>
                                                                    <button wire:click="cancelReport" type="button" class="inline-flex min-h-9 items-center rounded-xl border border-gray-200 bg-white px-4 text-xs font-medium text-gray-700 hover:bg-gray-50 active:scale-95 transition-all">
                                                                        Cancel
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <!-- Clean Trigger Button -->
                                                            <button wire:click="startReport({{ $review->id }})" type="button" class="inline-flex items-center gap-1 text-[0.75rem] font-medium text-gray-400 hover:text-rose-600 transition-colors">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                                                                Report review
                                                            </button>
                                                        @endif
                                                    @endif
                                                @else
                                                    <a href="{{ route('login') }}" class="inline-flex items-center gap-1 text-[0.75rem] font-medium text-gray-400 hover:text-gray-700 transition-colors">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                                                        Log in to report
                                                    </a>
                                                @endauth
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($reviews->hasPages())
                            {{-- Scroll back to the reviews card, not the top of the page, when paging. --}}
                            <div class="mt-8">
                                {{ $reviews->links(data: ['scrollTo' => '#reviews-tab']) }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-12">
                            <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <p class="text-gray-500 font-medium text-sm">No reviews yet. Be the first to review this product!</p>
                        </div>
                    @endif

                    <!-- Review Form Area -->
                    @auth('customer')
                        <div class="mt-12 border-t border-gray-100 pt-10">
                            @if($hasReview)
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 text-emerald-800 text-sm font-medium flex items-center gap-3">
                                    <svg class="w-5 h-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Thank you for reviewing this product.
                                </div>
                            @elseif($canReview)
                                <form wire:submit="submitReview" class="max-w-2xl space-y-6">
                                    <h3 class="text-lg font-extrabold text-gray-900">Write a Review</h3>

                                    <div x-data="{ 
                                        rating: @entangle('reviewRating').live || 5, 
                                        hoverRating: 0,
                                        labels: {1: '1 - Very Poor', 2: '2 - Poor', 3: '3 - Average', 4: '4 - Good', 5: '5 - Excellent'}
                                    }">
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">Rating</label>
                                        <div class="flex items-center gap-3">
                                            <div class="flex items-center gap-1" @mouseleave="hoverRating = 0">
                                                <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                                                    <button type="button" 
                                                            @click="rating = star" 
                                                            @mouseenter="hoverRating = star"
                                                            class="p-1 -m-1 text-gray-200 hover:scale-110 transition-transform focus:outline-none">
                                                        <svg class="w-8 h-8 transition-colors duration-150" 
                                                             :class="(hoverRating ? star <= hoverRating : star <= rating) ? 'text-amber-400 fill-current' : 'text-gray-200 fill-current'" 
                                                             viewBox="0 0 20 20">
                                                            <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                                        </svg>
                                                    </button>
                                                </template>
                                            </div>
                                            <span class="text-xs font-semibold text-gray-600 min-w-[6.25rem]" x-text="labels[hoverRating || rating]"></span>
                                        </div>
                                        @error('reviewRating') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    <!-- Headline / Title Field with inner padding -->
                                    <div>
                                        <label for="review-title" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">
                                            Products <span class="text-gray-400 font-normal lowercase">(optional)</span>
                                        </label>
                                        <input id="review-title" 
                                               type="text" 
                                               wire:model="reviewTitle" 
                                               maxlength="255"
                                               placeholder="What's most important to know?"
                                               class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] transition-colors shadow-sm">
                                        @error('reviewTitle') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    <!-- Review Comment Field with inner padding -->
                                    <div>
                                        <label for="review-comment" class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">
                                            Review
                                        </label>
                                        <textarea id="review-comment" 
                                                  wire:model="reviewComment" 
                                                  rows="4" 
                                                  maxlength="2000"
                                                  placeholder="What did you like or dislike? How was the fit and quality?"
                                                  class="w-full p-4 rounded-xl border border-gray-200 text-sm leading-relaxed text-gray-900 placeholder:text-gray-400 focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] transition-colors shadow-sm"></textarea>
                                        @error('reviewComment') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                                        @error('review') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    <!-- Photos Upload Section -->
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700">
                                                Photos <span class="text-gray-400 font-normal lowercase">(optional, up to 5 images, max 10 MB each)</span>
                                            </label>
                                            <span class="text-xs text-gray-400 font-medium">{{ count($reviewPhotos) }}/5</span>
                                        </div>

                                        <div class="flex flex-wrap gap-3">
                                            @foreach($reviewPhotos as $index => $photo)
                                                <div class="relative w-20 h-20 rounded-xl overflow-hidden border border-gray-200 shadow-sm group">
                                                    <img src="{{ $photo->temporaryUrl() }}" alt="Selected photo" class="w-full h-full object-cover">
                                                    <button type="button" wire:click="removeReviewPhoto({{ $index }})"
                                                            class="absolute top-1 right-1 bg-black/70 hover:bg-black text-white rounded-full w-5 h-5 flex items-center justify-center text-xs transition-colors shadow">
                                                        &times;
                                                    </button>
                                                </div>
                                            @endforeach

                                            @if(count($reviewPhotos) < 5)
                                                <label class="w-20 h-20 rounded-xl border-2 border-dashed border-gray-200 flex flex-col items-center justify-center text-gray-400 cursor-pointer hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition-colors">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    <input type="file" wire:model="newReviewPhotos" multiple accept="image/*" class="hidden">
                                                </label>
                                            @endif
                                        </div>
                                        
                                        <p class="mt-1.5 text-xs text-gray-400" wire:loading wire:target="newReviewPhotos">Uploading photos...</p>
                                        @error('reviewPhotos') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                                        @error('reviewPhotos.*') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                                        @error('newReviewPhotos.*') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    <!-- Video Upload Section -->
                                    <div>
                                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-2">
                                            Video <span class="text-gray-400 font-normal lowercase">(optional, up to 1 minute, max 10 MB)</span>
                                        </label>

                                        @if($reviewVideo)
                                            <div class="relative w-40">
                                                <video src="{{ $reviewVideo->temporaryUrl() }}" controls class="w-40 rounded-xl border border-gray-200"></video>
                                                <button type="button" wire:click="removeReviewVideo"
                                                        class="absolute top-1 right-1 bg-black/60 hover:bg-black text-white rounded-full w-5 h-5 flex items-center justify-center text-xs transition-colors">
                                                    &times;
                                                </button>
                                            </div>
                                        @else
                                            <label class="inline-flex items-center gap-2 rounded-xl border-2 border-dashed border-gray-200 px-4 py-2.5 text-xs font-semibold text-gray-600 cursor-pointer hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                Choose a video
                                                <input type="file" wire:model="reviewVideo" accept="video/*" class="hidden">
                                            </label>
                                        @endif
                                        <p class="mt-1.5 text-xs text-gray-400" wire:loading wire:target="reviewVideo">Uploading video...</p>
                                        @error('reviewVideo') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    <button type="submit"
                                            style="background-color: var(--color-primary);"
                                            class="rounded-xl px-6 py-3 font-bold text-sm text-white transition-all hover:brightness-110 active:scale-95 disabled:opacity-50 shadow-md"
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

        <!-- Related Products Section (Mobile Carousel / Desktop Grid) -->
        @if($relatedProducts->count() > 0)
            <section class="mt-12">
                <div class="flex items-center justify-between mb-6 sm:mb-8">
                    <h2 class="text-xl sm:text-2xl font-extrabold text-gray-900 tracking-tight">Products You May Also Like</h2>
                    
                    <!-- Mobile swipe indicator -->
                    <span class="text-xs font-medium text-gray-400 sm:hidden">Swipe &rarr;</span>
                </div>

                <div class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-4 -mx-4 px-4 sm:mx-0 sm:px-0 sm:pb-0 sm:grid sm:grid-cols-2 lg:grid-cols-4 sm:gap-6 sm:overflow-visible no-scrollbar">
                    @foreach($relatedProducts as $relatedProduct)
                        <div class="w-[72vw] max-w-[280px] flex-shrink-0 snap-start sm:w-auto sm:max-w-none sm:flex-shrink">
                            <livewire:product-card :product="$relatedProduct" :key="'related-' . $relatedProduct->id" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
