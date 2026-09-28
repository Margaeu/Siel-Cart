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
- Forgotten password: use **Forgot password?** on the login page. A logged-in customer changes it under **My Account → Profile → Change Password**.
- **My Account → Profile** lets a customer update their first name, last name and phone number, and change their email (Change Email Address, needs the current password). Date of birth cannot be changed there.
- A customer can delete their own account from **My Account → Profile → Delete Account**. It is permanent and is blocked while any order is Pending, Processing or Ready for Pickup.
- Chatbot conversations are not recorded or stored.

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
- Orders not claimed within the pickup schedule are NOT cancelled automatically by the system: UBAP staff cancel them and the items return to stock. There is no rescheduled pickup; questions about a cancelled order go to ubap@clsu.edu.ph.
- There is no reschedule feature on the website.

CANCELLATION AND CHANGES:
- Customers cancel from the **My Orders** page ONLY while status is **Pending**. Once **Processing**, it cannot be cancelled online.
- Items and quantities cannot be edited after an order is placed. A Pending order can be cancelled and placed again.
- Nothing is charged online, so a cancellation involves no refund.

REVIEWS:
- Only after an order is **Completed**, from the product page's Reviews tab.

RETURNS & PRIVACY:
- Returns/refunds cannot be requested on website. Contact **UBAP Office** directly (ubap@clsu.edu.ph) for defective items.
- There is no in-app inquiry form; other questions go to ubap@clsu.edu.ph or the UBAP Office.
- The UBAP Office is part of Central Luzon State University, Science City of Muñoz, Nueva Ecija 3119. Its opening hours are not published here: do not state any hours; point to ubap@clsu.edu.ph and the pickup time in the Ready for Pickup email.
- Data privacy concerns go to the CLSU Data Protection Officer, dpo@clsu.edu.ph.
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

// Variant-based products (has_variants = 1) keep their real price and stock on
// product_variants, not on the products row itself -- p.price is NULL and
// p.stock_quantity is unused for those. Pull the lowest active-variant price and
// total active-variant stock for them, and fall back to the product's own
// columns otherwise.
const AVAILABLE_PRODUCTS_SQL = (extraColumns) => `SELECT
        p.name,
        p.slug,
        p.has_variants,
        c.id AS category_id,
        c.name AS category_name,
        CASE WHEN p.has_variants = 1 THEN MIN(pv.price) ELSE MAX(p.price) END AS price,
        CASE WHEN p.has_variants = 1 THEN COALESCE(SUM(pv.stock_quantity), 0) ELSE MAX(p.stock_quantity) END AS stock_quantity
        ${extraColumns}
     FROM products p
     JOIN categories c ON c.id = p.category_id AND c.is_active = 1
     LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
     WHERE p.is_active = 1 AND p.deleted_at IS NULL
     GROUP BY p.id, p.name, p.slug, p.has_variants, c.id, c.name
     HAVING stock_quantity > 0`;

// The categories the storefront lets customers browse to. Read from the database
// on every request, like the products, so a category added, renamed or switched
// off in the admin is reflected here without touching this file. Empty on a
// failure: item words ("jacket") still work, only category names stop matching.
async function fetchActiveCategories() {
    try {
        const [rows] = await dbPool.query('SELECT id, name FROM categories WHERE is_active = 1');
        return rows;
    } catch (dbError) {
        console.error('Category fetch error:', dbError.code || '(no code)', '-', dbError.message);
        return [];
    }
}

// Every product the storefront lists, in stock or not: the names a shopper may
// ask for. Same visibility rules as the stock query above (active, not deleted,
// in an active category) but without the stock filter.
async function fetchCatalogue() {
    try {
        const [rows] = await dbPool.query(
            `SELECT p.id, p.name, p.slug, p.category_id
               FROM products p
               JOIN categories c ON c.id = p.category_id AND c.is_active = 1
              WHERE p.is_active = 1 AND p.deleted_at IS NULL`
        );
        return rows;
    } catch (dbError) {
        console.error('Catalogue fetch error:', dbError.code || '(no code)', '-', dbError.message);
        return [];
    }
}

