// "Is the hoodie in stock?" / "Is the hoodie available in Large?"
//
// These are questions about what is on the shelf, so the answer is read from
// the database rows and printed here; no language model is involved, which is
// also why it keeps working when the free models are rate-limited. The old
// behaviour was the FAQ's one-line "availability is shown on each product's
// page" followed by a recommendation list, which never said whether the thing
// asked about was actually in stock, or in the size asked for.

import { itemGroupsAsked, wordMatcher } from './vocabulary.js';

// Stock words, plus "size(s)" ("what sizes does the hoodie come in?") and the
// Taglish forms. Deliberately not a bare "have": "do you have jackets" is a
// product search and keeps the recommendation list.
export const AVAILABILITY_PATTERN = /\b(in stock|out of stock|sold out|stocks?|available|availability|still (?:have|got)|sizes?|meron pa|mayroon pa|may stock|ubos(?: na)?)\b/i;

// "How many hoodies are left?" asks for the number itself. Without it the reply
// only names a count once it is low ("only 3 left"), which answers "is it in
// stock" and leaves the shopper who asked for a quantity with none. "How many
// sizes/colours" is about the choices, not the units, so it is excluded.
export const QUANTITY_PATTERN = /\b(?:how many(?!\s+(?:sizes?|colou?rs?|options?|variants?|designs?|kinds?|types?|products?|items?)\b)|how much (?:stock|is left|are left|left)|quantit(?:y|ies)|ilan|(?:stocks?|units?|pieces?|pcs|pairs?) (?:left|remaining)|number of (?:stocks?|units?|pieces?))\b/i;

export const asksQuantity = (message) => QUANTITY_PATTERN.test(message);

const MAX_PRODUCTS = 5;
const MAX_CHIPS = 4;

// A product asked for by the kind of item ("hoodie") out of the shop's whole
// listing, sold out or not: a sold-out hoodie still has to be reported as sold
// out rather than silently left out. The word the shopper used is matched
// before its synonyms, so "hoodie" is not answered with a sweatshirt unless the
// shop has no hoodie.
export function matchAvailabilityProducts(text, shop) {
    const hits = itemGroupsAsked(text, shop.extraGroups);
    if (hits.length === 0) return { products: [], word: '' };

    const matched = [];
    for (const hit of hits) {
        const exact = shop.catalogue.filter(p => hit.asked.some(w => wordMatcher(w).test(p.name)));
        matched.push(...(exact.length > 0 ? exact : shop.catalogue.filter(p => hit.words.some(w => wordMatcher(w).test(p.name)))));
    }

    const all = [...new Map(matched.map(p => [p.slug, p])).values()];
    const { products, words } = narrowByName(all, text);
    const word = [...words, hits[0].asked[0]].join(' ');
    return { products: products.slice(0, MAX_PRODUCTS), word, more: Math.max(0, products.length - MAX_PRODUCTS) };
}

// Words that are not part of a product's identity, so a question's own wording
// ("is the black shirt available?") never narrows anything by accident.
const QUESTION_WORDS = new Set([
    'the', 'and', 'for', 'you', 'are', 'was', 'any', 'have', 'has', 'there', 'this', 'that', 'what', 'which',
    'stock', 'stocks', 'sold', 'out', 'available', 'availability', 'still', 'size', 'sizes', 'with', 'your', 'got'
]);

// "Sielesyuan T-Shirt - Brown" and "- Green" are separate products that differ
// only in a word of their name, so "is the black shirt in stock?" has to keep
// the black ones. A word narrows only when it is in some of the candidates and
// not all of them -- "shirt" runs through every one and says nothing -- and a
// size word never narrows: it is asking about a variant, not a product.
function narrowByName(products, text) {
    if (products.length < 2) return { products, words: [] };

    const tokens = [...new Set(text.match(/[a-z0-9]{3,}/g) ?? [])]
        .filter(t => !QUESTION_WORDS.has(t) && sizesMentioned(t).length === 0);
    const words = tokens.filter(t => {
        const hit = products.filter(p => wordMatcher(t).test(p.name)).length;
        return hit > 0 && hit < products.length;
    });
    if (words.length === 0) return { products, words: [] };

    const everyWord = products.filter(p => words.every(w => wordMatcher(w).test(p.name)));
    return everyWord.length > 0
        ? { products: everyWord, words }
        : { products: products.filter(p => words.some(w => wordMatcher(w).test(p.name))), words };
}

