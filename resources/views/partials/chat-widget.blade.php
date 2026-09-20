{{--
    AI shopping assistant widget.

    Originally lived inline in resources/views/livewire/home-page.blade.php and was
    dropped by commit a7ae819. Restored here as a layout partial so it renders on
    every storefront page rather than only the homepage.

    Accent colours: #557F13 (site primary) with #FFD801 highlights.
--}}

<style>
    /* Hide native scrollbars for suggestion chips row */
    .no-scrollbar {
        -ms-overflow-style: none; /* IE and Edge */
        scrollbar-width: none; /* Firefox */
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none; /* Chrome, Safari, Opera */
    }
    /* Mobile-friendly fullscreen class */
    #chat-widget.chat-mobile {
        left: 0 !important;
        right: 0 !important;
        top: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        max-width: none !important;
        border-radius: 0 !important;
    }
    @media (min-width: 641px) {
        /* Ensure chat-mobile only becomes fullscreen on small screens */
        #chat-widget.chat-mobile {
            left: auto !important;
            right: 1rem !important;
            top: 1rem !important;
            bottom: 1rem !important;
            width: 24rem !important;
            height: calc(100vh - 2rem) !important;
            border-radius: 0.75rem !important;
        }
    }
    /* Minimal suggestion layout: two-column grid of pills, compact and non-scrolling */
    #chat-suggestions .suggestions-card {
        background: transparent;
        padding: 0.12rem 0.2rem;
        max-width: 96%;
        margin: 0.08rem auto;
        overflow: visible;
    }
    #chat-suggestions .suggestions-head {
        font-size: 0.78rem;
        color: #374151;
        margin-bottom: 0.18rem;
        font-weight: 600;
        display: block;
    }
    #chat-suggestions .suggestions-head-pill {
        display: inline-block;
        background: #f3f4f6;
        color: #374151;
        padding: 0.18rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.78rem;
        font-weight: 600;
    }
    #chat-suggestions .suggestions-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.28rem;
        align-items: start;
    }
    #chat-suggestions .suggestion-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: #fff;
        border: 1px solid rgba(16,24,40,0.04);
        padding: 0.18rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.82rem;
        color: #111827;
        cursor: pointer;
        transition: background .08s ease, transform .08s ease;
        justify-content: flex-start;
        width: 100%;
        box-sizing: border-box;
        white-space: normal;
    }
    #chat-suggestions .suggestion-chip:hover { background: #f8fafc; transform: translateY(-1px); }
    @media (max-width: 640px) {
        #chat-suggestions .suggestions-grid { grid-template-columns: repeat(1, minmax(0, 1fr)); }
    }
</style>

<!-- Floating AI Chatbot Toggle Button -->
<button id="chat-toggle" class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 px-5 py-3 bg-[var(--color-primary)] text-white font-semibold rounded-full shadow-xl hover:bg-[var(--color-primary-hover)] active:scale-95 transition-all duration-200 group">
    <svg class="w-6 h-6 text-[#FFD801] group-hover:rotate-12 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
    <span>Chat</span>
</button>

