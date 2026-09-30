// How shopper wording is turned into something the catalogue can be searched
// with. Extracted verbatim from server.js so the pipeline and its tests can use
// the same matching the deterministic path has always used.

export const escapeRegex = (str) => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

// Whole-word match, plural allowed. The old `text.includes(kw)` matched "pen" in
// "happens" and "cap" in "capital", so an FAQ about a missed pickup came back
// as a product list.
export const wordMatcher = (kw) => new RegExp(`\\b${escapeRegex(kw)}(?:s|es)?\\b`, 'i');

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
export const ITEM_GROUPS = [
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
export const CATEGORY_ALIASES = [
    { words: ['clothes', 'clothing', 'wear', 'apparel'], name: /apparel|cloth|wear/i },
    { words: ['stationery', 'stationary', 'supplies', 'school'], name: /station|suppl|school/i },
    { words: ['accessory', 'accessories'], name: /accessor/i },
    { words: ['drinkware'], name: /drink/i }
];

// A category is matched by its whole name or by any word of it long enough to be
// meaningful ("Gift Set" -> "gift", not "set"), singular or plural.
export const categoryWords = (name) => {
    const words = [name, ...name.split(/[^a-z0-9]+/i).filter(w => w.length >= 4)];
    return [...new Set(words.flatMap(w => (/s$/i.test(w) && w.length > 4 ? [w, w.slice(0, -1)] : [w])))];
};

export function categoriesAsked(text, categories) {
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

export function nameVocabulary(catalogue) {
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

// The search words for a set of concepts, widened through ITEM_GROUPS.
//
// The model names the KIND of thing wanted ("t-shirt", "jacket"), and product
// names rarely use that word: a shop selling "sielesyuan shirt" and "CLSU
// Windbreaker" has neither "t-shirt" nor "jacket" in any name, so searching
// the concepts literally found nothing and the assistant said the shop had no
// shirts while one sat in stock. ITEM_GROUPS already records which words mean
// the same kind of item; this is the semantic path using it too.
//
// A concept in no group is searched as itself. Groups stay tight, so this
// never widens a jacket request into shirts.
export function expandConcepts(concepts, limit = 12) {
    const terms = [];

    for (const concept of concepts) {
        const matcher = wordMatcher(concept);
        const group = ITEM_GROUPS.find(words =>
            words.some(w => w === concept || matcher.test(w) || wordMatcher(w).test(concept)));
        terms.push(concept, ...(group ?? []));
    }

    return [...new Set(terms)].slice(0, limit);
}

// [{ words, asked }] for each group the text mentions: `asked` are the group's
// words the shopper actually used, `words` the whole group.
export function itemGroupsAsked(text, extraGroups = []) {
    return [...ITEM_GROUPS, ...extraGroups]
        .map(words => ({ words, asked: words.filter(w => wordMatcher(w).test(text)) }))
        .filter(g => g.asked.length > 0);
}

// `shop` is what the database says the shop sells right now: { categories,
// catalogue, extraGroups }. See loadShop().
export const EMPTY_SHOP = { categories: [], catalogue: [], extraGroups: [] };

export const mentionsCatalogTerm = (text, shop) =>
    categoriesAsked(text, shop.categories).length > 0 || itemGroupsAsked(text, shop.extraGroups).length > 0;

// Returns { amount, isCap } for "under 300", "below ₱500", "under ₱500",
// "300 pesos", or { amount: null } when no price was given. A bare number is
// NOT a price. The optional currency sign after the keyword matters: the quick
// reply chips read "Under ₱250", and without it the keyword failed to match,
// the bare "₱250" did, and the cap was silently treated as an "around" budget.
//
// The Filipino caps ("hindi hihigit sa 500", "wag lalagpas ng 500", "500 pababa")
// sit in the same alternation as the English ones: a Taglish budget has to be
// read as a cap by the deterministic path too, not only by the model, because
// the deterministic path is what answers when the model is unavailable.
export function parseBudget(text) {
    const t = text.replace(/(\d),(\d{3})/g, '$1$2');
    const m = t.match(/(under|below|less than|within|up to|at most|not more than|max(?:imum)?(?: of)?|budget(?: of)?|around|about|hindi (?:hihigit|lalagpas|sosobra)(?: sa| ng| na)?|wag(?: na)? (?:hihigit|lalagpas|sosobra)(?: sa| ng)?|kulang sa|₱|php)\s*(?:₱|php)?\s*(\d+(?:\.\d+)?)/i)
        || t.match(/(\d+(?:\.\d+)?)\s*(?:pesos?|php|₱)?\s*(pababa|pataas)\b/i)
        || t.match(/(\d+(?:\.\d+)?)\s*(pesos?|php|₱)/i);
    if (!m) return { amount: null, isCap: false };

    const amount = parseFloat(isNaN(parseFloat(m[1])) ? m[2] : m[1]);
    const isCap = /(under|below|less than|within|up to|at most|not more than|max|hindi (hihigit|lalagpas|sosobra)|wag|kulang sa|pababa)/i.test(m[0]);
    return { amount, isCap };
}

export const CHEAPEST_PATTERN = /\b(cheap\w*|affordable|lowest|budget|mura\w*)\b/i;

export const RECOMMENDATION_PATTERN = /\b(suggest\w*|recommend\w*|price[sd]?|pesos?|php|under|below|less than|budget|cheap\w*|affordable|shirts?|t-?shirts?|hoodies?|jackets?|apparel|clothes|mugs?|tumblers?|bottles?|bags?|totes?|notebooks?|pens?|lanyards?|keychains?|merch\w*|stationery|accessor\w*|drinkware|supplies|products?|items?|catalog|what do you (sell|have)|what can i buy)\b|₱\s*\d/i;

export const BEST_SELLER_PATTERN = /\b(best[- ]?sell(ers?|ing)|top[- ]?sell(ers?|ing)|most popular|popular)\b/i;

// True when the message says WHAT to recommend (a category, a price, "cheapest",
// "best sellers") rather than just asking for something. "Show me more" carries
// no criteria of its own, so it inherits the previous request's.
export function queryHasCriteria(text, shop) {
    const lower = text.toLowerCase();
    return parseBudget(lower).amount !== null
        || CHEAPEST_PATTERN.test(lower)
        || BEST_SELLER_PATTERN.test(lower)
        || mentionsCatalogTerm(lower, shop);
}

export const MORE_PATTERN = /\b(more|others?|another|else|different|next|again|pa\b|iba)\b/i;

// A short follow-up asking for more, and only once something was recommended:
// otherwise "what else can you do?" would be read as a product request.
export function isMoreRequest(text, { shown, lastQuery }) {
    if (shown.length === 0 || !lastQuery) return false;
    return text.split(/\s+/).length <= 8 && MORE_PATTERN.test(text);
}