// Variants are named "CLSU Athletes Hoodie - Large", "CLSU Tumbler Green" or
// just "Black": what the shopper picks is what is left once the product's own
// name is taken off the front, and then the part after the last separator.
export const optionLabel = (name, productName = '') => {
    let text = String(name).trim();
    const prefix = String(productName).trim();
    if (prefix && text.toLowerCase().startsWith(prefix.toLowerCase()) && text.length > prefix.length) {
        text = text.slice(prefix.length).replace(/^[\s\-\/:]+/, '');
    }
    const parts = text.split(/\s+-\s+|\s+\/\s+/);
    return parts[parts.length - 1].trim();
};

// "Large", "L", "large size" and "Large" in a name must all mean the same thing,
// and "XLarge", "XL" and "extra large" another. Only the common spellings are
// folded together; anything else is compared as the letters it spells.
const SIZE_ALIASES = new Map([
    ['xsmall', 'xs'], ['extrasmall', 'xs'],
    ['small', 's'], ['medium', 'm'], ['large', 'l'],
    ['xlarge', 'xl'], ['extralarge', 'xl'],
    ['xxlarge', '2xl'], ['xxl', '2xl'], ['2xlarge', '2xl'],
    ['xxxlarge', '3xl'], ['xxxl', '3xl'], ['3xlarge', '3xl']
]);
const sizeKey = (text) => {
    const squashed = String(text).toLowerCase().replace(/[^a-z0-9]/g, '');
    return SIZE_ALIASES.get(squashed) ?? squashed;
};

// Whether an option is a clothing size (as opposed to a colour or a design),
// by its folded spelling: s, m, l, xs, xl, 2xl, 3xl.
const isSize = (label) => /^(?:x{0,2}[sl]|m|[2-5]xl)$/.test(sizeKey(label));

// A size written out in the message. A bare "s", "m" or "l" is a size only next
// to the word "size" ("size M", "M size"): on their own they are letters of
// "it's" and "I'm".
const SIZE_WORD = /\b(?:extra[- ]?small|extra[- ]?large|x{1,3}[- ]?(?:small|large)|[2-5]x[- ]?(?:large|l)?|xxs|xs|xl|xxl|xxxl|small|medium|large)\b/gi;
const SIZE_LETTER = /\b(?:size\s+([sml])|([sml])\s+size)\b/gi;

export function sizesMentioned(message) {
    const found = [];
    for (const m of message.matchAll(SIZE_WORD)) found.push(m[0]);
    for (const m of message.matchAll(SIZE_LETTER)) found.push(m[1] || m[2]);
    return found;
}

// Which of a product's options the message asked for: a size by its folded
// spelling, anything else (a colour, a design) by the option's own words.
function optionAsked(label, message, sizesAsked) {
    const key = sizeKey(label);
    if (sizesAsked.some(s => sizeKey(s) === key)) return true;
    return label.length >= 3 && !isSize(label) && wordMatcher(label).test(message);
}