<!-- Chatbot Window -->
<div id="chat-widget" wire:ignore class="hidden fixed bottom-24 right-6 z-50 w-80 sm:w-96 h-[500px] bg-white rounded-2xl shadow-2xl border border-gray-100 flex-col overflow-hidden transition-all duration-300">
    <!-- Header -->
    <div class="bg-[var(--color-primary)] text-white p-4 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-3">
            <div>
                <h4 class="font-semibold text-sm leading-tight">Shopping Assistant</h4>
                <span class="text-xs text-emerald-200 flex items-center gap-1.5 mt-0.5">
                    <span class="w-2 h-2 rounded-full bg-[var(--color-secondary)] animate-pulse"></span>
                    Online
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
            Hello! How can I help you find what you're looking for today?
        </div>
    </div>
    <!-- Suggested quick questions (moved below messages so messages can fill the area) -->
    <div id="chat-suggestions" class="px-3 pt-2 pb-3 bg-transparent border-t border-gray-100">
        <!-- Buttons inserted by JS -->
    </div>

    <!-- Input Area -->
    <div class="p-3 bg-white border-t border-gray-100 flex items-center gap-2">
        <input type="text" id="chat-input" placeholder="Ask about products, orders..." class="flex-1 px-4 py-2.5 bg-gray-100 border border-transparent rounded-xl focus:bg-white focus:border-[var(--color-primary)] focus:outline-none text-sm text-gray-900 placeholder-gray-400 transition">
        <button id="chat-send" class="p-2.5 bg-[var(--color-primary)] text-white rounded-xl hover:bg-[var(--color-primary-hover)] active:scale-95 transition shadow-sm">
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
            // On small screens, switch to mobile/fullscreen mode
            if (window.innerWidth <= 640) {
                chatWidget.classList.add('chat-mobile');
            }
            inputField.focus();
        };
        closeBtn.onclick = () => {
            chatWidget.classList.add('hidden');
            chatWidget.classList.remove('flex', 'chat-mobile');
        };
        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                chatWidget.classList.add('hidden');
                chatWidget.classList.remove('flex', 'chat-mobile');
            }
        });
        // If the viewport is resized larger, remove mobile class to avoid stuck fullscreen
        window.addEventListener('resize', () => {
            if (window.innerWidth > 640) chatWidget.classList.remove('chat-mobile');
        });

        function appendMessage(text, sender) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `max-w-[85%] p-3 text-sm transition-all duration-200 ${
                sender === 'user'
                    ? 'self-end bg-[var(--color-primary)] text-white rounded-2xl rounded-tr-none shadow-sm ml-auto'
                    : 'self-start bg-white text-gray-800 rounded-2xl rounded-tl-none border border-gray-100 shadow-sm mr-auto'
            }`;

            msgDiv.innerText = text;
            messagesBox.appendChild(msgDiv);
            messagesBox.scrollTop = messagesBox.scrollHeight;
            adjustMessagesPadding();
        }

        // Send a prepared message (used by suggestion buttons)
        async function sendMessageWithText(messageText) {
            const message = messageText.trim();
            if (!message) return;

            appendMessage(message, 'user');
            showTypingIndicator();

            try {
                const response = await fetch("{{ url('/api/chat') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ message: message })
                });

                removeTypingIndicator();

                if (!response.ok) {
                    appendMessage(`Server Error (${response.status}): Could not reach Laravel API.`, 'bot');
                    return;
                }

                const data = await response.json();

                if (data.response) {
                    appendMessage(data.response, 'bot');
                    renderSuggestions();
                    // Ensure input stays visible: adjust padding, scroll messages and focus input
                    setTimeout(() => {
                        try {
                            adjustMessagesPadding();
                            messagesBox.scrollTop = messagesBox.scrollHeight;
                            inputField.focus();
                            inputField.scrollIntoView({ block: 'nearest' });
                            chatWidget.scrollIntoView({ block: 'end' });
                        } catch (e) { /* ignore */ }
                    }, 50);
                } else {
                    appendMessage('Error: Received invalid response from server.', 'bot');
                    renderSuggestions();
                    setTimeout(() => {
                        try {
                            adjustMessagesPadding();
                            messagesBox.scrollTop = messagesBox.scrollHeight;
                            inputField.focus();
                            inputField.scrollIntoView({ block: 'nearest' });
                            chatWidget.scrollIntoView({ block: 'end' });
                        } catch (e) { /* ignore */ }
                    }, 50);
                }
            } catch (error) {
                removeTypingIndicator();
                console.error('Fetch Error:', error);
                appendMessage('Network Error: Check browser console (F12) for details.', 'bot');
                renderSuggestions();
            }
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
                const response = await fetch("{{ url('/api/chat') }}", {
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
                    renderSuggestions();
                    return;
                }

                const data = await response.json();

                if (data.response) {
                    appendMessage(data.response, 'bot');
                    renderSuggestions();
                    // Keep the input visible and focused after a reply
                    try { inputField.focus(); } catch (e) { /* ignore */ }
                } else {
                    appendMessage('Error: Received invalid response from server.', 'bot');
                    renderSuggestions();
                    try { inputField.focus(); } catch (e) { /* ignore */ }
                }
            } catch (error) {
                removeTypingIndicator();
                console.error('Fetch Error:', error);
                appendMessage('Network Error: Check browser console (F12) for details.', 'bot');
                renderSuggestions();
            }
        }

        // Suggested questions derived from server-side allowed topics
        const SUGGESTED_QUESTIONS = [
            'How do I place an order?',
            'What payment methods do you accept?',
            'Where and when do I pick up my order?',
            'How can I check my order status?',
            'What is your return and refund policy?',
            'Can you recommend products under ₱500?'
        ];

        // Track suggestions that the user has already used in this session
        const usedSuggestions = new Set();

        function renderSuggestions() {
            const container = document.getElementById('chat-suggestions');
            if (!container) return;
            container.innerHTML = '';

            const card = document.createElement('div');
            card.className = 'suggestions-card';

            const head = document.createElement('div');
            head.className = 'suggestions-head';
            head.innerText = 'Quick actions';

            const grid = document.createElement('div');
            grid.className = 'suggestions-grid';

                SUGGESTED_QUESTIONS.forEach(q => {
                    if (usedSuggestions.has(q)) return; // skip used ones

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'suggestion-chip';
                    btn.innerText = q;
                    btn.onclick = () => {
                        // hide suggestions immediately so UI doesn't jump
                        const containerInner = document.getElementById('chat-suggestions');
                        if (containerInner) containerInner.style.display = 'none';
                        usedSuggestions.add(q);
                        sendMessageWithText(q);
                    };
                    grid.appendChild(btn);
                });

            card.appendChild(head);
            card.appendChild(grid);
            // Only show container if there are suggestions available
            if (grid.childElementCount > 0) {
                container.appendChild(card);
                container.style.display = '';
            } else {
                container.style.display = 'none';
            }

            adjustMessagesPadding();
        }

        // Ensure messages area has bottom padding to avoid overlap with suggestions + input
        function adjustMessagesPadding() {
            const suggestions = document.getElementById('chat-suggestions');
            const inputArea = document.querySelector('#chat-widget > div:last-of-type');
            if (!messagesBox) return;
            let extra = 0;
            if (suggestions) extra += suggestions.offsetHeight;
            if (inputArea) extra += inputArea.offsetHeight;
            // add some breathing room
            extra += 18;
            messagesBox.style.paddingBottom = extra + 'px';
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }

        // Render suggestions immediately so users see them before typing
        renderSuggestions();

        sendBtn.onclick = sendMessage;
        inputField.onkeypress = (e) => {
            if (e.key === 'Enter') sendMessage();
        };
    }

    document.addEventListener('DOMContentLoaded', initChatbot);
    document.addEventListener('livewire:navigated', initChatbot);
</script>
