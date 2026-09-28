import dotenv from 'dotenv';
dotenv.config();

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import express from 'express';
import cors from 'cors';
import OpenAI from 'openai';
import mysql from 'mysql2/promise';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const app = express();
app.use(cors());
app.use(express.json());

// 1. Initialize OpenRouter Client
const openrouter = new OpenAI({
    baseURL: 'https://openrouter.ai/api/v1',
    apiKey: process.env.OPENROUTER_API_KEY || '',
    defaultHeaders: {
        'HTTP-Referer': 'http://localhost:3000',
        'X-Title': 'Siel Cart E-Commerce Assistant',
    }
});

// 2. Initialize Database Connection Pool
const dbHost = process.env.DB_HOST || 'localhost';
const isLocalDbHost = ['localhost', '127.0.0.1', '::1'].includes(dbHost);

// Aiven requires TLS, and Node's default trust store doesn't include its CA,
// so `rejectUnauthorized: true` with no `ca` fails the handshake on every
// connection attempt. fetchAvailableProducts() below catches that silently
// and falls back to a hardcoded product list -- which is why production was
// serving fake "CLSU Notebook / Siel Cart Lanyard / UBAP Mug" recommendations
// with no visible error. Mirrors config/database.php's CA lookup so the same
// committed cert works for both services; see chatbot-server/certs/README.md
// for why there are two copies.
function loadDbSslCa() {
    const configured = (process.env.MYSQL_ATTR_SSL_CA || '').trim();
    const candidates = configured
        ? [path.isAbsolute(configured) ? configured : path.resolve(__dirname, configured)]
        : [
            path.resolve(__dirname, 'certs/aiven-ca.pem'),
            path.resolve(__dirname, '../storage/certs/aiven-ca.pem'),
        ];

    for (const candidate of candidates) {
        try {
            return fs.readFileSync(candidate, 'utf8');
        } catch {
            // try next candidate
        }
    }

    return null;
}

const dbSslCa = isLocalDbHost ? null : loadDbSslCa();

if (!isLocalDbHost && !dbSslCa) {
    console.warn(
        'MySQL SSL CA not found (checked MYSQL_ATTR_SSL_CA and chatbot-server/certs/aiven-ca.pem). ' +
        'The connection to Aiven will fail TLS verification and product recommendations will silently fall back to stub data.'
    );
}

const dbPool = mysql.createPool({
    host: dbHost,
    port: process.env.DB_PORT || 3306,
    user: process.env.DB_USER || process.env.DB_USERNAME || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || process.env.DB_DATABASE || 'siel_cart',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
    ...(isLocalDbHost ? {} : { ssl: dbSslCa ? { ca: dbSslCa, rejectUnauthorized: true } : { rejectUnauthorized: true } })
});

// Updated stable free OpenRouter model list
const FALLBACK_MODELS = [
    'google/gemini-2.0-flash-lite-001',
    'meta-llama/llama-3.3-70b-instruct:free',
    'deepseek/deepseek-r1:free',
    'qwen/qwen-2.5-coder-32b-instruct:free'
];

const STANDARD_REFUSAL = "I can only assist with Siel Cart FAQs (How to Order, Returns/Refunds, Data Handling), Product Recommendations, and order status inquiries. How may I help you today?";

const FRIENDLY_ERROR_MESSAGE = "Our assistant is temporarily unavailable. Please browse our catalog on the store page orr contact the UBAP Office directly for immediate assistance.";

/**
 * Static store facts context provided to LLM
 */
const STORE_FACTS = `STORE FACTS (Siel Cart - UBAP Office at CLSU):
Siel Cart is pickup-only and cash-only at the UBAP Office. No delivery, no couriers, no cards/GCash/online payments.

HOW TO ORDER:
1. **Browse** catalog and select item...
2. **Choose** size/variant and add to cart.
3. **Open** cart items.
4. **Proceed** to checkout to confirm.
5. **Receive** claim number via email, then collect and pay in cash at UBAP Office.

PICKUP & CANCELLATION:
- Claim Numbers are issued ONLY when status is **Ready for Pickup**.
- Unclaimed Orders are cancelled. To reschedule pickup, contact **UBAP Office**.
- Cancel orders on **My Orders** page ONLY while status is **Pending**.

RETURNS & PRIVACY:
- Returns/refunds cannot be requested on website. Contact **UBAP Office** directly for defective items.
- Privacy Policy: [Privacy Policy](/privacy-policy)
- Terms & Conditions: [Terms & Conditions](/terms-and-conditions)`;

function isIrrelevantQuery(text) {
    const query = text.trim().toLowerCase();

    const mathPattern = /^(\d+[\s\+\-\*\/\^%\=]+\d+|\b(what is|calculate|compute|solve)\b.*?\d+)/i;
    if (mathPattern.test(query)) return true;
    if (/^\d+\s*[\+\-\*\/]\s*\d+/.test(query)) return true;
    if (/\b(write code|python|javascript|function|html|css|sql|script)\b/i.test(query)) return true;
    if (/^(who is|what is the capital|tell me a story|write a poem|sing|meaning of life)/i.test(query)) return true;

    return false;
}

