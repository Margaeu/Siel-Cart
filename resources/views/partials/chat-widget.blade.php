{{--
    AI shopping assistant widget.

    Originally lived inline in resources/views/livewire/home-page.blade.php and was
    dropped by commit a7ae819. Restored here as a layout partial so it renders on
    every storefront page rather than only the homepage.

    Accent colours: #557F13 (site primary) with #FFD801 highlights.
--}}

<!-- Floating AI Chatbot Toggle Button -->
<button id="chat-toggle" class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-5 py-3 bg-[#557F13] text-white font-semibold rounded-full shadow-xl hover:bg-[#3E5D0E] active:scale-95 transition-all duration-200 group">
    <svg class="w-6 h-6 text-[#FFD801] group-hover:rotate-12 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
    <span>Chat</span>
</button>

<!-- Chatbot Window -->
<div id="chat-widget" wire:ignore class="hidden fixed bottom-24 right-6 z-50 w-80 sm:w-96 h-[500px] bg-white rounded-2xl shadow-2xl border border-gray-100 flex-col overflow-hidden transition-all duration-300">
    <!-- Header -->
    <div class="bg-[#557F13] text-white p-4 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-[#FFD801]/20 flex items-center justify-center border border-[#FFD801]/40">
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
        <input type="text" id="chat-input" placeholder="Ask about products, orders..." class="flex-1 px-4 py-2.5 bg-gray-100 border border-transparent rounded-xl focus:bg-white focus:border-[#557F13] focus:outline-none text-sm text-gray-900 placeholder-gray-400 transition">
        <button id="chat-send" class="p-2.5 bg-[#557F13] text-white rounded-xl hover:bg-[#3E5D0E] active:scale-95 transition shadow-sm">
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
            inputField.focus();
        };
        closeBtn.onclick = () => {
            chatWidget.classList.add('hidden');
            chatWidget.classList.remove('flex');
        };

        function appendMessage(text, sender) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `max-w-[85%] p-3 text-sm transition-all duration-200 ${
                sender === 'user'
                    ? 'self-end bg-[#557F13] text-white rounded-2xl rounded-tr-none shadow-sm ml-auto'
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

        async function sendMessage() {
            const message = inputField.value.trim();
            if (!message) return;

            appendMessage(message, 'user');
            inputField.value = '';
            showTypingIndicator();

            try {
                // Point fetch to Laravel's internal API route
                const response = await fetch('{{ url('/api/chat') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
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