// Sales and rating signals for "popular" answers and the rating shown beside a
// pick. Only completed, non-deleted orders count as a sale (a cancelled order's
// stock goes back on the shelf), and only approved reviews count toward the
// rating, matching what the storefront itself shows. Correlated subqueries
// rather than joins: joining order_items and reviews onto the variant join
// above would multiply the rows and inflate the stock sum.
const POPULARITY_COLUMNS = `,
        (SELECT COALESCE(SUM(oi.quantity), 0)
           FROM order_items oi
           JOIN orders o ON o.id = oi.order_id
          WHERE oi.product_id = p.id AND o.status = 'completed' AND o.deleted_at IS NULL) AS units_sold,
        (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1) AS avg_rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1) AS review_count`;

async function fetchAvailableProducts() {
    try {
        try {
            const [rows] = await dbPool.query(AVAILABLE_PRODUCTS_SQL(POPULARITY_COLUMNS));
            return rows;
        } catch (richError) {
            // A schema without the sales/review tables must not take the whole
            // catalogue down with it: recommend from the basic columns instead.
            console.warn('Popularity query failed, using basic catalogue:', richError.code || '(no code)', '-', richError.message);
            const [rows] = await dbPool.query(AVAILABLE_PRODUCTS_SQL(''));
            return rows;
        }
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

// What the shop's categories ARE comes from the database (fetchActiveCategories),
// never from this file: the old hardcoded apparel/stationery/... list knew nothing
// of the categories actually in the admin and could not follow a rename.
//
// Two small vocabularies remain, and both only translate customer wording:
//
// ITEM_GROUPS: words a shopper uses for a kind of item, grouped by what counts as
// the same kind. Product names rarely say the generic word -- "CLSU Windbreaker"
// is a jacket without containing "jacket" -- so a request for jackets has to
// know the windbreaker qualifies. Groups are kept tight on purpose: a shirt is
// not a jacket, so a jackets request must not fall back to shirts.
const ITEM_GROUPS = [
    ['shirt', 'tshirt', 't-shirt', 'tee', 'polo'],
    ['hoodie', 'sweatshirt', 'pullover'],
    ['jacket', 'windbreaker', 'coat', 'outerwear'],
    ['cap', 'hat'],
    ['pen', 'ballpen'],
    ['notebook'],
    ['pencil'],
    ['lanyard'],
    ['id holder'],
    ['keychain'],
    ['badge'],
    ['bag', 'tote', 'totebag', 'backpack', 'pouch'],
    ['mug', 'tumbler', 'cup', 'bottle', 'flask', 'water bottle']
];

// CATEGORY_ALIASES: everyday words for a whole category, tied to it by a pattern
// on the category's real name, so "clothes" finds Apparel whatever the admin
// calls it, and finds nothing if no such category exists.
const CATEGORY_ALIASES = [
    { words: ['clothes', 'clothing', 'wear', 'apparel'], name: /apparel|cloth|wear/i },
    { words: ['stationery', 'stationary', 'supplies', 'school'], name: /station|suppl|school/i },
    { words: ['accessory', 'accessories'], name: /accessor/i },
    { words: ['drinkware'], name: /drink/i }
];

// A category is matched by its whole name or by any word of it long enough to be
// meaningful ("Gift Set" -> "gift", not "set"), singular or plural.
const categoryWords = (name) => {
    const words = [name, ...name.split(/[^a-z0-9]+/i).filter(w => w.length >= 4)];
    return [...new Set(words.flatMap(w => (/s$/i.test(w) && w.length > 4 ? [w, w.slice(0, -1)] : [w])))];
};

function categoriesAsked(text, categories) {
    return categories.filter(c =>
        categoryWords(c.name).some(w => wordMatcher(w).test(text))
        || CATEGORY_ALIASES.some(a => a.name.test(c.name) && a.words.some(w => wordMatcher(w).test(text))));
}

// Words from the shop's own product names become one-word item groups, so a
// product the admin adds ("Umbrella") can be asked for by name with no edit to
// ITEM_GROUPS. That list then only has to supply synonyms.
//
// Read from the whole active catalogue, sold out or not, so asking for an item
// that has run out is recognised as a product request and answered "out of
// stock" rather than handed to the language model.
//
// Grammar and filler words are skipped, and so is any word that runs through
// much of the catalogue ("CLSU", a series name): as a search word it would match
// everything and mean nothing.
const NAME_STOPWORDS = new Set([
    'the', 'and', 'for', 'with', 'new', 'set', 'pack', 'pcs', 'size', 'small', 'medium', 'large', 'mini', 'big',
    'official', 'edition', 'collection', 'design', 'series', 'version', 'special', 'limited', 'sale',
    'product', 'products', 'item', 'items'
]);
const STATIC_ITEM_WORDS = new Set(ITEM_GROUPS.flat());
const singular = (w) => (/s$/.test(w) && !/ss$/.test(w) && w.length > 3 ? w.slice(0, -1) : w);

function nameVocabulary(catalogue) {
    const counts = new Map();
    for (const p of catalogue) {
        const words = new Set((String(p.name).toLowerCase().match(/[a-z]{3,}/g) || [])
            .filter(w => !NAME_STOPWORDS.has(w))
            .map(singular));
        for (const w of words) counts.set(w, (counts.get(w) || 0) + 1);
    }
    const runsThroughCatalogue = (n) => catalogue.length >= 3 && n >= 2 && n / catalogue.length > 0.3;
    return [...counts]
        .filter(([w, n]) => !runsThroughCatalogue(n) && !STATIC_ITEM_WORDS.has(w))
        .map(([w]) => [w]);
}

// [{ words, asked }] for each group the text mentions: `asked` are the group's
// words the shopper actually used, `words` the whole group.
function itemGroupsAsked(text, extraGroups = []) {
    return [...ITEM_GROUPS, ...extraGroups]
        .map(words => ({ words, asked: words.filter(w => wordMatcher(w).test(text)) }))
        .filter(g => g.asked.length > 0);
}

// `shop` is what the database says the shop sells right now: { categories,
// catalogue, extraGroups }. See loadShop().
const EMPTY_SHOP = { categories: [], catalogue: [], extraGroups: [] };

// Everything the vocabulary above is built from, fetched fresh per request.
async function loadShop() {
    const [categories, catalogue] = await Promise.all([fetchActiveCategories(), fetchCatalogue()]);
    return { categories, catalogue, extraGroups: nameVocabulary(catalogue) };
}

const mentionsCatalogTerm = (text, shop) =>
    categoriesAsked(text, shop.categories).length > 0 || itemGroupsAsked(text, shop.extraGroups).length > 0;

// Returns { amount, isCap } for "under 300", "below ₱500", "under ₱500",
// "300 pesos", or { amount: null } when no price was given. A bare number is
// NOT a price. The optional currency sign after the keyword matters: the quick
// reply chips read "Under ₱250", and without it the keyword failed to match,
// the bare "₱250" did, and the cap was silently treated as an "around" budget.
function parseBudget(text) {
    const t = text.replace(/(\d),(\d{3})/g, '$1$2');
    const m = t.match(/(under|below|less than|within|up to|at most|not more than|max(?:imum)?(?: of)?|budget(?: of)?|around|about|₱|php)\s*(?:₱|php)?\s*(\d+(?:\.\d+)?)/i)
        || t.match(/(\d+(?:\.\d+)?)\s*(pesos?|php|₱)/i);
    if (!m) return { amount: null, isCap: false };

    const amount = parseFloat(isNaN(parseFloat(m[1])) ? m[2] : m[1]);
    const isCap = /(under|below|less than|within|up to|at most|not more than|max)/i.test(m[0]);
    return { amount, isCap };
}

const shuffle = (list) => {
    const a = [...list];
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
};
const pickOne = (list) => list[Math.floor(Math.random() * list.length)];

const categoryOf = (product) => product.category_name || 'other';

// Round-robin across categories, so a request with no criteria surfaces a mix
// (one bag, one mug, one notebook) instead of three items of the same kind.
function diversify(pool) {
    const groups = new Map();
    for (const p of shuffle(pool)) {
        const key = categoryOf(p);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(p);
    }
    const lists = shuffle([...groups.values()]);
    const out = [];
    for (let i = 0; out.length < pool.length; i++) {
        let added = false;
        for (const list of lists) {
            if (i < list.length) { out.push(list[i]); added = true; }
        }
        if (!added) break;
    }
    return out;
}

const CHEAPEST_PATTERN = /\b(cheap\w*|affordable|lowest|budget)\b/i;

// True when the message says WHAT to recommend (a category, a price, "cheapest",
// "best sellers") rather than just asking for something. "Show me more" carries
// no criteria of its own, so it inherits the previous request's.
function queryHasCriteria(text, shop) {
    const lower = text.toLowerCase();
    return parseBudget(lower).amount !== null
        || CHEAPEST_PATTERN.test(lower)
        || BEST_SELLER_PATTERN.test(lower)
        || mentionsCatalogTerm(lower, shop);
}

// Up to three in-stock products for the request. Never returns items above a
// stated cap: the old code widened "under 300" to 375 when nothing fit, so a
// customer asking for a limit was shown products over it.
//
// `shown` is the slugs already recommended in this conversation (the browser
// keeps them; this service is stateless). Unseen items always rank ahead of
// seen ones, so asking again gives new products, and `freshOnly` ("show me
// more") drops the seen ones entirely rather than repeating them.
function findProductSuggestions(userQuery, products, { shown = [], freshOnly = false, popular = false, shop = EMPTY_SHOP } = {}) {
    const text = userQuery.toLowerCase();
    const { amount, isCap } = parseBudget(text);
    const seen = new Set(shown);

    let pool = products.filter(p => p.price !== null && p.price !== undefined && !isNaN(Number(p.price)));

    // What the shopper named: a category ("apparel") and/or a kind of item
    // ("jackets"). Naming both narrows to that kind of item within the category.
    // An empty result is an honest answer: it is never widened to the rest of the
    // category, which is how a jackets request used to come back as three shirts.
    const categoryHits = categoriesAsked(text, shop.categories);
    const itemHits = itemGroupsAsked(text, shop.extraGroups);
    const categoryAsked = categoryHits.length > 0 || itemHits.length > 0;
    const askedLabels = [
        ...categoryHits.map(c => c.name),
        ...itemHits.map(h => text.match(wordMatcher(h.asked[0]))[0])
    ];
    const categoryIds = new Set(categoryHits.map(c => Number(c.id)));
    const nameMatches = (p, itemHit) => itemHit.words.some(kw => wordMatcher(kw).test(p.name));
    let outOfStock = [];

    if (categoryAsked) {
        let scoped = pool;
        if (categoryHits.length > 0) {
            scoped = scoped.filter(p => categoryIds.has(Number(p.category_id)));
        }
        if (itemHits.length > 0) {
            const matched = [];
            for (const itemHit of itemHits) {
                // "hoodie" should surface hoodies before sweatshirts; the wider
                // group is only the fallback when nothing carries the exact word.
                const exact = scoped.filter(p => itemHit.asked.some(kw => wordMatcher(kw).test(p.name)));
                matched.push(...(exact.length > 0 ? exact : scoped.filter(p => nameMatches(p, itemHit))));
            }
            scoped = Array.from(new Set(matched));
        }
        pool = scoped;

        // Nothing in stock, but the shop does list it: say it ran out, which is
        // a different answer from "we don't sell that".
        if (pool.length === 0 && itemHits.length > 0) {
            const inStock = new Set(products.map(p => p.slug));
            outOfStock = shop.catalogue
                .filter(p => !inStock.has(p.slug)
                    && (categoryHits.length === 0 || categoryIds.has(Number(p.category_id)))
                    && itemHits.some(h => nameMatches(p, h)))
                .map(p => p.name)
                .slice(0, 3);
        }
    }

    if (amount !== null) {
        pool = isCap
            ? pool.filter(p => Number(p.price) <= amount)
            : pool.filter(p => Number(p.price) <= amount * 1.2);
    }

    const wantsCheapest = CHEAPEST_PATTERN.test(text);
    const vague = !categoryAsked && amount === null && !wantsCheapest && !popular;

    // "Popular" is ranked on completed-order units, then rating. Items nobody
    // has bought yet are left out; the caller says so when nothing is left.
    if (popular) pool = pool.filter(p => Number(p.units_sold) > 0);

    const rank = (list) => {
        if (popular) {
            return [...list].sort((a, b) =>
                (Number(b.units_sold) - Number(a.units_sold)) || (Number(b.avg_rating || 0) - Number(a.avg_rating || 0)));
        }
        if (vague) return diversify(list);
        // Closest to the budget first (best value for "under 300"); otherwise cheapest first.
        return [...list].sort((a, b) => (amount !== null && !wantsCheapest)
            ? Math.abs(amount - Number(a.price)) - Math.abs(amount - Number(b.price))
            : Number(a.price) - Number(b.price));
    };

    const unseen = rank(pool.filter(p => !seen.has(p.slug)));
    const ordered = freshOnly ? unseen : [...unseen, ...rank(pool.filter(p => seen.has(p.slug)))];
    const picks = ordered.slice(0, 3);

    return {
        picks,
        remaining: unseen.length - picks.filter(p => !seen.has(p.slug)).length,
        allSeen: shown.length > 0 && picks.length > 0 && picks.every(p => seen.has(p.slug)),
        meta: { amount, isCap, categoryAsked, askedLabels, outOfStock, wantsCheapest, vague, popular }
    };
}

// Markdown, because the chat widget renders it (sanitised) and the link lets the
// customer open the product page straight from the recommendation. Rating and
// scarcity trail the price so a pick says more than its name.
function formatProductList(items) {
    return items.map(function(item) {
        const name = String(item.name).replace(/[\[\]]/g, '');
        const price = Number(item.price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const label = `${name} – ${item.has_variants ? 'from ' : ''}₱${price}`;

        const notes = [];
        if (Number(item.review_count) > 0) notes.push(`★ ${Number(item.avg_rating).toFixed(1)} (${Number(item.review_count)})`);
        const stock = Number(item.stock_quantity);
        if (stock > 0 && stock <= 5) notes.push(`only ${stock} left`);
        const suffix = notes.length ? ` — ${notes.join(' · ')}` : '';

        return item.slug ? `- [${label}](/product/${encodeURIComponent(item.slug)})${suffix}` : `- ${label}${suffix}`;
    }).join('\n');
}

// Each chip is a message the shopper could have typed, so tapping one goes back
// through the same matching as everything else.
// The names are the database's, so a chip only ever offers a category that has
// something in stock, and the name still resolves through categoriesAsked().
function buildChips(products, { remaining, meta }) {
    const chips = [];
    if (remaining > 0) chips.push('Show me more');

    if (!meta.categoryAsked) {
        const inStock = new Set(products.map(p => p.category_name).filter(Boolean));
        shuffle([...inStock])
            .slice(0, 2)
            .forEach(name => chips.push(`Show me ${name}`));
    }

    if (meta.amount === null && !meta.popular) {
        const prices = products.map(p => Number(p.price)).filter(n => n > 0).sort((a, b) => a - b);
        if (prices.length >= 4) {
            const median = prices[Math.floor(prices.length / 2)];
            chips.push(`Under ₱${Math.max(50, Math.ceil(median / 50) * 50)}`);
        }
    }

    return chips.slice(0, 4);
}

function recommendationHeading({ picks, allSeen, meta }, freshOnly) {
    if (picks.length === 1 && meta.popular && !freshOnly && !allSeen) return "Here's our top seller right now, based on completed orders:";
    if (picks.length === 1) {
        return pickOne(["Here's the one item that fits:", 'Only one product matches that right now:']);
    }
    const n = picks.length;
    if (freshOnly) return pickOne(['Here are a few more:', 'Some other options you might like:', `Here are ${n} more to look at:`]);
    if (allSeen) return "Those are all the matches I have for that request, so here they are again:";
    if (meta.popular) return pickOne(['Here are our best sellers, based on completed orders:', 'These are the items customers pick most:']);
    if (meta.amount !== null) {
        const amount = meta.amount.toLocaleString('en-PH');
        return meta.isCap
            ? pickOne([`Here are ${n} picks for ₱${amount} and below:`, `Within ₱${amount}, these are worth a look:`])
            : pickOne([`Here are ${n} picks around ₱${amount}:`, `Close to ₱${amount}, you might like:`]);
    }
    if (meta.wantsCheapest) return pickOne(['Here are our most affordable picks:', `The ${n} lowest-priced items in stock:`]);
    if (meta.vague) return pickOne(["Here's a mix from across our catalog:", 'A few things you might like:', 'Here are some picks to get you started:']);
    return pickOne([`Here are ${n} products matching your request:`, "Here's what we have in stock for that:", 'These match what you asked for:']);
}

// One reply for every recommendation path (a fresh ask, "best sellers", "show me
// more"). `products` are the slugs it recommended and `query` is what the
// browser should send back as last_query, so a later "more" knows what to
// continue; `chips` are the follow-up replies to offer.
function buildRecommendationReply(query, products, { shown = [], freshOnly = false, shop = EMPTY_SHOP } = {}) {
    const popular = BEST_SELLER_PATTERN.test(query);
    const result = findProductSuggestions(query, products, { shown, freshOnly, popular, shop });

    if (result.picks.length === 0) {
        let response;
        const asked = result.meta.askedLabels.join(' / ');
        if (freshOnly) {
            response = "That's everything I have for that request. Try a different category or price range, or browse the full [catalog](/products).";
        } else if (popular) {
            response = "I can't rank best sellers yet because there aren't enough completed orders, but you can browse the whole [catalog](/products).";
        } else if (result.meta.outOfStock.length > 0) {
            const names = result.meta.outOfStock.map(n => `**${n.replace(/[\[\]*]/g, '')}**`);
            const list = names.length > 1 ? `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}` : names[0];
            response = `${list} ${names.length > 1 ? 'are' : 'is'} out of stock right now. Try another item, or browse our [catalog](/products).`;
        } else if (asked) {
            // Name what was asked for, so "jackets" is not answered as if the
            // shopper had asked for anything else.
            const budget = result.meta.amount !== null ? ' within that budget' : '';
            response = `Sorry, I couldn't find any ${asked} in stock${budget} right now. Try a different category or price range, or browse our [catalog](/products).`;
        } else {
            response = "Sorry, we don't have any matching products in stock right now. Please try a different price range or category, or browse our [catalog](/products).";
        }
        // The category chips are offered even when a category was asked for: the
        // shopper just found nothing there, so pointing elsewhere is the useful move.
        return { response, products: [], query, chips: buildChips(products, { remaining: 0, meta: { ...result.meta, categoryAsked: false } }) };
    }

    return {
        response: recommendationHeading(result, freshOnly) + "\n\n" + formatProductList(result.picks),
        products: result.picks.map(p => p.slug).filter(Boolean),
        query,
        chips: buildChips(products, result)
    };
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
        // Above everything else: "I forgot my password" used to be read as a
        // missed pickup because of the word "forgot".
        id: 'password',
        match: any(
            /\b(forgot(ten)?|forget|lost|reset|change|recover|new)\b.*\bpassword\b/,
            /\bpassword\b.*\b(forgot(ten)?|reset|change|recover|not working|wrong)\b/,
            /\b(can'?t|cannot|unable to|won'?t let me) (log ?in|sign ?in)\b/
        ),
        answer: 'On the login page, choose **Forgot password?** and enter your account email: we will send you a link to set a new one. If you are already logged in, you can change it under **My Account → Profile → Change Password**.'
    },
    {
        id: 'verify-email',
        match: any(
            /\bverif\w*\b.*\b(email|e-mail|link|account)\b/,
            /\b(email|e-mail|link)\b.*\bverif\w*\b/,
            /\b(didn'?t|did not|haven'?t|have not|not) (receive|get|got)\b.*\b(email|link|code)\b/
        ),
        answer: 'After you register, we email you a **verification link**, and you need to verify before you can check out or use My Account. Check your **spam/junk** folder first. If it is not there, log in and use the **resend** option on the verification page (please wait a moment between requests).'
    },
    {
        id: 'refund-cancel',
        match: all(/\b(refund|money back|charged?|reimburse\w*)\b/, /\bcancel/),
        answer: 'Nothing is charged online, because payment is only collected in cash when you pick up your order. So there is nothing to refund when an order is cancelled.'
    },
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
        match: any(/\b(miss(ed)?|unclaimed|deadline)\b/, all(/\b(forgot(ten)?|forget)\b/, /\b(pick|claim|collect)/), /\bhold(ing)? (period|my order)\b/, /\bfail(ed)? to (claim|pick)/, /\bnot (picked up|claimed)\b/),
        // The Terms (6.3, 7.3) say an unclaimed order is cancelled by UBAP and its
        // stock restored; they no longer describe a rescheduled pickup.
        answer: `Once your order is **Ready for Pickup**, claim it at the UBAP Office within the pickup date and time given in your email. If it isn't claimed in time, UBAP will cancel the order and the reserved items go back into stock for other customers. There is no reschedule feature on the website; for questions about a cancelled order, ${contactUbap}.`
    },
    {
        id: 'why-cancelled',
        match: any(/\b(why|how come)\b.*\bcancel/, /\b(my order|it) (was|got|has been|is) cancel/),
        answer: `An order is cancelled in one of two ways: **you** cancelled it while it was still Pending, or **UBAP** cancelled it because it wasn't claimed within its pickup schedule. Either way the reserved items go back into stock. Your order page under **My Orders** shows its status. If you don't recognize the cancellation, ${contactUbap}.`
    },
    {
        id: 'cancel',
        match: /\bcancel(l?ed|l?ing|lation)?\b/,
        answer: 'You can cancel only while your order is still **Pending**: open it under **My Orders** and use the cancel option. Once it is marked **Processing**, it can no longer be cancelled online.'
    },
    {
        // Terms 5.6: no editing items or quantities after placing an order.
        id: 'modify-order',
        match: all(/\b(change|edit|modify|update|add|remove|swap|replace)\b/, /\b(my order|the order|order details|size|quantity|item|items|variant)\b/, /\b(after|already|placed|submitted|once|ordered)\b/),
        answer: `Items and quantities can't be edited after an order is placed. If your order is still **Pending**, you can cancel it under **My Orders** and place a new one with the right items (if they are still in stock). Once it is **Processing**, it can't be cancelled online, so ${contactUbap}.`
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
        // The Terms (section 18) give the institution's address but not the
        // office's own hours, so hours are deliberately not guessed at.
        id: 'office-location',
        match: any(
            /\b(address|located|location|directions?)\b.*\b(ubap|office)\b/,
            /\b(ubap|office)\b.*\b(address|located|location|directions?)\b/,
            /\bwhere (is|are) (the )?(ubap|office)\b/,
            /\b(physical|actual) (store|shop|office)\b/
        ),
        answer: `The UBAP Office is part of **Central Luzon State University, Science City of Muñoz, Nueva Ecija 3119**. Siel Cart has no separate physical store: you order online and collect your order at the UBAP Office. For directions or the office's opening hours, ${contactUbap}.`
    },
    {
        id: 'office-hours',
        match: any(/\b(office|store|shop|ubap)\b.*\b(hours|open|opens|opening|close|closes|closing|schedule)\b/, /\b(opening|office|business|store) hours\b/, /\bwhat time\b.*\b(open|close)/),
        answer: `I don't have the UBAP Office's opening hours. Your **Ready for Pickup** email gives the exact pickup date and time for your order. For anything else, ${contactUbap}.`
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
        // Terms 15: a customer can delete their own account unless an order is
        // still active (Customer::deleteAccount() enforces the same rule).
        id: 'delete-account',
        match: any(/\b(delete|remove|close|deactivate|cancel)\b.*\b(my )?account\b/, /\baccount\b.*\b(delet\w*|remov\w*|clos\w*|deactivat\w*)\b/),
        answer: 'You can delete your account yourself under **My Account → Profile → Delete Account**. It is permanent, and it is not allowed while you have an order that is **Pending**, **Processing**, or **Ready for Pickup**: wait until those orders are completed or cancelled first.'
    },
    {
        // Terms 3: name, email and phone can be updated; date of birth cannot.
        id: 'update-profile',
        match: all(/\b(change|update|edit|modify|correct|fix)\b/, /\b(email|e-mail|phone|contact number|mobile|first name|last name|my name|profile|birthday|birthdate|date of birth)\b/),
        answer: 'Open **My Account → Profile**. You can update your **name** and **phone number** there, and change your **email** with the Change Email Address option (it asks for your current password). Your **date of birth** cannot be changed there.'
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
        // Terms 10.4.
        id: 'chat-privacy',
        match: any(
            /\b(chat|chats|chatbot|conversations?)\b.*\b(saved?|stored?|recorded?|kept|logged|private)\b/,
            /\b(saved?|stored?|recorded?|kept|logged)\b.*\b(chat|chats|chatbot|conversations?)\b/
        ),
        answer: 'Chatbot conversations are **not recorded or stored** by Siel Cart.'
    },
    {
        id: 'privacy',
        match: /\b(privacy|personal (information|data|info)|data (privacy|protection|handling)|data protection officer|dpo|is my data|my data|my information)\b/,
        answer: 'We only collect the information needed to process your orders and manage your account. For full details, see our [Privacy Policy](/privacy-policy). For data privacy concerns, email the CLSU Data Protection Officer at **dpo@clsu.edu.ph**.'
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
        match: /\b(what is siel ?cart|about siel ?cart|what is this (store|shop|website|site)|who (runs|owns|operates|manages|is behind|sells)|is (this|it|siel ?cart)( (store|shop|site|website))? (legit|official|real|safe|trusted|legitimate)|official (store|shop))\b/,
        answer: '**Siel Cart** is the official online store for CLSU merchandise, run by the UBAP Office (University Business Affairs Program) of Central Luzon State University. It is pickup-only and cash-only: order online, then collect and pay in cash at the UBAP Office.'
    },
    {
        // Anchored so it only catches a message that is nothing but a courtesy;
        // "thanks, and how do I cancel?" still reaches the entry for cancelling.
        id: 'thanks',
        match: /^(thanks?( you)?( (so|very) much)?|thank you|salamat( po)?)[!. ]*$/,
        answer: "You're welcome! Ask me anything else about ordering, pickup, payment, or our products."
    },
    {
        id: 'acknowledged',
        match: /^(ok(ay)?( po)?|got it|noted|great|cool|sige|alright)[!. ]*$/,
        answer: 'Alright! Let me know if you need anything else about ordering, pickup, payment, or our products.'
    },
    {
        id: 'goodbye',
        match: /^(bye|goodbye|good bye|see you|see ya|that'?s all|that is all|no,? thanks?)[!. ]*$/,
        answer: 'Goodbye! Thanks for shopping at Siel Cart.'
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

const RECOMMENDATION_PATTERN = /\b(suggest\w*|recommend\w*|price[sd]?|pesos?|php|under|below|less than|budget|cheap\w*|affordable|shirts?|t-?shirts?|hoodies?|jackets?|apparel|clothes|mugs?|tumblers?|bottles?|bags?|totes?|notebooks?|pens?|lanyards?|keychains?|merch\w*|stationery|accessor\w*|drinkware|supplies|products?|items?|catalog|what do you (sell|have)|what can i buy)\b|₱\s*\d/i;
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

const MORE_PATTERN = /\b(more|others?|another|else|different|next|again)\b/i;

// Only what the widget is allowed to send: a bounded list of slugs and one
// bounded query string. Anything else is dropped, since this endpoint is open to
// guests and both values end up in matching logic.
function readConversationContext(body) {
    const shown = Array.isArray(body?.shown)
        ? body.shown.filter(s => typeof s === 'string' && s.length > 0 && s.length <= 255).slice(-100)
        : [];
    const lastQuery = typeof body?.last_query === 'string' ? body.last_query.trim().slice(0, 2000) : '';
    return { shown, lastQuery };
}

// A short follow-up asking for more, and only once something was recommended:
// otherwise "what else can you do?" would be read as a product request.
function isMoreRequest(text, { shown, lastQuery }) {
    if (shown.length === 0 || !lastQuery) return false;
    return text.split(/\s+/).length <= 8 && MORE_PATTERN.test(text);
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

        // Active categories and every listed product name, not just what is in
        // stock: asking for something that is sold out must be answered "out of
        // stock", not read as a vague request for a random mix.
        const shop = await loadShop();

        const noProductsReply = "I couldn't find any products in stock to recommend right now. Please check our [catalog](/products) for the latest items.";
        const hasCatalog = Array.isArray(dbProducts) && dbProducts.length > 0;

        // What the browser remembers for us (this service keeps no per-customer
        // state): the slugs already recommended and the request that produced them.
        const context = readConversationContext(req.body);

        // 4a. "Show me more" / "anything else?" after a recommendation continues
        // that recommendation with items not shown yet. It has no keyword of its
        // own, so without this it fell through to the model and got the
        // "temporarily unavailable" reply whenever the free models were down.
        if (isMoreRequest(msgLower, context)) {
            if (!hasCatalog) return res.json({ response: noProductsReply });

            const query = queryHasCriteria(msgLower, shop) ? message : context.lastQuery;
            return res.json(buildRecommendationReply(query, dbProducts, { shown: context.shown, freshOnly: true, shop }));
        }

        // 4b. Product recommendations, answered from the database (no model call).
        // Naming a category or a kind of item counts as asking, so a category the
        // admin adds ("Show me Athletics") works without editing RECOMMENDATION_PATTERN.
        if (BEST_SELLER_PATTERN.test(msgLower) || RECOMMENDATION_PATTERN.test(msgLower) || mentionsCatalogTerm(msgLower, shop)) {
            if (!hasCatalog) return res.json({ response: noProductsReply });

            return res.json(buildRecommendationReply(message, dbProducts, { shown: context.shown, shop }));
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
