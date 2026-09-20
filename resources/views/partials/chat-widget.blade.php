{{--
    AI shopping assistant widget.

    Originally lived inline in resources/views/livewire/home-page.blade.php and was
    dropped by commit a7ae819. Restored here as a layout partial so it renders on
    every storefront page rather than only the homepage.

    Accent colours: #557F13 (site primary) with #FFD801 highlights.
--}}

{{--
    This partial used to carry a style block with two rules, both now gone:

    .no-scrollbar hid the scrollbar on the horizontal suggestion strip. The
    suggestions stack vertically inside the transcript now, so there is no strip
    and nothing to hide -- and the transcript's own scrollbar should stay
    visible.

    .chat-mobile forced the panel to 100vw/100vh on small screens. The panel
    sizes itself responsively through its own utility classes below, so the
    override was both redundant and wrong: it cancelled the 16px mobile gutter
    and used vh, which overflows a phone whose browser chrome is showing.
--}}

<!-- Floating AI Chatbot Toggle Button -->
{{--
    Icon-only 48x48 below sm, the original labelled pill from sm up. The pill
    was 110px wide at every width and sat over the product grid on a phone;
    the square button keeps the same corner without reaching into a card's
    name or price.

    Colours come from the theme variables, not literals: the palette is
    database-driven (Admin -> Design -> Color Themes & Fonts), so a hardcoded
    #557F13 here would ignore whichever theme is active.
--}}
<button id="chat-toggle" type="button"
        aria-label="Open shopping assistant"
        aria-expanded="false"
        aria-controls="chat-widget"
        class="group fixed bottom-4 right-4 z-50 inline-flex size-12 items-center justify-center rounded-full bg-[var(--color-primary)] font-semibold text-white shadow-xl transition-all duration-200 hover:bg-[var(--color-primary-hover)] active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 sm:bottom-6 sm:right-6 sm:size-auto sm:gap-2.5 sm:px-5 sm:py-3">
    <svg class="size-6 shrink-0 text-[#FFD801] transition-transform duration-200 motion-safe:group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
    <span class="hidden sm:inline">Chat</span>
</button>

<!-- Chatbot Window -->
{{--
    Below sm the window spans the viewport minus the 16px mobile gutter and
    takes its height from dvh, so it fits a 320px phone instead of overflowing
    it -- the old fixed w-80 plus right-6 measured 344px. From sm up the
    original 384x500 panel is unchanged.
--}}
<div id="chat-widget" wire:ignore role="dialog" aria-label="Shopping assistant" class="hidden fixed bottom-20 left-4 right-4 z-50 h-[min(30rem,calc(100dvh-7rem))] flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-2xl transition-all duration-300 sm:bottom-24 sm:left-auto sm:right-6 sm:h-[500px] sm:w-96">
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
        <button id="chat-close" type="button" aria-label="Close chat" class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">
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
        {{--
            The quick questions belong to the greeting, so they sit inside the
            transcript directly beneath it rather than in their own strip above
            the input. They scroll away with the greeting once the conversation
            moves on, which is the point: they are an opening prompt, not a
            permanent toolbar competing with the input field.
        --}}
        <div id="chat-suggestions">
            <!-- Buttons inserted by JS -->
        </div>
    </div>

    <!-- Input Area -->
    <div class="p-3 bg-white border-t border-gray-100 flex items-center gap-2">
        <label for="chat-input" class="sr-only">Message the shopping assistant</label>
        <input type="text" id="chat-input" placeholder="Ask about products, orders..." class="h-11 min-w-0 flex-1 rounded-xl border border-transparent bg-gray-100 px-4 text-sm text-gray-900 placeholder-gray-500 transition focus:border-[var(--color-primary)] focus:bg-white focus:outline-none">
        <button id="chat-send" type="button" aria-label="Send message" class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--color-primary)] text-white shadow-sm transition hover:bg-[var(--color-primary-hover)] active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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

        // Open and close are two entry points for the same state, and the
        // toggle button's aria-expanded has to follow both of them.
        function openChat() {
            chatWidget.classList.remove('hidden');
            chatWidget.classList.add('flex');
            toggleBtn.setAttribute('aria-expanded', 'true');
            inputField.focus();
        }

        function closeChat() {
            chatWidget.classList.add('hidden');
            chatWidget.classList.remove('flex');
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.focus();
        }

        toggleBtn.onclick = () => {
            chatWidget.classList.contains('hidden') ? openChat() : closeChat();
        };
        closeBtn.onclick = closeChat;

        chatWidget.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeChat();
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
                } else {
                    appendMessage('Error: Received invalid response from server.', 'bot');
                    renderSuggestions();
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
                } else {
                    appendMessage('Error: Received invalid response from server.', 'bot');
                    renderSuggestions();
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

            // Stacked vertically, not in a horizontal strip. The strip clipped
            // every question past the first: "Where and when do I pick up my
            // order?" rendered as "Where and when do I pi" with the rest behind
            // a hidden-scrollbar overflow, so the options were unreadable
            // without a drag that nothing advertised.
            //
            // items-start keeps each chip the width of its own text instead of
            // stretching it across the panel, so the column reads as a list of
            // replies under the greeting rather than a menu bar. max-w matches
            // the message bubbles above it; whitespace-normal lets a long
            // question wrap to a second line instead of overflowing.
            const wrapper = document.createElement('div');
            wrapper.className = 'flex flex-col items-start gap-2 pt-1';

            SUGGESTED_QUESTIONS.forEach(q => {
                if (usedSuggestions.has(q)) return; // skip used ones

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'max-w-[85%] text-left whitespace-normal text-sm px-4 py-2 bg-white border border-gray-200 rounded-full shadow-sm hover:bg-gray-50 hover:border-[var(--color-primary)] transition';
                btn.innerText = q;
                btn.onclick = () => {
                    usedSuggestions.add(q);
                    renderSuggestions();
                    sendMessageWithText(q);
                };
                wrapper.appendChild(btn);
            });

            container.appendChild(wrapper);
        }

        // adjustMessagesPadding() used to run here and after every appended
        // message. It added the suggestion and input heights as bottom padding
        // on the message list to stop them overlapping it, but they never
        // overlapped -- #chat-widget is a flex column and they were siblings, so
        // the flex-1 list was already laid out above them. It also scrolled the
        // list to the bottom, which is now actively wrong: the suggestions sit
        // under the greeting, and scrolling to the bottom on first render pushed
        // the greeting they belong to off the top of a short panel. appendMessage
        // still scrolls on its own, which is the only place that should.

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
