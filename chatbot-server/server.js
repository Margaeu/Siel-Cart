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

// Free OpenRouter models, tried in order until one answers. OpenRouter retires
// and re-slugs free models without notice: the previous list (gemini-2.0-flash-lite,
// llama-3.3-70b:free, deepseek-r1:free, qwen-2.5-coder:free) all returned 404, so
// every AI-answered question fell through to FRIENDLY_ERROR_MESSAGE. Checked
// against GET https://openrouter.ai/api/v1/models on 2026-09-28. If the chat goes
// down again, re-check that list before suspecting anything else. Plain instruct
// models only: reasoning models can return empty content inside the 6s timeout.
const FALLBACK_MODELS = [
    'google/gemma-4-31b-it:free',
    'qwen/qwen3.8-27b:free',
    'nvidia/nemotron-3-super-120b-a12b:free',
    'google/gemma-4-26b-a4b-it:free'
];

const STANDARD_REFUSAL = "I can only assist with Siel Cart FAQs (How to Order, Returns/Refunds, Data Handling), Product Recommendations, and order status inquiries. How may I help you today?";

const FRIENDLY_ERROR_MESSAGE = "Our assistant is temporarily unavailable. Please browse our catalog on the store page or contact the UBAP Office directly for immediate assistance.";

/**
 * Static store facts context provided to LLM
 */
// Kept in sync with CheckoutPage, Order, CancelOrderModal, and the FAQ_ENTRIES
// below: the model may only describe the store in these terms.
const STORE_FACTS = `STORE FACTS (Siel Cart - UBAP Office at CLSU; UBAP = University Business Affairs Program):
Siel Cart is pickup-only and cash-only at the UBAP Office. No delivery, no couriers, no cards/GCash/online payments.

ACCOUNTS:
- A free account with a verified email is required to place an order. You must be at least 13 years old to register.

HOW TO ORDER:
1. **Browse** catalog and select item...
2. **Choose** size/variant and add to cart.
3. **Open** cart items.
4. **Proceed** to checkout to confirm.
5. **Receive** an email when the order is ready, with the claim number, then collect and pay in cash at UBAP Office.

PROCESSING & PICKUP:
- Allow at least 2-3 days for an order to be processed. Do not promise an exact ready date.
- The customer is emailed when the order is **Ready for Pickup**; that email has the claim number, pickup date and time.
- Claim Numbers are issued ONLY when status is **Ready for Pickup**. Show it at the UBAP Office.
- A customer may authorize another person to collect the order. That person must give the correct claim number, which UBAP verifies before release. Customers must share the claim number only with their authorized representative; UBAP is not responsible for losses, disputes, or unauthorized claims from the customer's voluntary disclosure of the claim number, provided UBAP followed its verification procedures.
- Orders not claimed by the pickup deadline are NOT cancelled automatically: UBAP staff cancel them and the items return to stock. To arrange a new pickup, email ubap@clsu.edu.ph.
- There is no reschedule feature on the website.

CANCELLATION:
- Customers cancel from the **My Orders** page ONLY while status is **Pending**. Once **Processing**, it cannot be cancelled online.

REVIEWS:
- Only after an order is **Completed**, from the product page's Reviews tab.

RETURNS & PRIVACY:
- Returns/refunds cannot be requested on website. Contact **UBAP Office** directly (ubap@clsu.edu.ph) for defective items.
- There is no in-app inquiry form; other questions go to ubap@clsu.edu.ph or the UBAP Office.
- Privacy Policy: [Privacy Policy](/privacy-policy)
- Terms & Conditions: [Terms & Conditions](/terms-and-conditions)`;

