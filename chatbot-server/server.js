import dotenv from 'dotenv';
dotenv.config();

import express from 'express';
import cors from 'cors';
import OpenAI from 'openai';

const app = express();
app.use(cors());
app.use(express.json());

const deepseek = new OpenAI({
    baseURL: 'https://openrouter.ai/api/v1',
    apiKey: process.env.OPENROUTER_API_KEY,
    defaultHeaders: {
        'HTTP-Referer': 'http://localhost:3000',
        'X-Title': 'GreenCobraCart E-Commerce Assistant',
    }
});

const FALLBACK_MODELS = [
    'openrouter/free',
    'deepseek/deepseek-chat:free',
    'google/gemini-2.0-flash-exp:free'
];

const STANDARD_REFUSAL = "I can only assist with GreenCobraCart FAQs (how to order, returns/refunds, data handling), product recommendations, and order status inquiries. How may I help you today?";

/**
 * Everything the assistant is allowed to state as fact about how the store
 * works. Without this the model invents a generic online-shop flow -- shipping
 * addresses, courier tracking, card payments -- none of which exist here.
 *
 * Keep this in sync with the order module: App\Livewire\CheckoutPage,
 * App\Models\Order, and App\Livewire\CancelOrderModal.
 */
const STORE_FACTS = `STORE FACTS (the only accurate description of how GreenCobraCart works):
CobraCart is the online store of the UBAP Office at Central Luzon State University. It is pickup-only and cash-only. There is no delivery, no courier, and no online payment of any kind.

HOW TO ORDER (use these steps whenever the customer asks how to order, how to buy, or how checkout works):
1. Browse the GreenCobraCart catalog and open the product you want.
2. Choose the variation (such as size or color) if the product has one, set the quantity, then add it to your cart.
3. Open your cart and review the items. The whole cart is checked out together, so remove anything you are not buying yet. If an item is out of stock or the quantity is more than the remaining stock, checkout is blocked until you fix or remove that item.
4. Proceed to checkout. There is nothing to fill in. Your name and email come from your account, and the pickup location (UBAP Office) and payment method (Cash on Pickup) are fixed and shown for confirmation only.
5. Review your items and total, then place the order. The total is only the merchandise subtotal. There is no shipping fee, no tax, and no delivery charge.
6. You will receive a confirmation email with your order number, and the order starts as Pending.
7. The UBAP staff prepare the order. Once it is ready, you receive a second email with your claim number, your pickup date, and your pickup time slot.
8. Go to the UBAP Office within your time slot, present your claim number, and pay in cash when you receive your items. The order is then marked Completed and Paid.

PAYMENT: Cash on Pickup only, paid in person at the UBAP Office when the items are handed over. Amounts are in Philippine pesos. GreenCobraCart does not accept credit or debit cards, GCash, bank transfers, e-wallets, or any online or advance payment, and it does not store payment details.

CLAIM NUMBER: A claim number is issued only when the order becomes Ready for Pickup, not at checkout. Before that the order has an order number only. Present the claim number at the UBAP Office to collect the order.

PICKUP: Orders must be claimed within the assigned date and time slot. Unclaimed orders are cancelled. Someone else may collect on the customer's behalf as long as they bring the proper authorization and the order details.

ORDER STATUS: A customer checks progress by logging in and opening My Orders, then the order. Statuses are Pending, Processing, Ready for Pickup, Completed, Cancelled, and the return statuses. There are no tracking numbers and no delivery updates because nothing is shipped.

CANCELLATION: A customer can cancel from the order details page only while the order is still Pending, choosing either change of mind or incorrect items. Once the order is being processed, they must contact the UBAP Office.

RETURNS AND REFUNDS: A return or refund can be requested from the order details page only after the order is Completed. The request is reviewed by the UBAP staff.

FORBIDDEN CLAIMS: Never mention or ask for a shipping address, delivery address, shipping method, shipping fee, delivery date, courier, tracking number, tracking link, card payment, e-wallet, online payment, or cash on delivery. Never say an order will be shipped or delivered. If a customer asks about delivery or online payment, tell them plainly that GreenCobraCart is pickup and cash-on-pickup only, then explain the pickup process.`;