const money = (n) => `₱${Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const safe = (s) => String(s).replace(/[\[\]*]/g, '');
const joinList = (items) => (items.length > 1 ? `${items.slice(0, -1).join(', ')} and ${items[items.length - 1]}` : items[0]);

// One product, its price range and what is on its shelf, folded from the rows.
function describe(product, variants) {
    if (!Number(product.has_variants)) {
        return {
            slug: product.slug, name: safe(product.name), options: [],
            inStock: Number(product.stock_quantity) > 0,
            price: Number(product.price), lowStock: Number(product.stock_quantity)
        };
    }

    const options = variants.map(v => ({
        label: safe(optionLabel(v.name, product.name)), price: Number(v.price), stock: Number(v.stock_quantity)
    }));
    return { slug: product.slug, name: safe(product.name), options, inStock: options.some(o => o.stock > 0) };
}

const priceOf = (d, options = d.options.filter(o => o.stock > 0)) => {
    if (options.length === 0) return d.price !== undefined ? money(d.price) : '';
    const prices = options.map(o => o.price);
    const low = Math.min(...prices);
    return prices.every(p => p === low) ? money(low) : `from ${money(low)}`;
};

const link = (d, text) => (d.slug ? `[${text}](/product/${encodeURIComponent(d.slug)})` : text);
const listOf = (options, extra = '') => options.map(o => `${o.label}${extra}`).join(', ');
const totalStock = (d) => d.options.reduce((sum, o) => sum + Math.max(0, o.stock), 0);
const kindOf = (labels) => (labels.every(isSize) ? 'size' : 'option');

function chipsFor(word, options, skip = new Set()) {
    const seen = new Set();
    return options
        .filter(o => o.stock > 0 && !skip.has(o.label.toLowerCase()))
        .filter(o => (seen.has(o.label.toLowerCase()) ? false : seen.add(o.label.toLowerCase())))
        .slice(0, MAX_CHIPS)
        .map(o => `Is the ${word} available in ${o.label}?`);
}

/**
 * @param {string} message          what the shopper wrote
 * @param {{products: object[], word: string}} matched  from matchAvailabilityProducts
 * @param {{products: object[], variants: Map<string, object[]>}} rows  fresh stock rows
 * @returns {{response: string, chips: string[]}}
 */
export function buildAvailabilityReply(message, matched, rows) {
    const bySlug = new Map(rows.products.map(p => [p.slug, p]));
    const described = matched.products
        .filter(p => bySlug.has(p.slug))
        .map(p => describe(bySlug.get(p.slug), rows.variants.get(p.slug) ?? []));

    // The shop lists it but the storefront no longer shows it (turned off since
    // the catalogue was read): nothing honest to say about its stock.
    if (described.length === 0) {
        return { response: "I couldn't find that item in our catalog right now. You can browse the full [catalog](/products).", chips: [] };
    }

    const sizesAsked = sizesMentioned(message);
    const withOptions = described.filter(d => d.options.length > 0);
    const requested = withOptions.length > 0
        ? withOptions.flatMap(d => d.options).filter(o => optionAsked(o.label, message, sizesAsked))
        : [];
    const askedLabels = new Set(requested.map(o => o.label.toLowerCase()));

    const counts = asksQuantity(message);

    let reply;
    if (sizesAsked.length > 0 && askedLabels.size === 0 && withOptions.length > 0) {
        // Sizes were named but this item has no such option at all.
        reply = noSuchOption(sizesAsked[0], withOptions, matched.word);
    } else {
        reply = askedLabels.size > 0
            ? answerForOption(requested, askedLabels, described, matched.word, counts)
            : answerForItem(described, matched.word, counts);
    }

    // Only the first few matches are checked; say so rather than let a longer
    // list pass for the whole shop.
    return matched.more > 0
        ? { ...reply, response: `${reply.response}

There are ${matched.more} more matching products: see the full [catalog](/products).` }
        : reply;
}

// "Is the hoodie in stock?": which of the matching products have anything, and
// what is left on each shelf.
function answerForItem(described, word, counts = false) {
    const inStock = described.filter(d => d.inStock);
    const soldOut = described.filter(d => !d.inStock);
    const soldOutLine = soldOut.length > 0
        ? `${joinList(soldOut.map(d => `**${d.name}**`))} ${soldOut.length > 1 ? 'are' : 'is'} out of stock right now.`
        : '';

    if (inStock.length === 0) {
        return {
            response: `${soldOutLine} Try another item, or browse our [catalog](/products).`,
            chips: []
        };
    }

    const lines = inStock.map(d => {
        const text = `${d.name} – ${d.options.length > 0 ? priceOf(d) : money(d.price)}`;
        // Each option on its own nested line under the name, in the order the
        // admin set, so the shopper reads down the sizes instead of along a sentence.
        const sub = d.options.map(o => (o.stock > 0
            ? `  - ${o.label}${counts ? ` · ${o.stock} left` : o.stock <= 5 ? ` · only ${o.stock} left` : ''}`
            : `  - ${o.label} · sold out`));
        const units = d.options.length === 0 ? d.lowStock : totalStock(d);
        const low = d.options.length === 0 && d.lowStock > 0 && (counts || d.lowStock <= 5)
            ? ` — ${counts ? '' : 'only '}${d.lowStock} left`
            : '';
        // A variable product's total is stated on its own line, so the per-size
        // numbers beneath it do not have to be added up by the shopper.
        const total = counts && d.options.length > 1 ? ` — ${units} left in total` : '';

        return [`- ${link(d, text)}${low}${total}`, ...sub].join('\n');
    });

    const live = inStock.flatMap(d => d.options);
    const kind = kindOf(live.filter(o => o.stock > 0).map(o => o.label));

    let heading;
    if (counts && inStock.length === 1) {
        const d = inStock[0];
        const units = d.options.length === 0 ? d.lowStock : totalStock(d);
        heading = `**${d.name}** has **${units}** ${units === 1 ? 'unit' : 'units'} left in stock${d.options.length > 1 ? ', across all sizes and options' : ''}.`;
    } else if (counts) {
        heading = `Here's how many ${word}${/s$/.test(word) ? '' : 's'} we have left:`;
    } else {
        heading = inStock.length === 1
            ? `Yes, **${inStock[0].name}** is in stock.`
            : `Yes, we have ${word}${/s$/.test(word) ? '' : 's'} in stock.${live.length > 0 ? ` Availability depends on the ${kind === 'size' ? 'design and size' : 'design and option'}:` : ''}`;
    }
    const ask = !counts && live.some(o => o.stock > 0) ? `\n\nWhich ${kind} are you looking for?` : '';

    return {
        response: [heading, lines.join('\n'), soldOutLine].filter(Boolean).join('\n\n') + ask,
        chips: chipsFor(word, live)
    };
}

