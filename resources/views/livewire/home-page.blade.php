<div>
    <!-- Hero Section -->
    <section class="bg-blue-600 text-white py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold mb-4">
                    Welcome to {{ config('app.name') }}
                </h1>
                <p class="text-xl md:text-2xl mb-8 text-indigo-100">
                    Discover amazing products at unbeatable prices
                </p>
                <a href="{{ route('products.index') }}" 
                   class="inline-block bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                    Shop Now
                </a>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="py-16 bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-8">Shop by Category</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                @foreach($categories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}" 
                       class="group">
                        <div class="aspect-square rounded-lg overflow-hidden bg-gray-100 mb-3">
                            @if($category->image)
                                <img src="{{ asset('storage/' . $category->image) }}" 
                                     alt="{{ $category->name }}"
                                     class="w-full h-full object-cover group-hover:scale-110 transition duration-300">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-blue-600">
                                    <span class="text-4xl text-white">{{ substr($category->name, 0, 1) }}</span>
                                </div>
                            @endif
                        </div>
                        <h3 class="text-center font-medium text-gray-900 group-hover:text-blue-600">
                            {{ $category->name }}
                        </h3>
                        <p class="text-center text-sm text-gray-500">{{ $category->products_count }} items</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-16 bg-gray-50">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Featured Products</h2>
                <a href="{{ route('products.index', ['featured' => 1]) }}" 
                   class="text-blue-600 hover:text-indigo-700 font-medium">
                    View All →
                </a>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($featuredProducts as $product)
                    <livewire:product-card :product="$product" :key="$product->id"  lazy />
                @endforeach
            </div>
        </div>
    </section>

    <!-- New Arrivals -->
    <section class="py-16 bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-3xl font-bold text-gray-900">New Arrivals</h2>
                <a href="{{ route('products.index', ['sort' => 'newest']) }}" 
                   class="text-blue-600 hover:text-indigo-700 font-medium">
                    View All →
                </a>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($newArrivals as $product)
                    <livewire:product-card :product="$product" :key="'new-' . $product->id"  />
                @endforeach
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="py-16 bg-gray-50">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 text-blue-600 rounded-full mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Quality Guarantee</h3>
                    <p class="text-gray-600">All products are carefully selected and quality tested</p>
                </div>
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 text-blue-600 rounded-full mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Fast Shipping</h3>
                    <p class="text-gray-600">Quick delivery right to your doorstep</p>
                </div>
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 text-blue-600 rounded-full mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Secure Payment</h3>
                    <p class="text-gray-600">Your payment information is safe with us</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Floating AI Chatbot Toggle Button -->
    <button id="chat-toggle" class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-5 py-3 bg-[#1E6031] text-white font-semibold rounded-full shadow-xl hover:bg-[#164724] active:scale-95 transition-all duration-200 group">
        <svg class="w-6 h-6 text-[#E0A70D] group-hover:rotate-12 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        <span>Chat with AI</span>
    </button>

    <!-- Chatbot Window -->
    <div id="chat-widget" wire:ignore class="hidden fixed bottom-24 right-6 z-50 w-80 sm:w-96 h-[500px] bg-white rounded-2xl shadow-2xl border border-gray-100 flex-col overflow-hidden transition-all duration-300">
        <!-- Header -->
        <div class="bg-[#1E6031] text-white p-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-[#E0A70D]/20 flex items-center justify-center border border-[#E0A70D]/40">
                    <span class="text-lg">🤖</span>
                </div>
                <div>
                    <h4 class="font-semibold text-sm leading-tight">Shopping Assistant</h4>
                    <span class="text-xs text-emerald-200 flex items-center gap-1.5 mt-0.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Online
                    </span>
                </div>
            </div>
            <button id="chat-close" class="text-white/80 hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Messages Area -->
        <div id="chat-messages" class="flex-1 p-4 overflow-y-auto space-y-3 bg-gray-50/50 text-sm">
            <div class="self-start max-w-[85%] p-3 rounded-2xl rounded-tl-none bg-white text-gray-800 border border-gray-100 shadow-sm">
                Hello! 👋 How can I help you find what you're looking for today?
            </div>
        </div>

        <!-- Input Area -->
        <div class="p-3 bg-white border-t border-gray-100 flex items-center gap-2">
            <input type="text" id="chat-input" placeholder="Ask about products, orders..." class="flex-1 px-4 py-2.5 bg-gray-100 border border-transparent rounded-xl focus:bg-white focus:border-[#1E6031] focus:outline-none text-sm text-gray-900 placeholder-gray-400 transition">
            <button id="chat-send" class="p-2.5 bg-[#1E6031] text-white rounded-xl hover:bg-[#164724] active:scale-95 transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
            </button>
        </div>
    </div>

 <script>
    function initChatbot() {
        const toggleBtn = document.getElementById('chat-toggle');
        const closeBtn = document.getElementById('chat-close');
        const chatWidget = document.getElementById('chat-widget');
        const sendBtn = document.getElementById('chat-send');
        const inputField = document.getElementById('chat-input');
        const messagesBox = document.getElementById('chat-messages');

        if (!toggleBtn || !chatWidget) return;

        toggleBtn.onclick = () => { 
            chatWidget.classList.remove('hidden'); 
            chatWidget.classList.add('flex');
        };
        closeBtn.onclick = () => { 
            chatWidget.classList.add('hidden'); 
            chatWidget.classList.remove('flex');
        };

        function appendMessage(text, sender) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `max-w-[85%] p-3 text-sm transition-all duration-200 ${
                sender === 'user' 
                    ? 'self-end bg-[#1E6031] text-white rounded-2xl rounded-tr-none shadow-sm ml-auto' 
                    : 'self-start bg-white text-gray-800 rounded-2xl rounded-tl-none border border-gray-100 shadow-sm mr-auto'
            }`;

            msgDiv.innerText = text;
            messagesBox.appendChild(msgDiv);
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }

        function showTypingIndicator() {
            const indicatorDiv = document.createElement('div');
            indicatorDiv.id = 'typing-indicator';
            indicatorDiv.className = 'self-start max-w-[85%] p-3 bg-white text-gray-400 rounded-2xl rounded-tl-none border border-gray-100 shadow-sm flex items-center gap-1 mr-auto';
            indicatorDiv.innerHTML = `
                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"></span>
                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce [animation-delay:0.2s]"></span>
                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce [animation-delay:0.4s]"></span>
            `;
            messagesBox.appendChild(indicatorDiv);
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }

        function removeTypingIndicator() {
            const indicator = document.getElementById('typing-indicator');
            if (indicator) indicator.remove();
        }

        // ==========================================
        // PASTE YOUR NEW sendMessage() FUNCTION HERE
        // ==========================================
        async function sendMessage() {
            const message = inputField.value.trim();
            if (!message) return;

            appendMessage(message, 'user');
            inputField.value = '';
            showTypingIndicator();

            try {
                // Point fetch to Laravel's internal API route
                const response = await fetch('/api/chat', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message })
                });

                removeTypingIndicator();

                if (!response.ok) {
                    appendMessage(`Server Error (${response.status}): Could not reach Laravel API.`, 'bot');
                    return;
                }

                const data = await response.json();
                
                if (data.response) {
                    appendMessage(data.response, 'bot');
                } else {
                    appendMessage('Error: Received invalid response from server.', 'bot');
                }
            } catch (error) {
                removeTypingIndicator();
                console.error('Fetch Error:', error);
                appendMessage('Network Error: Check browser console (F12) for details.', 'bot');
            }
        }

        sendBtn.onclick = sendMessage;
        inputField.onkeypress = (e) => {
            if (e.key === 'Enter') sendMessage();
        };
    }

    document.addEventListener('DOMContentLoaded', initChatbot);
    document.addEventListener('livewire:navigated', initChatbot);
</script>
</div>