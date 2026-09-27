{{--
    AI shopping assistant widget.

    Originally lived inline in resources/views/livewire/home-page.blade.php and was
    dropped by commit a7ae819. Restored here as a layout partial so it renders on
    every storefront page rather than only the homepage.

    Accent colours: the active theme's primary, with secondary highlights.
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
    <svg class="size-6 shrink-0 text-[var(--color-secondary)] transition-transform duration-200 motion-safe:group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
    <span class="hidden sm:inline">Chat</span>
</button>

<!-- Chatbot Window -->
{{--
    Below sm the window spans the viewport minus the 16px mobile gutter and
    takes its height from dvh, so it fits a 320px phone instead of overflowing
    it -- the old fixed w-80 plus right-6 measured 344px. From sm up the
    desktop/tablet panel stays compact enough to leave the storefront visible
    behind it, while the narrow-screen layout still uses the available width.
--}}
<div id="chat-widget" wire:ignore role="dialog" aria-label="Shopping assistant" class="hidden fixed bottom-20 left-4 right-4 z-50 h-[min(30rem,calc(100dvh-7rem))] flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-2xl transition-all duration-300 sm:bottom-24 sm:left-auto sm:right-6 sm:h-[27rem] sm:w-80">
    <!-- Header -->
    <div class="flex h-16 shrink-0 items-center justify-between bg-[var(--color-primary)] px-4 text-white shadow-sm">
        <div class="flex min-w-0 items-center gap-2.5">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-full border border-white/15 bg-white/10" aria-hidden="true">
                <svg class="size-5 text-[var(--color-secondary)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 10h.01M12 10h.01M16 10h.01M20 11.5c0 3.59-3.58 6.5-8 6.5a9.62 9.62 0 0 1-3.68-.72L4 18l1.28-3.06A5.62 5.62 0 0 1 4 11.5C4 7.91 7.58 5 12 5s8 2.91 8 6.5Z" />
                </svg>
            </div>
            <div class="min-w-0">
                <h4 class="truncate text-sm font-semibold leading-tight">Shopping Assistant</h4>
                <span class="mt-1 flex items-center gap-1.5 text-xs leading-none text-white/75">
                    <span class="size-2 rounded-full bg-[var(--color-secondary)] animate-pulse"></span>
                    Online
                </span>
            </div>
        </div>
        <button id="chat-close" type="button" aria-label="Close chat" class="inline-flex size-10 shrink-0 items-center justify-center rounded-full text-white/75 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">
            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Messages Area -->
    <div id="chat-messages" class="flex-1 space-y-2.5 overflow-y-auto bg-gray-50/50 p-3 text-xs">
        <div class="self-start max-w-[85%] rounded-2xl rounded-tl-none border border-gray-100 bg-white px-3 py-2.5 leading-relaxed text-gray-800 shadow-sm">
            Hello! How can I help you find what you're looking for today?
        </div>
        {{--
            The quick questions belong to the greeting, so they sit inside the
            transcript directly beneath it rather than in their own strip above
            the input. They disappear when the shopper sends their first
            question, whether they select a suggestion or type it themselves.
        --}}
        <div id="chat-suggestions">
            <!-- Buttons inserted by JS -->
        </div>
    </div>

    <!-- Input Area -->
    <div class="p-3 bg-white border-t border-gray-100 flex items-center gap-2">
        <label for="chat-input" class="sr-only">Message the shopping assistant</label>
        <input type="text" id="chat-input" maxlength="{{ \App\Http\Controllers\Api\ChatController::MAX_MESSAGE_LENGTH }}" placeholder="Ask about products, orders..." class="h-11 min-w-0 flex-1 rounded-xl border border-transparent bg-gray-100 px-3 text-xs text-gray-900 placeholder-gray-500 transition focus:border-[var(--color-primary)] focus:bg-white focus:outline-none">
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
            msgDiv.className = `max-w-[85%] px-3 py-2.5 text-xs leading-relaxed transition-all duration-200 ${
                sender === 'user'
                    ? 'self-end bg-[var(--color-primary)] text-white rounded-2xl rounded-tr-none shadow-sm ml-auto'
                    : 'self-start bg-white text-gray-800 rounded-2xl rounded-tl-none border border-gray-100 shadow-sm mr-auto'
            }`;

            if (sender === 'user') {
                msgDiv.style.whiteSpace = 'pre-wrap';
                msgDiv.textContent = text;
            } else {
                msgDiv.classList.add('chat-answer');
                msgDiv.innerHTML = window.renderChatMarkdown(text);
            }
            messagesBox.appendChild(msgDiv);
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }

        // Map the statuses the /api/chat route actually produces to something a
        // shopper can act on. 429 is the route's throttle:10,1, 422 is
        // ChatController::MAX_MESSAGE_LENGTH, and 419 is an expired CSRF token --
        // the token is baked into this script at render time, so it goes stale
        // once the session is invalidated (e.g. logging out in another tab).
        function errorMessageFor(status) {
            switch (status) {
                case 429: return "You're sending messages a little fast. Please wait a minute and try again.";
                case 422: return 'That message is too long. Please keep it under {{ \App\Http\Controllers\Api\ChatController::MAX_MESSAGE_LENGTH }} characters.';
                case 419: return 'Your session has expired. Please refresh the page and try again.';
                default:  return `Server Error (${status}): Could not reach the shopping assistant.`;
            }
        }

        // The one path to the backend. The input field and the suggestion
        // buttons both come through here, so suggestions disappear immediately
        // whichever way the shopper starts the conversation.
        async function sendMessageWithText(messageText) {
            const message = messageText.trim();
            if (!message) return;

            const suggestions = document.getElementById('chat-suggestions');
            if (suggestions) {
                suggestions.replaceChildren();
                suggestions.hidden = true;
            }

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
                    body: JSON.stringify({ message })
                });

                removeTypingIndicator();

                if (!response.ok) {
                    appendMessage(errorMessageFor(response.status), 'bot');
                } else {
                    const data = await response.json();
                    appendMessage(data.response || 'Error: Received invalid response from server.', 'bot');
                }
            } catch (error) {
                removeTypingIndicator();
                console.error('Fetch Error:', error);
                appendMessage('Network Error: Check browser console (F12) for details.', 'bot');
            }

            // Hand focus back to the input so the next question can be typed
            // straight away, including after a suggestion button was clicked.
            inputField.focus();
        }

        function showTypingIndicator() {
            const indicatorDiv = document.createElement('div');
            indicatorDiv.id = 'typing-indicator';
            indicatorDiv.className = 'self-start max-w-[85%] px-3 py-2.5 bg-white text-gray-400 rounded-2xl rounded-tl-none border border-gray-100 shadow-sm flex items-center gap-1 mr-auto';
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

        function sendMessage() {
            const message = inputField.value;
            inputField.value = '';
            sendMessageWithText(message);
        }

        // Suggested questions derived from server-side allowed topics
        const SUGGESTED_QUESTIONS = [
            'How do I place an order?',
            'What payment methods do you accept?',
            'Where and when do I pick up my order?'
        ];

        function renderSuggestions() {
            const container = document.getElementById('chat-suggestions');
            // Livewire may initialize the existing widget again; keep opening
            // suggestions hidden once the conversation has started.
            if (!container || container.hidden) return;
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
            wrapper.className = 'flex flex-col items-start gap-1.5 pt-0.5';

            SUGGESTED_QUESTIONS.forEach(q => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'max-w-[85%] rounded-xl border border-gray-200 bg-white px-3 py-2 text-left text-xs leading-snug whitespace-normal shadow-sm transition hover:border-[var(--color-primary)] hover:bg-gray-50';
                btn.innerText = q;
                btn.onclick = () => {
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