// "Is the hoodie available in Large?": the answer is about that option, per
// product, and says what IS left when it is not there.
function answerForOption(requested, askedLabels, described, word, counts = false) {
    const asked = [...new Map(requested.map(o => [o.label.toLowerCase(), o.label])).values()];
    const askedText = joinList(asked.map(l => `**${l}**`));

    const available = [];
    const unavailable = [];
    const notOffered = [];
    for (const d of described) {
        if (d.options.length === 0) continue;
        const hit = d.options.filter(o => askedLabels.has(o.label.toLowerCase()));
        const live = hit.filter(o => o.stock > 0);
        if (live.length > 0) available.push({ d, live });
        else if (hit.length > 0) unavailable.push({ d, hit });
        // "Sold out" and "never made in that size" are different news.
        else notOffered.push(d);
    }

    const rest = (d) => d.options.filter(o => o.stock > 0 && !askedLabels.has(o.label.toLowerCase()));

    const lines = available.map(({ d, live }) => {
        if (counts) {
            return `- ${link(d, `${d.name} – ${priceOf(d, live)}`)} — ${live.map(o => `${o.label}: ${o.stock} left`).join(', ')}`;
        }
        const low = live.filter(o => o.stock <= 5).map(o => `only ${o.stock} left`);
        return `- ${link(d, `${d.name} – ${priceOf(d, live)}`)} — ${listOf(live)} in stock${low.length ? ` · ${low[0]}` : ''}`;
    });

    const misses = unavailable.map(({ d }) => {
        const others = rest(d);
        return others.length > 0
            ? `${askedText} is sold out for **${d.name}**, which is still available in: ${listOf(others)}.`
            : `**${d.name}** is out of stock right now.`;
    });

    const notOfferedLine = notOffered.length > 0
        ? `${joinList(notOffered.map(d => `**${d.name}**`))} ${notOffered.length > 1 ? "don't" : "doesn't"} come in ${askedText}.`
        : '';

    const chipSource = [...available, ...unavailable, ...notOffered.map(d => ({ d }))].flatMap(({ d }) => d.options);
    const chips = chipsFor(word, chipSource, askedLabels);

    if (available.length > 0) {
        const heading = available.length === 1 && unavailable.length === 0 && notOffered.length === 0
            ? `Yes, **${available[0].d.name}** is available in ${askedText}.`
            : `Yes, ${askedText} is available for:`;
        return { response: [heading, lines.join('\n'), ...misses, notOfferedLine].filter(Boolean).join('\n\n'), chips };
    }

    const tail = chips.length > 0 ? `\n\nWould you like to check another ${kindOf(asked)}?` : '';
    return { response: `${[...misses, notOfferedLine].filter(Boolean).join(' ')}${tail}`, chips };
}

// A size the item does not come in ("XXL" on a hoodie sold in S to XL): said so,
// with what it does come in, instead of reading as "sold out".
function noSuchOption(asked, withOptions, word) {
    const names = joinList(withOptions.map(d => `**${d.name}**`));
    const live = withOptions.flatMap(d => d.options).filter(o => o.stock > 0);
    const unique = [...new Map(live.map(o => [o.label.toLowerCase(), o])).values()];

    const response = unique.length > 0
        ? `I couldn't find a **${asked}** option for ${names}. What's in stock: ${listOf(unique)}.`
        : `I couldn't find a **${asked}** option for ${names}, and nothing is in stock right now. You can browse our [catalog](/products).`;
    return { response, chips: chipsFor(word, unique) };
}
