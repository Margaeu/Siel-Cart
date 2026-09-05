{{--
    Product Card Component
    Layout featuring top-left logo overlay, title-case product name, and price below.
--}}
<div class="group relative flex flex-col bg-white">
    <a href="{{ route('products.show', $product->slug) }}" class="block w-full">

        <!-- Product Image Container -->
        <div class="relative aspect-[4/5] w-full overflow-hidden bg-white mb-3">
            
            <!-- Optional Brand/Store Logo Overlay (Top-Left) -->
            {{-- 
            <div class="absolute top-2 left-2 z-10 w-8 h-8">
                <img src="/path-to-your-logo.png" alt="Logo" class="w-full h-full object-contain">
            </div> 
            --}}

            @if($product->primaryImage)
                <img src="{{ $product->primaryImage->url }}"
                     alt="{{ $product->name }}"
                     class="w-full h-full object-contain group-hover:scale-105 transition duration-300 ease-in-out">
            @else
                <div class="w-full h-full flex items-center justify-center bg-gray-100">
                    <span class="text-4xl text-gray-400 font-light">
                        {{ substr($product->name, 0, 1) }}
                    </span>
                </div>
            @endif
        </div>

        <!-- Product Details -->
        <div class="px-1 flex flex-col gap-1">
            
            <!-- Product Title (Title Case, Clean Sans-Serif) -->
            <h3 class="font-medium text-base text-gray-900 line-clamp-2 leading-snug group-hover:text-gray-600 transition-colors">
                {{ $product->name }}
            </h3>

            <!-- Price Display -->
            <div class="text-base font-bold text-gray-900">
                {{ $product->display_price_label }}
            </div>

        </div>

    </a>
</div>