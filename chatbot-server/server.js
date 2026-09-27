import dotenv from 'dotenv';
dotenv.config();

import express from 'express';
import cors from 'cors';
import OpenAI from 'openai';
import mysql from 'mysql2/promise';

const app = express();
app.use(cors());
app.use(express.json());

// 1. Initialize OpenRouter / OpenAI Client
const deepseek = new OpenAI({
    baseURL: 'https://openrouter.ai/api/v1',
    apiKey: process.env.OPENROUTER_API_KEY,
    defaultHeaders: {
        'HTTP-Referer': 'http://localhost:3000',
        'X-Title': 'Siel Cart E-Commerce Assistant',
    }
});

// 2. Initialize Database Connection Pool
const dbPool = mysql.createPool({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'siel_cart',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

const FALLBACK_MODELS = [
    'openrouter/free',
    'deepseek/deepseek-chat:free',
    'google/gemini-2.0-flash-exp:free'
];

const STANDARD_REFUSAL = "I can only assist with Siel Cart FAQs (how to order, returns/refunds, data handling), product recommendations, and order status inquiries. How may I help you today?";

// User-friendly error message when LLM/Server fails or times out
const FRIENDLY_ERROR_MESSAGE = "Our assistant is temporarily unavailable. Please browse our catalog on the store page or contact the UBAP Office directly for immediate assistance.";

/**
 * Static store rules and FAQ boundaries.
 */
const STORE_FACTS = `STORE FACTS (the only accurate description of how Siel Cart works):
SielCart is the online store of the UBAP Office at Central Luzon State University. It is pickup-only and cash-only. There is no delivery, no courier, and no online payment of any kind.

HOW TO ORDER:
1. Select product variation & quantity, then add to cart.
2. Review cart & proceed to checkout (pickup location & cash payment are fixed).
3. Place order to receive an Order Number (Status: Pending).
4. When ready, receive an email with your Claim Number, pickup date, and time slot.
5. Present Claim Number & pay cash in person at the UBAP Office to collect items.

PICKUP & CANCELLATION:
- Claim Numbers are issued ONLY when status is "Ready for Pickup".
- Orders must be claimed within assigned time slots. Unclaimed orders are cancelled.
- To reschedule pickup, contact UBAP Office by email/in person.
- Cancel orders on "My Orders" page ONLY while status is "Pending".

RETURNS & PRIVACY:
- Returns/refunds cannot be requested on the website. Contact UBAP Office directly for damaged/incorrect items.
- For data privacy questions, link to: [Privacy Policy](/privacy-policy).
- For terms questions, link to: [Terms & Conditions](/terms-and-conditions).

FORBIDDEN CLAIMS:
- Never mention shipping fees, delivery, tracking numbers, or online payments.
- If asked about delivery or online payment, state clearly that Siel Cart is pickup and cash-on-pickup only.`;

// Pre-filter non-e-commerce inputs (Math, Coding, Simple Off-Topic)
function isIrrelevantQuery(text) {
    const query = text.trim().toLowerCase();

    const GREETINGS = ['hi', 'hello', 'halu', 'hey', 'good morning', 'good afternoon', 'good evening', 'kumusta', 'yo'];
    if (GREETINGS.some(g => query === g || query.startsWith(g + ' '))) {
        return false;
    }

    const mathPattern = /^(\d+[\s\+\-\*\/\^%\=]+\d+|\b(what is|calculate|compute|solve)\b.*?\d+)/i;
    if (mathPattern.test(query)) return true;
    if (/^\d+\s*[\+\-\*\/]\s*\d+/.test(query)) return true;
    if (/\b(write code|python|javascript|function|html|css|sql|script)\b/i.test(query)) return true;
    if (/^(who is|what is the capital|tell me a story|write a poem|sing|meaning of life)/i.test(query)) return true;

    return false;
}

/**
 * Fetch available/in-stock products directly from database
 */
async function fetchAvailableProducts() {
    try {
        const [rows] = await dbPool.query(
            'SELECT name, price FROM products WHERE is_active = 1 AND stock > 0'
        );
        return rows;
    } catch (dbError) {
        console.error('Database fetch error:', dbError.message);
        return [];
    }
}

/**
 * Universal product pre-filtering across ALL categories and price constraints
 */
function getProductSuggestionsByQuery(userQuery, products) {
    const text = userQuery.toLowerCase();
    
    // Extract numerical target price (e.g., "200", "under 300 pesos", "below ₱500")
    const priceMatch = text.match(/(\d+)\s*(pesos|php|₱)?/i);
    const targetPrice = priceMatch ? parseFloat(priceMatch[1]) : null;

    // Standard product category keyword mappings
    const CATEGORIES = {
        apparel: ['shirt', 'tshirt', 't-shirt', 'hoodie', 'jacket', 'cap', 'hat', 'clothes', 'wear', 'apparel'],
        stationery: ['pen', 'ballpen', 'notebook', 'paper', 'pencil', 'pad', 'stationery', 'supplies', 'school'],
        accessories: ['lanyard', 'holder', 'id holder', 'keychain', 'badge', 'accessory', 'accessories'],
        bags: ['bag', 'tote', 'totebag', 'backpack', 'pouch'],
        drinkware: ['mug', 'tumbler', 'cup', 'bottle', 'flask', 'water bottle']
    };

    let filtered = products;

    // 1. Category Search: Match query against category synonyms or direct product name keywords
    let matchedCategoryItems = [];
    for (const [category, keywords] of Object.entries(CATEGORIES)) {
        if (keywords.some(kw => text.includes(kw))) {
            const matches = products.filter(p => 
                keywords.some(kw => p.name.toLowerCase().includes(kw))
            );
            matchedCategoryItems.push(...matches);
        }
    }

    // Deduplicate matches if category matches were found
    if (matchedCategoryItems.length > 0) {
        filtered = Array.from(new Set(matchedCategoryItems));
    }

    // 2. Budget Filtering: Apply price constraints if a number is present
    if (targetPrice) {
        if (text.includes('under') || text.includes('below') || text.includes('less than')) {
            const underItems = filtered.filter(p => p.price <= targetPrice);
            filtered = underItems.length > 0 ? underItems : filtered.filter(p => p.price <= targetPrice * 1.25);
        } else {
            const matching = filtered.filter(p => p.price <= targetPrice * 1.2);
            filtered = matching.length > 0 ? matching : filtered;
        }
    }

    // 3. Limit recommendations strictly to a maximum of 3 items
    const topThree = filtered.slice(0, 3);

    return topThree.map(p => `- **\({p.name}**: ₱\){p.price}`).join('\n');
}

async function generateContentWithFallback(message, systemInstruction) {
    let lastError = null;

    for (const modelName of FALLBACK_MODELS) {
        try {
            const completion = await deepseek.chat.completions.create({
                model: modelName,
                temperature: 0.0,
                messages: [
                    { role: 'system', content: systemInstruction },
                    { role: 'user', content: message }
                ],
            });

            let text = completion.choices[0]?.message?.content;
            
            if (text) {
                text = text.replace(/^(user\s*safety:\s*safe|user:safe)\s*/i, '').trim();

                if (text.length > 0) {
                    return text;
                }
            }
            throw new Error(`Model [${modelName}] returned an empty text payload.`);
        } catch (error) {
            console.warn(`Model [\({modelName}] failed/rate-limited:\){error.message}. Trying next model...`);
            lastError = error;
        }
    }

    throw lastError || new Error("All fallback models failed.");
}

app.post('/api/chat', async (req, res) => {
    try {
        const { message } = req.body;

        if (!message) {
            return res.status(400).json({ error: 'Message is required.' });
        }

        // STEP 1: Code-level check to instantly block math, coding, or trivia queries
        if (isIrrelevantQuery(message)) {
            return res.json({ response: STANDARD_REFUSAL });
        }

        // STEP 2: Fetch DB products (with hardcoded fallback if DB is down/empty)
        let dbProducts = [];
        try {
            dbProducts = await fetchAvailableProducts();
        } catch (dbErr) {
            console.error('Failed to fetch from DB:', dbErr);
        }

        if (!dbProducts || dbProducts.length === 0) {
            dbProducts = [
                { name: "UBAP Ballpen", price: 20 },
                { name: "CLSU Notebook", price: 50 },
                { name: "Siel Cart Lanyard", price: 80 },
                { name: "CLSU ID Holder", price: 100 },
                { name: "UBAP Mug", price: 200 },
                { name: "Siel Cart Tote Bag", price: 200 },
                { name: "CLSU Basic Shirt", price: 250 },
                { name: "CLSU Cap", price: 250 },
                { name: "CLSU T-Shirt", price: 350 },
                { name: "UBAP Hoodie", price: 750 }
            ];
        }

        // STEP 3: DIRECT PRODUCT INTERCEPTOR (Bypasses LLM disclaimers entirely)
        const msgLower = message.toLowerCase();
        const isRecommendationQuery = 
            msgLower.includes('suggest') || 
            msgLower.includes('recommend') || 
            msgLower.includes('product') || 
            msgLower.includes('item') || 
            msgLower.includes('price') || 
            msgLower.includes('pesos') || 
            msgLower.includes('php') || 
            msgLower.includes('under') || 
            msgLower.includes('below') || 
            msgLower.includes('shirt') || 
            msgLower.includes('apparel') || 
            msgLower.includes('mug') || 
            msgLower.includes('bag') || 
            msgLower.includes('notebook') || 
            msgLower.includes('pen');

        if (isRecommendationQuery) {
            const matchedList = getProductSuggestionsByQuery(message, dbProducts);
            
            // Immediately respond from Node.js in clean English (limited to 3 items)
            return res.json({ 
                response: `Here are 3 product recommendations matching your request:\n\n${matchedList}` 
            });
        }

        // STEP 4: If it's a general FAQ (e.g. "how to order", "where to pick up"), use the LLM
        const dynamicCatalog = dbProducts.map(p => `- **\({p.name}**: ₱\){p.price}`).join('\n');
        
        const systemInstruction = `CRITICAL ASSISTANT BOUNDARY:
You are strictly an e-commerce assistant for Siel Cart. You DO NOT answer math, coding, trivia, or off-topic queries.

LANGUAGE RULE:
Respond ONLY in English at all times.

RESPONSE STYLE & FORMATTING:
- Be concise and informative. Keep responses short so customers remain engaged.
- Use **bold text** for important highlights and key actions.
- Use bullet points (-) for steps or feature lists.
- If asked about Data Privacy or Terms & Conditions, always include direct links:
  • [Privacy Policy](/privacy-policy)
  • [Terms & Conditions](/terms-and-conditions)

AVAILABLE PRODUCT CATALOG IN OUR SHOP:
${dynamicCatalog}

${STORE_FACTS}

REFUSAL INSTRUCTIONS:
If the user query is unrelated to Siel Cart e-commerce, output EXACTLY this response in English:
"${STANDARD_REFUSAL}"`;

        const responseText = await generateContentWithFallback(message, systemInstruction);
        return res.json({ response: responseText });

    } catch (error) {
        console.error('All models failed or server error occurred:', error);

        // Friendly error message instead of raw server exception
        return res.json({ 
            response: FRIENDLY_ERROR_MESSAGE 
        });
    }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server listening on port ${PORT}`);
});