async function fetchAvailableProducts() {
    try {
        // Variant-based products (has_variants = 1) keep their real price and
        // stock on product_variants, not on the products row itself -- p.price
        // is NULL and p.stock_quantity is unused for those. Pull the lowest
        // active-variant price and total active-variant stock for them, and
        // fall back to the product's own columns otherwise.
        const [rows] = await dbPool.query(
            `SELECT
                p.name,
                CASE WHEN p.has_variants = 1 THEN MIN(pv.price) ELSE MAX(p.price) END AS price,
                CASE WHEN p.has_variants = 1 THEN COALESCE(SUM(pv.stock_quantity), 0) ELSE MAX(p.stock_quantity) END AS stock_quantity
             FROM products p
             LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
             WHERE p.is_active = 1
             GROUP BY p.id, p.name, p.has_variants
             HAVING stock_quantity > 0`
        );
        return rows;
    } catch (dbError) {
        // Logged with the driver's error code (e.g. ECONNREFUSED,
        // ER_ACCESS_DENIED_ERROR, HANDSHAKE_SSL_ERROR) because the message
        // alone doesn't distinguish "wrong host/credentials" from "TLS
        // verification failed" -- both silently fall back to the same stub
        // product list below, so this log is the only way to tell which.
        console.error('Database fetch error:', dbError.code || '(no code)', '-', dbError.message);
        return [];
    }
}

function getProductSuggestionsByQuery(userQuery, products) {
    const text = userQuery.toLowerCase();
    
    const priceMatch = text.match(/(\d+)\s*(pesos|php|₱)?/i);
    const targetPrice = priceMatch ? parseFloat(priceMatch[1]) : null;

    const CATEGORIES = {
        apparel: ['shirt', 'tshirt', 't-shirt', 'hoodie', 'jacket', 'cap', 'hat', 'clothes', 'wear', 'apparel'],
        stationery: ['pen', 'ballpen', 'notebook', 'paper', 'pencil', 'pad', 'stationery', 'supplies', 'school'],
        accessories: ['lanyard', 'holder', 'id holder', 'keychain', 'badge', 'accessory', 'accessories'],
        bags: ['bag', 'tote', 'totebag', 'backpack', 'pouch'],
        drinkware: ['mug', 'tumbler', 'cup', 'bottle', 'flask', 'water bottle']
    };

    let filtered = products;

    let matchedCategoryItems = [];
    for (const [category, keywords] of Object.entries(CATEGORIES)) {
        if (keywords.some(kw => text.includes(kw))) {
            const matches = products.filter(p => 
                keywords.some(kw => p.name.toLowerCase().includes(kw))
            );
            matchedCategoryItems.push(...matches);
        }
    }

    if (matchedCategoryItems.length > 0) {
        filtered = Array.from(new Set(matchedCategoryItems));
    }

    if (targetPrice) {
        if (text.includes('under') || text.includes('below') || text.includes('less than')) {
            const underItems = filtered.filter(p => p.price <= targetPrice);
            filtered = underItems.length > 0 ? underItems : filtered.filter(p => p.price <= targetPrice * 1.25);
        } else {
            const matching = filtered.filter(p => p.price <= targetPrice * 1.2);
            filtered = matching.length > 0 ? matching : filtered;
        }
    }

    const topThree = filtered.slice(0, 3);

    return topThree.map(function(item) {
        return "- **" + item.name + "**: ₱" + item.price;
    }).join("\n");
}

// Helper to wrap API calls with a fast 6-second timeout
async function createCompletionWithTimeout(modelName, systemInstruction, message, timeoutMs = 6000) {
    return Promise.race([
        openrouter.chat.completions.create({
            model: modelName,
            temperature: 0.2,
            messages: [
                { role: 'system', content: systemInstruction },
                { role: 'user', content: message }
            ],
        }),
        new Promise((_, reject) => 
            setTimeout(() => reject(new Error(`Timeout after ${timeoutMs}ms`)), timeoutMs)
        )
    ]);
}