function isIrrelevantQuery(text) {
    const query = text.trim().toLowerCase();

    // "what is" only counts as maths when a number/bracket follows it directly; the old
    // \b(what is)\b.*?\d+ form also refused "what is available under 300".
    const mathPattern = /^(\d+[\s\+\-\*\/\^%\=]+\d+|what is\s*[\d(]|\b(calculate|compute|solve)\b.*?\d+)/i;
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
                p.slug,
                p.has_variants,
                CASE WHEN p.has_variants = 1 THEN MIN(pv.price) ELSE MAX(p.price) END AS price,
                CASE WHEN p.has_variants = 1 THEN COALESCE(SUM(pv.stock_quantity), 0) ELSE MAX(p.stock_quantity) END AS stock_quantity
             FROM products p
             LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
             WHERE p.is_active = 1
             GROUP BY p.id, p.name, p.slug, p.has_variants
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

const escapeRegex = (str) => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

// Whole-word match, plural allowed. The old `text.includes(kw)` matched "pen" in
// "happens" and "cap" in "capital", so an FAQ about a missed pickup came back
// as a product list.
const wordMatcher = (kw) => new RegExp(`\\b${escapeRegex(kw)}(?:s|es)?\\b`, 'i');

const CATEGORIES = {
    apparel: ['shirt', 'tshirt', 't-shirt', 'hoodie', 'jacket', 'cap', 'hat', 'clothes', 'wear', 'apparel'],
    stationery: ['pen', 'ballpen', 'notebook', 'paper', 'pencil', 'pad', 'stationery', 'supplies', 'school'],
    accessories: ['lanyard', 'holder', 'id holder', 'keychain', 'badge', 'accessory', 'accessories'],
    bags: ['bag', 'tote', 'totebag', 'backpack', 'pouch'],
    drinkware: ['mug', 'tumbler', 'cup', 'bottle', 'flask', 'water bottle']
};

// Returns { amount, isCap } for "under 300", "below ₱500", "300 pesos", or
// { amount: null } when no price was given. A bare number is NOT a price.
function parseBudget(text) {
    const t = text.replace(/(\d),(\d{3})/g, '$1$2');
    const m = t.match(/(under|below|less than|within|up to|at most|not more than|max(?:imum)?(?: of)?|budget(?: of)?|around|about|₱|php)\s*(\d+(?:\.\d+)?)/i)
        || t.match(/(\d+(?:\.\d+)?)\s*(pesos?|php|₱)/i);
    if (!m) return { amount: null, isCap: false };

    const amount = parseFloat(isNaN(parseFloat(m[1])) ? m[2] : m[1]);
    const isCap = /(under|below|less than|within|up to|at most|not more than|max)/i.test(m[0]);
    return { amount, isCap };
}

// Up to three in-stock products for the request. Never returns items above a
// stated cap: the old code widened "under 300" to 375 when nothing fit, so a
// customer asking for a limit was shown products over it.
function findProductSuggestions(userQuery, products) {
    const text = userQuery.toLowerCase();
    const { amount, isCap } = parseBudget(text);

    let pool = products.filter(p => p.price !== null && p.price !== undefined && !isNaN(Number(p.price)));

    let categoryAsked = false;
    const inCategory = [];
    for (const keywords of Object.values(CATEGORIES)) {
        const asked = keywords.filter(kw => wordMatcher(kw).test(text));
        if (asked.length > 0) {
            categoryAsked = true;
            // "hoodie" should surface hoodies before shirts; the wider category
            // is only the fallback when nothing carries the exact word.
            const exact = pool.filter(p => asked.some(kw => wordMatcher(kw).test(p.name)));
            inCategory.push(...(exact.length > 0
                ? exact
                : pool.filter(p => keywords.some(kw => wordMatcher(kw).test(p.name)))));
        }
    }
    if (categoryAsked) pool = Array.from(new Set(inCategory));

    if (amount !== null) {
        pool = isCap
            ? pool.filter(p => Number(p.price) <= amount)
            : pool.filter(p => Number(p.price) <= amount * 1.2);
    }

    // Closest to the budget first (best value for "under 300"); otherwise cheapest first.
    const wantsCheapest = /\b(cheap\w*|affordable|lowest|budget)\b/i.test(text);
    pool.sort((a, b) => (amount !== null && !wantsCheapest)
        ? Math.abs(amount - Number(a.price)) - Math.abs(amount - Number(b.price))
        : Number(a.price) - Number(b.price));

    return pool.slice(0, 3);
}

// Markdown, because the chat widget renders it (sanitised) and the link lets the
// customer open the product page straight from the recommendation.
function formatProductList(items) {
    return items.map(function(item) {
        const name = String(item.name).replace(/[\[\]]/g, '');
        const price = Number(item.price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const label = `${name} – ${item.has_variants ? 'from ' : ''}₱${price}`;
        return item.slug ? `- [${label}](/product/${encodeURIComponent(item.slug)})` : `- ${label}`;
    }).join('\n');
}

// --- FAQ ANSWERS ------------------------------------------------------------
// Fixed replies for the store's published FAQ. They are answered here, before
// any model call, so they are instant, identical every time, and still work
// when OpenRouter's free models are rate-limited or gone. Keep the wording in
// step with STORE_FACTS above and with what the store really does (Order,
// CancelOrderModal, CheckoutPage, the Terms & Conditions).
const contactUbap = 'email **ubap@clsu.edu.ph** or visit the UBAP Office';

// A matcher is a regex or a function of the text, so all()/any() can nest.
const runMatcher = (m, t) => (typeof m === 'function' ? m(t) : m.test(t));
const all = (...matchers) => (t) => matchers.every(m => runMatcher(m, t));
const any = (...matchers) => (t) => matchers.some(m => runMatcher(m, t));

// Order matters: the first entry that matches wins, so narrower questions
// (missed pickup, cancel) sit above broader ones (ready notice, payment).
const FAQ_ENTRIES = [
    {
        id: 'returns',
        match: /\b(returns?|refunds?|exchange[sd]?|defective|damaged|faulty|wrong item)\b/,
        answer: `Returns, refunds, and exchanges can't be requested on the website. For a defective, damaged, or wrong item, ${contactUbap} directly.`
    },
    {
        id: 'delivery',
        match: /\b(deliver\w*|shipping|ship|courier|shipping fee)\b/,
        answer: 'Siel Cart is **pickup-only**: there is no delivery or shipping. Collect your order at the UBAP Office and pay in cash there.'
    },
    {
        id: 'missed-pickup',
        match: any(/\b(miss(ed)?|forgot|forget|unclaimed|deadline)\b/, /\bhold(ing)? (period|my order)\b/, /\bfail(ed)? to (claim|pick)/, /\bnot (picked up|claimed)\b/),
        answer: `Once your order is **Ready for Pickup**, claim it at the UBAP Office within the pickup date and time given in your email. If it isn't claimed in time, UBAP will cancel the order and the reserved items go back into stock for other customers. To arrange a new pickup, ${contactUbap}.`
    },
    {
        id: 'cancel',
        match: /\bcancel(l?ed|l?ing|lation)?\b/,
        answer: 'Yes, but only while your order is still **Pending**. Open your order under **My Orders** and use the cancel option. Once it is marked **Processing**, it can no longer be cancelled online.'
    },
    {
        id: 'someone-else',
        match: all(/\b(someone|somebody|another person|other person|representative|friend|relative|behalf)\b/, /\b(pick|claim|collect)/),
        answer: "Yes. You may authorize another person to collect your order on your behalf. They must provide the correct **claim number** for the order, which UBAP verifies before releasing it. Share your claim number only with your authorized representative: UBAP is not responsible for losses, disputes, or unauthorized claims that result from you giving out the claim number, as long as UBAP followed its verification procedures."
    },
    {
        id: 'bring',
        match: any(/\bclaim (number|code)\b/, all(/\b(bring|show|present|need|requirements?|required)\b/, /\b(pick|claim|collect)/)),
        answer: 'Yes, show your **claim number** at the UBAP Office. You can find it in your Ready for Pickup email and on your order page under **My Orders**.'
    },
    {
        id: 'how-long',
        match: /\b(how long|how many (days|weeks)|how soon|processing time|turnaround|when will my order (be )?(ready|done|processed))\b/,
        answer: 'Please allow **at least 2–3 days** for your order to be processed. You will get an email once it is ready for pickup, and you can follow its status under **My Orders**.'
    },
    {
        id: 'ready-notice',
        match: all(/\b(how (will|do|can|would) i know|notif\w*|notify|email|alert|inform)\b/, /\b(ready|pick[- ]?up|claim|processing|status)\b/),
        answer: 'You will receive an **email** when your order status changes to **Ready for Pickup**. It includes your claim number and your pickup date and time. You can also check the status any time under **My Orders**.'
    },
    {
        id: 'where-pickup',
        match: any(/\bwhere\b.*\b(pick|claim|collect)/, /\bpick[- ]?up (location|place|address|area|schedule|time|date|hours)\b/, /\bwhere (is|are) (the )?(ubap|office)\b/, /\bwhen (can|do|should) i (pick|claim|collect)/),
        answer: 'Orders are picked up at the **UBAP Office** (University Business Affairs Program). Once your order is **Ready for Pickup**, you will get an email with your claim number, pickup date, and pickup time. The same details appear under **My Orders**.'
    },
    {
        id: 'same-last-item',
        match: /\b(same time|last (item|one|piece|stock)|two people|both order|simultaneous(ly)?)\b/,
        answer: 'The first order to complete checkout gets the item. If someone else takes the last one first, you will see a message that only a limited quantity is left (or that it is unavailable), and you can adjust your cart. Nothing is charged online, since payment is only collected in cash at pickup, so no refund is needed.'
    },
    {
        id: 'stock',
        match: /\b(in stock|out of stock|sold out|availability|stock status)\b/,
        answer: "Availability is shown on each product's page. Items that are out of stock are clearly marked and can't be checked out, and your cart flags any item that is no longer available."
    },
    {
        id: 'age',
        match: /\b(age (requirement|limit|restriction)|minimum age|how old|old enough|13 years)\b/,
        answer: 'Yes. You must be at least **13 years old** to create an account, in line with our [Privacy Policy](/privacy-policy).'
    },
    {
        id: 'account',
        match: any(/\b(need|require[sd]?|must|have to)\b.*\b(account|log ?in|sign ?up|register|registration)\b/, /\b(create|make|open)\b.*\baccount\b/),
        answer: 'Yes. You need a free Siel Cart account (with a verified email address) to place an order. An account lets you track your order status, view your order history, and leave reviews after pickup.'
    },
    {
        id: 'review',
        match: all(/\b(leave|write|post|give|add|submit|can i|how (do|can) i)\b/, /\b(review|reviews|rating|rate|feedback)\b/),
        answer: "Yes. Once your order is **Completed**, open the product's page and use its **Reviews** tab to leave a rating and review. You can also reach it from your order under **My Orders**."
    },
    {
        id: 'privacy',
        match: /\b(privacy|personal (information|data|info)|data (privacy|protection|handling)|my data|my information)\b/,
        answer: 'We only collect the information needed to process your orders and manage your account. For full details, see our [Privacy Policy](/privacy-policy).'
    },
    {
        id: 'contact',
        match: /\b(contact|inquiry|inquire|enquir\w*|customer (service|support)|support|human|hotline|phone number|email address|reach (you|ubap|someone)|not answered|other question)\b/,
        answer: `If your question isn't covered here, ${contactUbap}. You can also keep asking me about ordering, pickup, payment, and products.`
    },
    {
        id: 'payment',
        match: /\b(payment|paying|pay|gcash|cash|(credit|debit) cards?)\b/,
        answer: 'Payment at Siel Cart is **Cash on Pickup only**, paid in person at the UBAP Office when collecting your items. We do not accept online payments or credit/debit cards.'
    },
    {
        id: 'order-status',
        match: /\b(order status|check my order|track(ing)? (my )?(order|status)|track status|where is my order)\b/,
        answer: `To check your order status:

1. Log in to your **Siel Cart** account.
2. Go to **My Orders** and select your order.
3. Statuses shown are: **Pending**, **Processing**, **Ready for Pickup**, or **Completed** (or **Cancelled**).`
    },
    {
        id: 'how-to-order',
        match: /\b(how (to|do i|can i|would i) (place |make )?(an? )?order|place an order|ordering process|how does ordering work|how (to|do i) buy)\b/,
        answer: `To place an order:

1. **Browse** our catalog and select an item.
2. **Choose** your preferred size or variant, then add it to your cart.
3. **Open** your cart and review your items.
4. **Proceed** to checkout (you'll need to log in) and check your order details.
5. **Submit** your order, then wait for the email that says it is ready.
6. **Collect** it and pay in cash at the UBAP Office, showing your claim number.`
    },
    {
        id: 'about',
        match: /\b(what is siel ?cart|about siel ?cart|what is this (store|shop|website|site))\b/,
        answer: '**Siel Cart** is the official online store for CLSU merchandise, run by the UBAP Office (University Business Affairs Program). It is pickup-only and cash-only: order online, then collect and pay in cash at the UBAP Office.'
    }
];

const GREETINGS = ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'kumusta', 'yo', 'halu'];

function findFaqAnswer(message) {
    const text = message.toLowerCase().replace(/[’‘]/g, "'").replace(/\s+/g, ' ').trim();

    if (GREETINGS.some(g => text === g || text === g + '!' || text === g + '.')) {
        return "Hello! Welcome to **Siel Cart**. How can I assist you with your shopping today?";
    }

    for (const entry of FAQ_ENTRIES) {
        if (runMatcher(entry.match, text)) return entry.answer;
    }
    return null;
}

const RECOMMENDATION_PATTERN = /\b(suggest\w*|recommend\w*|price[sd]?|pesos?|php|under|below|less than|budget|cheap\w*|affordable|shirts?|t-?shirts?|hoodies?|jackets?|apparel|clothes|mugs?|tumblers?|bottles?|bags?|totes?|notebooks?|pens?|lanyards?|keychains?|merch\w*|products?|items?|catalog|what do you (sell|have)|what can i buy)\b|₱\s*\d/i;
const BEST_SELLER_PATTERN = /\b(best[- ]?sell(ers?|ing)|top[- ]?sell(ers?|ing)|most popular|popular)\b/i;

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
            console.warn(`Model [${modelName}] failed/timed out: ${error.message}. Trying next model...`);
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

        // 1. Published FAQ, greetings, payment, ordering, order status, ...
        const faqAnswer = findFaqAnswer(message);
        if (faqAnswer) {
            return res.json({ response: faqAnswer });
        }

        // 2. Off-Topic Check
        if (isIrrelevantQuery(message)) {
            return res.json({ response: STANDARD_REFUSAL });
        }

        // 3. Fetch DB products. If the database can't be reached we say so instead
        // of inventing a stub catalogue: the old hardcoded "CLSU Notebook / UBAP
        // Mug" list was served to real customers as if it were in stock.
        let dbProducts = [];
        try {
            dbProducts = await fetchAvailableProducts();
        } catch (dbErr) {
            console.error('Failed to fetch from DB:', dbErr);
        }

        const noProductsReply = "I couldn't find any products in stock to recommend right now. Please check our [catalog](/products) for the latest items.";

        // 4. Product recommendations, answered from the database (no model call).
        if (BEST_SELLER_PATTERN.test(msgLower)) {
            const picks = dbProducts && dbProducts.length ? findProductSuggestions(message, dbProducts) : [];
            if (picks.length === 0) return res.json({ response: noProductsReply });

            return res.json({
                response: "I don't have sales rankings, but here are some items available right now:\n\n" + formatProductList(picks)
            });
        }

        if (RECOMMENDATION_PATTERN.test(msgLower)) {
            const picks = dbProducts && dbProducts.length ? findProductSuggestions(message, dbProducts) : [];

            if (picks.length === 0) {
                return res.json({
                    response: dbProducts && dbProducts.length
                        ? "Sorry, we don't have any matching products in stock right now. Please try a different price range or category, or browse our [catalog](/products)."
                        : noProductsReply
                });
            }

            const heading = picks.length === 1
                ? "Here is 1 product matching your request:"
                : `Here are ${picks.length} products matching your request:`;

            return res.json({ response: heading + "\n\n" + formatProductList(picks) });
        }

        // 5. Dynamic catalog context for the LLM
        const dynamicCatalog = dbProducts && dbProducts.length
            ? dbProducts.slice(0, 5).map(function(item) {
                return "- " + item.name + ": ₱" + item.price;
            }).join("\n")
            : "(catalog unavailable right now - do not name specific products or prices)";

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
