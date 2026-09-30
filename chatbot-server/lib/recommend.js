// Ranking, wording and follow-up chips for a product answer. Every number and
// name it prints comes from a database row passed in; nothing here is generated
// by a language model.

import {
    BEST_SELLER_PATTERN,
    CHEAPEST_PATTERN,
    EMPTY_SHOP,
    categoriesAsked,
    itemGroupsAsked,
    parseBudget,
    wordMatcher
} from './vocabulary.js';

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

// What the shopper named, worked out from the words they used. Split out of
// findProductSuggestions so the semantic path can hand in criteria the model
// read instead ("something warm" carries no keyword at all).
function criteriaFromText(text, shop) {
    const categoryHits = categoriesAsked(text, shop.categories);
    const itemHits = itemGroupsAsked(text, shop.extraGroups);
    const { amount, isCap } = parseBudget(text);
    return {
        amount,
        isCap,
        categoryHits,
        itemHits,
        askedLabels: [
            ...categoryHits.map(c => c.name),
            ...itemHits.map(h => text.match(wordMatcher(h.asked[0]))[0])
        ],
        wantsCheapest: CHEAPEST_PATTERN.test(text)
    };
}

// Up to three in-stock products for the request. Never returns items above a
// stated cap: the old code widened "under 300" to 375 when nothing fit, so a
// customer asking for a limit was shown products over it.
//
// `shown` is the slugs already recommended in this conversation (the browser
// keeps them; this service is stateless). Unseen items always rank ahead of
// seen ones, so asking again gives new products, and `freshOnly` ("show me
// more") drops the seen ones entirely rather than repeating them.
//
// `criteria` lets the caller supply what was asked for rather than have it read
// off the text. The semantic path uses it to pass the model's reading of the
// message; everything else leaves it out and the wording is parsed as before.
export function findProductSuggestions(userQuery, products, { shown = [], freshOnly = false, popular = false, shop = EMPTY_SHOP, criteria = null } = {}) {
    const text = userQuery.toLowerCase();
    const seen = new Set(shown);

    const {
        amount,
        isCap,
        categoryHits,
        itemHits,
        askedLabels,
        wantsCheapest,
        // The semantic path has already narrowed the rows in SQL, matching
        // descriptions as well as names, so it supplies no itemHits -- re-running
        // the name check here would throw away every product that matched on its
        // description. These two say "something specific was asked for" and "here
        // is what we found listed but sold out" without that filtering.
        conceptsAsked = false,
        outOfStock: suppliedOutOfStock = null
    } = criteria ?? criteriaFromText(text, shop);

    let pool = products.filter(p => p.price !== null && p.price !== undefined && !isNaN(Number(p.price)));

    // What the shopper named: a category ("apparel") and/or a kind of item
    // ("jackets"). Naming both narrows to that kind of item within the category.
    // An empty result is an honest answer: it is never widened to the rest of the
    // category, which is how a jackets request used to come back as three shirts.
    const categoryAsked = categoryHits.length > 0 || itemHits.length > 0 || conceptsAsked;
    const categoryIds = new Set(categoryHits.map(c => Number(c.id)));
    const nameMatches = (p, itemHit) => itemHit.words.some(kw => wordMatcher(kw).test(p.name));
    let outOfStock = suppliedOutOfStock ?? [];

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
        if (pool.length === 0 && itemHits.length > 0 && suppliedOutOfStock === null) {
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
export function formatProductList(items) {
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
export function buildChips(products, { remaining, meta }) {
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

export function recommendationHeading({ picks, allSeen, meta }, freshOnly) {
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

// The reply for a result that found nothing. Split out so the semantic path can
// reuse the same honest wording: naming what was asked for, and separating "we
// list it but it ran out" from "nothing matched".
export function emptyResultResponse(result, { freshOnly, popular }) {
    const asked = result.meta.askedLabels.join(' / ');
    if (freshOnly) {
        return "That's everything I have for that request. Try a different category or price range, or browse the full [catalog](/products).";
    }
    if (popular) {
        return "I can't rank best sellers yet because there aren't enough completed orders, but you can browse the whole [catalog](/products).";
    }
    if (result.meta.outOfStock.length > 0) {
        const names = result.meta.outOfStock.map(n => `**${n.replace(/[\[\]*]/g, '')}**`);
        const list = names.length > 1 ? `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}` : names[0];
        return `${list} ${names.length > 1 ? 'are' : 'is'} out of stock right now. Try another item, or browse our [catalog](/products).`;
    }
    if (asked) {
        // Name what was asked for, so "jackets" is not answered as if the
        // shopper had asked for anything else.
        const budget = result.meta.amount !== null ? ' within that budget' : '';
        return `Sorry, I couldn't find any ${asked} in stock${budget} right now. Try a different category or price range, or browse our [catalog](/products).`;
    }
    return "Sorry, we don't have any matching products in stock right now. Please try a different price range or category, or browse our [catalog](/products).";
}

// One reply for every recommendation path (a fresh ask, "best sellers", "show me
// more"). `products` are the slugs it recommended and `query` is what the
// browser should send back as last_query, so a later "more" knows what to
// continue; `chips` are the follow-up replies to offer.
export function buildRecommendationReply(query, products, { shown = [], freshOnly = false, shop = EMPTY_SHOP, criteria = null } = {}) {
    const popular = BEST_SELLER_PATTERN.test(query);
    const result = findProductSuggestions(query, products, { shown, freshOnly, popular, shop, criteria });

    if (result.picks.length === 0) {
        // The category chips are offered even when a category was asked for: the
        // shopper just found nothing there, so pointing elsewhere is the useful move.
        return {
            response: emptyResultResponse(result, { freshOnly, popular }),
            products: [],
            query,
            chips: buildChips(products, { remaining: 0, meta: { ...result.meta, categoryAsked: false } }),
            result
        };
    }

    return {
        response: recommendationHeading(result, freshOnly) + "\n\n" + formatProductList(result.picks),
        products: result.picks.map(p => p.slug).filter(Boolean),
        query,
        chips: buildChips(products, result),
        result
    };
}