async function generateContentWithFallback(message, systemInstruction) {
    let lastError = null;

    for (const modelName of FALLBACK_MODELS) {
        try {
            console.log(`Attempting completion with model: ${modelName}`);
            const completion = await createCompletionWithTimeout(modelName, systemInstruction, message, 6000);

            let text = completion.choices[0]?.message?.content;
            
            if (text) {
                text = text.replace(/^(user\s*safety:\s*safe|user:safe)\s*/i, '').trim();
                if (text.length > 0) {
                    return text;
                }
            }
            throw new Error(`Model [${modelName}] returned an empty text payload.`);
        } catch (error) {
            console.warn(`Model [\({modelName}] failed/timed out:\){error.message}. Trying next model...`);
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

        const msgLower = message.toLowerCase().trim();

        // --- FAST-PASS INTERCEPTORS FOR CORE QUERY BUTTONS ---
        
        // 1. Greetings
        const GREETINGS = ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'kumusta', 'yo', 'halu'];
        if (GREETINGS.some(g => msgLower === g || msgLower === g + '!' || msgLower === g + '.')) {
            return res.json({
                response: "Hello! Welcome to Siel Cart. How can I assist you with your shopping today???"
            });
        }

        // 2. Payment Method
        if (msgLower.includes('payment') || msgLower.includes('pay') || msgLower.includes('gcash') || msgLower.includes('card')) {
            return res.json({
                response: "Payment at Siel Cart is **Cash on Pickup only**, paid in person at the UBAP Office when collecting your items. We do not accept online payments or credit/debit cards."
            });
        }

        // 3. How to Order
        if (msgLower.includes('how to order') || msgLower.includes('how do i order') || msgLower.includes('place an order') || msgLower.includes('how do i place an order')) {
            return res.json({
                response: `To place an order:

1. **Browse** our catalog and select an item.
2. **Choose** your preferred size or variant, then add it to your cart.
3. **Open** your cart and review your items.
4. **Proceed** to checkout to confirm your order details.
5. **Receive** your claim number via email, then collect and pay in cash at the UBAP Office.`
            });
        }

        // 4. Order Status
        if (msgLower.includes('order status') || msgLower.includes('check my order') || msgLower.includes('track status')) {
            return res.json({
                response: `To check your order status:

1. Log in to your **Siel Cart** account.
2. Go to **My Orders** and select your order.
3. Statuses shown are: **Pending**, **Processing**, **Ready for Pickup**, or **Completed**.`
            });
        }

        // 5. Off-Topic Check
        if (isIrrelevantQuery(message)) {
            return res.json({ response: STANDARD_REFUSAL });
        }

        // Fetch DB products
        let dbProducts = [];
        try {
            dbProducts = await fetchAvailableProducts();
        } catch (dbErr) {
            console.error('Failed to fetch from DB:', dbErr);
        }

        if (!dbProducts || dbProducts.length === 0) {
            dbProducts = [
                { name: "CLSU Notebook", price: 50 },
                { name: "Siel Cart Lanyard", price: 80 },
                { name: "UBAP Mug", price: 200 },
                { name: "Siel Cart Tote Bag", price: 200 },
                { name: "CLSU Basic Shirt", price: 250 },
                { name: "UBAP Hoodie", price: 750 }
            ];
        }

        const isFAQIntent = 
            msgLower.includes('how') || 
            msgLower.includes('where') || 
            msgLower.includes('when') || 
            msgLower.includes('can i') || 
            msgLower.includes('policy') || 
            msgLower.includes('status') || 
            msgLower.includes('cancel') || 
            msgLower.includes('return') || 
            msgLower.includes('refund') || 
            msgLower.includes('privacy');

        const isRecommendationQuery = 
            !isFAQIntent && (
                msgLower.includes('suggest') || 
                msgLower.includes('recommend') || 
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
                msgLower.includes('pen')
            );

        if (isRecommendationQuery) {
            const matchedList = getProductSuggestionsByQuery(message, dbProducts);

            if (!matchedList) {
                return res.json({
                    response: "Sorry, we don't have any matching products in stock right now. Please try a different price range or category."
                });
            }

            return res.json({
                response: "Here are 3 Product Recommendations matching your request:\n\n" + matchedList
            });
        }

        // Dynamic catalog context for LLM
        const dynamicCatalog = dbProducts.slice(0, 5).map(function(item) {
            return "- " + item.name + ": ₱" + item.price;
        }).join("\n");
        
        const systemInstruction = `CRITICAL ASSISTANT BOUNDARY:
You are strictly an e-commerce assistant for Siel Cart. You DO NOT answer math, coding, trivia, or off-topic queries.

LANGUAGE RULE:
Respond ONLY in English at all times.

STRICT LENGTH & FORMATTING RULES:
- Output ONLY short answers (3 bullet points max).
- Format responses as Markdown: use **bold** for key details, - for bullet lists, and [label](url) for links.
- Separate paragraphs and lists with a blank line. Do not output HTML or special Unicode bold letters.
- DO NOT add extra commentary or closing questions like "Is there anything else I can help you with?".

AVAILABLE PRODUCT CATALOG IN OUR SHOP:
${dynamicCatalog}

${STORE_FACTS}

REFUSAL INSTRUCTIONS:
If the user query is unrelated to Siel Cart e-commerce, output EXACTLY this response in English:
"${STANDARD_REFUSAL}"`;

        // Pass to OpenRouter LLM with fallback
        const responseText = await generateContentWithFallback(message, systemInstruction);
        return res.json({ response: responseText });

    } catch (error) {
        console.error('All models failed or server error occurred:', error);

        return res.json({ 
            response: FRIENDLY_ERROR_MESSAGE 
        });
    }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server listening on port ${PORT}`);
});