// Pre-filter non-e-commerce inputs (Math, Coding, Simple Off-Topic)
function isIrrelevantQuery(text) {
    const query = text.trim().toLowerCase();

    // Whitelist casual greetings so they pass through to the LLM
    const GREETINGS = ['hi', 'hello', 'halu', 'hey', 'good morning', 'good afternoon', 'good evening', 'kumusta', 'yo'];
    if (GREETINGS.some(g => query === g || query.startsWith(g + ' '))) {
        return false;
    }

    // 1. Math expressions or simple equations (e.g., 1+1, 5*10, 100/2, "what is 2 + 2")
    const mathPattern = /^(\d+[\s\+\-\*\/\^%\=]+\d+|\b(what is|calculate|compute|solve)\b.*?\d+)/i;
    if (mathPattern.test(query)) return true;

    // 2. Direct simple math questions like "1+1", "2+2=", "10 - 3"
    if (/^\d+\s*[\+\-\*\/]\s*\d+/.test(query)) return true;

    // 3. Coding/programming requests
    if (/\b(write code|python|javascript|function|html|css|sql|script)\b/i.test(query)) return true;

    // 4. Common trivia / general off-topic queries
    if (/^(who is|what is the capital|tell me a story|write a poem|sing|meaning of life)/i.test(query)) return true;

    return false;
}

async function generateContentWithFallback(message, systemInstruction) {
    let lastError = null;

    for (const modelName of FALLBACK_MODELS) {
        try {
            const completion = await deepseek.chat.completions.create({
                model: modelName,
                temperature: 0.0, // Absolute minimum randomness to follow negative rules
                messages: [
                    { role: 'system', content: systemInstruction },
                    { role: 'user', content: message }
                ],
            });

            let text = completion.choices[0]?.message?.content;
            
            if (text) {
                // Strip out "User Safety: safe", "User safety: safe", "user:safe", etc.
                text = text.replace(/^(user\s*safety:\s*safe|user:safe)\s*/i, '').trim();

                // If the model ONLY outputted the safety tag and nothing else, return a fallback greeting
                if (!text) {
                    return "Hello! How can I help you find what you're looking for today?";
                }

                return text;
            }
        } catch (error) {
            console.warn(`Model [${modelName}] failed/rate-limited: ${error.message}. Trying next model...`);
            lastError = error;
        }
    }

    throw lastError;
}

app.post('/api/chat', async (req, res) => {
    try {
        const { message } = req.body;

        if (!message) {
            return res.status(400).json({ error: 'Message is required.' });
        }

        // STEP 1: Code-level check to instantly block basic math, trivia, or code queries
        if (isIrrelevantQuery(message)) {
            return res.json({ response: STANDARD_REFUSAL });
        }

        // STEP 2: Strict LLM System Prompt
        const systemInstruction = `CRITICAL ASSISTANT BOUNDARY:
You are strictly an e-commerce assistant for GreenCobraCart. You DO NOT answer math questions (such as 1+1, calculations, or arithmetic), trivia, programming queries, general knowledge, or unrelated topics.

PERMITTED TOPICS ONLY:
1. GREETINGS & SMALL TALK: Respond warmly to greetings (such as "hi", "halu", "hello", "good morning") with a friendly greeting, then ask how you can help them with GreenCobraCart products, orders, or FAQs.
2. STORE FAQS: Ordering process, pickup, payment, return & refund policies, cancellation, and privacy/data handling.
3. PRODUCT RECOMMENDATIONS: Finding GreenCobraCart products based on price limits, budget, categories, or store catalog.
4. ORDER STATUS: Order progress, pickup readiness, and claim numbers.

${STORE_FACTS}

ACCURACY RULE:
Answer questions about the store using only the STORE FACTS above. Never invent a policy, a step, a fee, or an option that is not stated there. If the facts do not cover the question, say you are not sure and advise the customer to contact the UBAP Office at ubap@clsu.edu.ph.

REFUSAL INSTRUCTIONS:
If the query is math (e.g., 1+1, 2+2, math problems), general knowledge, coding, or completely unrelated to GreenCobraCart e-commerce, output EXACTLY this response and NOTHING ELSE:
"${STANDARD_REFUSAL}"

FORMATTING:
- Standard plain text only. No Markdown formatting (no asterisks, bolding, or headers).
- Use numbers (1., 2.) or dashes (-) for lists.
- No formal signatures or placeholders.`;

        const responseText = await generateContentWithFallback(message, systemInstruction);

        return res.json({ response: responseText });

    } catch (error) {
        console.error('All models rate-limited or failed:', error);

        return res.status(200).json({ 
            response: "Good day! Our automated system is currently experiencing high inquiry volume. Kindly resend your message in a few moments." 
        });
    }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server listening on port ${PORT}`);
});