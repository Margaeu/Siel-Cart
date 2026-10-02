// What happens to one shopper message, start to finish.
//
// The division of labour, which is the whole point of the design:
//   - the language model READS the question and EXPLAINS the answer;
//   - MySQL decides what exists, what it costs, and whether it is in stock;
//   - the fixed FAQ and keyword rules answer on their own when the model is
//     unavailable, which for free OpenRouter models is often.
//
// Nothing the model returns is printed without being checked first, and no
// model output ever reaches SQL as anything but a bound value.

import {
    CATALOG_UNAVAILABLE_MESSAGE,
    CONVERSATIONAL_FAQ_IDS,
    FRIENDLY_ERROR_MESSAGE,
    STANDARD_REFUSAL,
    findFaqEntry,
    isIrrelevantQuery,
    normalizeForMatching
} from './store-knowledge.js';
import { CatalogUnavailableError } from './catalog.js';
import {
    BEST_SELLER_PATTERN,
    CHEAPEST_PATTERN,
    EMPTY_SHOP,
    expandConcepts,
    isMoreRequest,
    mentionsCatalogTerm,
    parseBudget,
    queryHasCriteria,
    RECOMMENDATION_PATTERN,
    wordMatcher
} from './vocabulary.js';
import {
    buildChips,
    buildRecommendationReply,
    emptyResultResponse,
    findProductSuggestions,
    formatProductList,
    recommendationHeading
} from './recommend.js';
import { interpretMessage } from './interpret.js';
import { generateLeadIn, generateStoreAnswer } from './ground.js';
import { Deadline, INTERPRET_BUDGET_MS, INTERPRET_TIMEOUT_MS, PER_CALL_TIMEOUT_MS, REQUEST_BUDGET_MS } from './llm.js';

const NO_PRODUCTS_REPLY = "I couldn't find any products in stock to recommend right now. Please check our [catalog](/products) for the latest items.";

const CONTACT_FALLBACK = "I don't have that in my store information. Please email **ubap@clsu.edu.ph** or visit the UBAP Office, and they can help you directly.";

// What a message gets when the model could not read it and the shopper's own
// words give no sign of a shopping request either (a pasted story, small talk).
// It states the scope rather than guessing at a product list.
const NOT_A_SHOPPING_REQUEST_REPLY = "I'm not sure what you're asking. I can help you find merchandise (try \"jackets under ₱500\" or \"what do you sell?\") and answer questions about ordering, pickup and payment.";

const MAX_KEYWORD_FALLBACK_WORDS = 30;

// Only what the widget is allowed to send: a bounded list of slugs and one
// bounded query string. Anything else is dropped, since this endpoint is open to
// guests and both values end up in matching logic.
export function readConversationContext(body) {
    const shown = Array.isArray(body?.shown)
        ? body.shown.filter(s => typeof s === 'string' && s.length > 0 && s.length <= 255).slice(-100)
        : [];
    const lastQuery = typeof body?.last_query === 'string' ? body.last_query.trim().slice(0, 100) : '';
    return { shown, lastQuery };
}

// The widget's own chips and starter questions are strings this service wrote,
// so their meaning is already known. Reading them with the model would add a
// round trip to a question we can answer from the keywords immediately.
const CHIP_PATTERNS = [
    /^show me more$/i,
    /^show me .+$/i,
    /^under ₱\s*\d+$/i,
    /^recommend products under ₱\s*\d+$/i
];
const isChipMessage = (text) => CHIP_PATTERNS.some(p => p.test(text.trim()));

// Products the shop lists but has none of, for the concepts the model read.
// findProductSuggestions works this out for itself from itemHits, but the
// semantic path matches descriptions in SQL and so has no itemHits to give it;
// the same distinction still has to be drawn, because "we list that but it ran
// out" is a different answer from "nothing matched".
function outOfStockFor(concepts, inStockProducts, shop, categoryId) {
    if (concepts.length === 0) return [];
    const inStock = new Set(inStockProducts.map(p => p.slug));
    return shop.catalogue
        .filter(p => !inStock.has(p.slug)
            && (categoryId === null || Number(p.category_id) === Number(categoryId))
            && concepts.some(c => wordMatcher(c).test(p.name)))
        .map(p => p.name)
        .slice(0, 3);
}

// Everything a shopper said about the item, in the shape findProductSuggestions
// expects. The budget and the category are re-enforced here in backend code
// even though SQL already applied them: the model chose the words, so the limit
// a customer stated has to be checked against a database row, not trusted.
function criteriaFromInterpretation(interpretation, { outOfStock, message }) {
    const { budget, category, concepts, variantPreference } = interpretation;
    const lower = message.toLowerCase();

    // What to call the request back to the shopper when nothing matched. The
    // size belongs in it: "no jacket in Large" is a different piece of news
    // from "no jacket".
    const what = concepts.length > 0 ? concepts.join(' / ') : (category ? category.name : '');
    const label = what && variantPreference ? `${what} in ${variantPreference}` : what;

    // The model's budget is preferred because it reads Taglish; the regex is the
    // backstop for a reply that left it out.
    const fallbackBudget = parseBudget(lower);
    const amount = budget?.amount ?? fallbackBudget.amount;
    const isCap = budget ? budget.isCap : fallbackBudget.isCap;

    return {
        amount,
        isCap,
        categoryHits: category ? [category] : [],
        itemHits: [],
        conceptsAsked: concepts.length > 0 || category !== null,
        askedLabels: label ? [label] : [],
        wantsCheapest: CHEAPEST_PATTERN.test(lower),
        outOfStock
    };
}

// A size or colour the shopper named, checked against real variant rows rather
// than against the product's name. A product whose only in-stock variants are
// Small does not satisfy "size Large", and dropping it here is the difference
// between a recommendation a customer can act on and one they cannot.
async function keepProductsWithVariant(picks, preference, catalog, log) {
    if (!preference || picks.length === 0) return picks;

    const variantsBySlug = await catalog.fetchVariantsFor(picks.map(p => p.slug).filter(Boolean));
    if (variantsBySlug.size === 0) return picks;

    const matcher = wordMatcher(preference);
    const kept = picks.filter(p => {
        // A product sold without variants has no size or colour to contradict,
        // so the preference simply does not apply to it.
        if (!Number(p.has_variants)) return true;
        const variants = variantsBySlug.get(p.slug) || [];
        return variants.some(v => matcher.test(String(v.name)));
    });

    // Returning the unfiltered picks when nothing matched would answer "do you
    // have this in Large?" with products that only come in Small. An empty
    // result is the honest answer, and the caller already has wording for it.
    if (kept.length === 0) {
        log.warn(`No in-stock variant matched "${preference}".`);
    }
    return kept;
}

// The reply for a set of picks: the model's sentence when it passed every
// check, the deterministic heading when it did not, and in both cases the
// product list printed from the database rows.
async function composeProductReply({ message, products, result, freshOnly, shop, deps, deadline }) {
    const leadIn = deps.llm
        ? await generateLeadIn({
            llm: deps.llm,
            message,
            picks: result.picks,
            catalogue: shop.catalogue,
            deadline,
            timeoutMs: PER_CALL_TIMEOUT_MS,
            log: deps.log
        })
        : null;

    return {
        response: `${leadIn ?? recommendationHeading(result, freshOnly)}\n\n${formatProductList(result.picks)}`,
        products: result.picks.map(p => p.slug).filter(Boolean),
        query: message,
        chips: buildChips(products, result)
    };
}

// The recommendation helpers return their internal result for the pipeline to
// read; it must not travel to the browser, which only knows the four keys the
// widget reads.
const toWireReply = ({ response, products, query, chips }) => ({ response, products, query, chips });

/**
 * @param {{message: string, shown: string[], lastQuery: string}} request
 * @param {{catalog: object, llm: object|null, log?: object, deadline?: Deadline}} deps
 * @returns {Promise<{response: string, products?: string[], query?: string, chips?: string[]}>}
 */
export async function handleChat(request, deps) {
    const { message } = request;
    const { shown, lastQuery } = request;
    const log = deps.log ?? console;
    const deadline = deps.deadline ?? new Deadline(REQUEST_BUDGET_MS);
    const catalog = deps.catalog;

    const msgLower = normalizeForMatching(message);

    // 1. Published FAQ, greetings, payment, ordering, order status, ... answered
    // before any model call, so they are instant and identical every time.
    const faqEntry = findFaqEntry(message);
    const faqIsPolicy = faqEntry !== null && !CONVERSATIONAL_FAQ_IDS.has(faqEntry.id);

    // The shop's own words, needed to tell a pure policy question from one that
    // also asks about products. Never throws: with no database it comes back
    // empty and only category matching is lost.
    const shop = await catalog.loadShop().catch(error => {
        log.error('Shop load failed:', error.message);
        return EMPTY_SHOP;
    });

    // "Do you have jackets, and do you accept GCash?" used to be answered with
    // the payment paragraph alone: the FAQ matched first and the jackets half was
    // dropped. A policy answer is now only the whole reply when the message asks
    // nothing about products.
    const alsoAsksProducts = mentionsCatalogTerm(msgLower, shop);
    if (faqEntry && !(faqIsPolicy && alsoAsksProducts)) {
        return { response: faqEntry.answer };
    }
    let storePart = faqIsPolicy && alsoAsksProducts ? faqEntry.answer : null;

    // 2. Off-Topic Check
    if (isIrrelevantQuery(message)) {
        return { response: STANDARD_REFUSAL };
    }

    // 3. The in-stock catalogue. A database failure is reported as exactly that:
    // saying "nothing is in stock" would be a claim about the shop that we have
    // no basis for.
    let dbProducts;
    try {
        dbProducts = await catalog.fetchAvailableProducts();
    } catch (error) {
        if (error instanceof CatalogUnavailableError) {
            return { response: CATALOG_UNAVAILABLE_MESSAGE };
        }
        throw error;
    }

    const hasCatalog = Array.isArray(dbProducts) && dbProducts.length > 0;
    const context = { shown, lastQuery };

    // 4a. "Show me more" / "anything else?" after a recommendation continues
    // that recommendation with items not shown yet. It has no keyword of its
    // own, so without this it fell through to the model and got the
    // "temporarily unavailable" reply whenever the free models were down.
    //
    // The request it continues is the earlier one, re-read the same way: a
    // "more" after "something warm for the rainy season" has to stay a search
    // for warm things, and the words of "show me more" say nothing about warmth.
    const freshOnly = isMoreRequest(msgLower, context);
    const effectiveQuery = freshOnly && !queryHasCriteria(msgLower, shop) ? context.lastQuery : message;

    if (freshOnly && !hasCatalog) {
        return { response: NO_PRODUCTS_REPLY };
    }

    // 4b. A chip the widget itself offered. Its wording came from this service,
    // so the keyword rules already know what it means and a model round trip
    // would only add latency.
    if (!freshOnly && isChipMessage(message) && queryHasCriteria(msgLower, shop)) {
        if (!hasCatalog) return { response: NO_PRODUCTS_REPLY };
        return toWireReply(buildRecommendationReply(message, dbProducts, { shown: context.shown, shop }));
    }

    // 5. Read the question. This is where a paraphrase ("something warm for the
    // rainy season") and a Taglish budget ("hindi lalagpas sa 500") are turned
    // into concepts, a category and a cap. The broad RECOMMENDATION_PATTERN no
    // longer decides on its own: it is the fallback for when this fails, not
    // the gate in front of it.
    const interpreted = deps.llm
        ? await interpretMessage({
            llm: deps.llm,
            message: effectiveQuery,
            categories: shop.categories,
            // Its own sub-budget: reading the question must not be able to
            // spend the time answering it needs.
            deadline: deadline.child(INTERPRET_BUDGET_MS),
            timeoutMs: INTERPRET_TIMEOUT_MS,
            log
        })
        : { ok: false, reason: 'disabled' };

    if (interpreted.ok && interpreted.interpretation.intent === 'off_topic' && !storePart) {
        return { response: STANDARD_REFUSAL };
    }

    // 6. A question about how the store works that the FAQ table did not cover.
    if (interpreted.ok && interpreted.interpretation.intent === 'store_question' && !storePart) {
        const answer = await generateStoreAnswer({
            llm: deps.llm,
            question: interpreted.interpretation.storeQuestion || message,
            deadline,
            timeoutMs: PER_CALL_TIMEOUT_MS,
            log
        });
        return { response: answer ?? CONTACT_FALLBACK };
    }

    // The policy half of a mixed question that no FAQ entry matched. Without
    // this the half the shopper asked about the store is simply dropped and
    // they get a product list answering the other half.
    if (interpreted.ok
        && interpreted.interpretation.intent === 'mixed'
        && !storePart
        && interpreted.interpretation.storeQuestion) {
        storePart = await generateStoreAnswer({
            llm: deps.llm,
            question: interpreted.interpretation.storeQuestion,
            deadline,
            timeoutMs: PER_CALL_TIMEOUT_MS,
            log
        }) ?? CONTACT_FALLBACK;
    }

    // 7. Products. Either from what the model read, or -- when it could not be
    // reached, replied with nonsense, or the budget ran out -- from the keyword
    // rules that have always answered this.
    if (!hasCatalog) {
        return { response: storePart ? `${storePart}\n\n${NO_PRODUCTS_REPLY}` : NO_PRODUCTS_REPLY };
    }

    let reply;

    const interpretation = interpreted.ok ? interpreted.interpretation : null;
    const usableConcepts = interpretation
        ? interpretation.concepts.filter(c => c.length >= 2)
        : [];
    const wantsSemanticSearch = interpretation !== null
        && (usableConcepts.length > 0 || interpretation.category !== null);

    if (wantsSemanticSearch) {
        const categoryId = interpretation.category ? Number(interpretation.category.id) : null;

        // Widened through ITEM_GROUPS: the model answers with the generic word
        // for a kind of item, and product names almost never contain it.
        const searchTerms = expandConcepts(usableConcepts);

        let retrieved;
        try {
            retrieved = await catalog.searchProducts(searchTerms, { categoryId });
        } catch (error) {
            if (error instanceof CatalogUnavailableError) {
                return { response: CATALOG_UNAVAILABLE_MESSAGE };
            }
            throw error;
        }

        // Nothing in SQL matched any of the words, widened included. That is a
        // vocabulary miss rather than an empty shop, and the keyword rules read
        // the shopper's own words instead of the model's paraphrase of them --
        // so they get the question back rather than the shopper getting a "we
        // don't have that" for something sitting in stock.
        if (retrieved.length === 0) {
            const keyword = toWireReply(buildRecommendationReply(effectiveQuery, dbProducts, { shown: context.shown, freshOnly, shop }));
            return storePart ? { ...keyword, response: `${storePart}\n\n${keyword.response}` } : keyword;
        }

        const criteria = criteriaFromInterpretation(interpretation, {
            outOfStock: outOfStockFor(expandConcepts(usableConcepts), retrieved, shop, categoryId),
            message: effectiveQuery
        });

        const result = findProductSuggestions(effectiveQuery, retrieved, {
            shown: context.shown,
            freshOnly,
            popular: BEST_SELLER_PATTERN.test(effectiveQuery.toLowerCase()),
            shop,
            criteria
        });

        result.picks = await keepProductsWithVariant(result.picks, interpretation.variantPreference, catalog, log);

        if (result.picks.length > 0) {
            reply = await composeProductReply({ message: effectiveQuery, products: dbProducts, result, freshOnly, shop, deps, deadline });
        } else {
            // Built from THIS result, not by re-running the search. Recomputing
            // here silently dropped the variant filter above, so "in Large" was
            // answered with the products that have no Large left.
            reply = {
                response: emptyResultResponse(result, { freshOnly, popular: result.meta.popular }),
                products: [],
                query: effectiveQuery,
                chips: buildChips(dbProducts, { remaining: 0, meta: { ...result.meta, categoryAsked: false } })
            };
        }
    } else {
        // No usable reading of the message, so the keyword rules answer. They
        // may only do so for something that sounds like shopping: a vague request
        // is read as "show me anything", which turned a pasted story into three
        // product links whenever the free model was rate-limited. When the model
        // DID read it (interpretation !== null) its verdict stands, so a genuine
        // "recommend something" with no concepts still gets a list.
        //
        // A keyword alone is not enough: any long prose contains "under", "item"
        // or "bag" somewhere, so a message past MAX_KEYWORD_FALLBACK_WORDS is
        // never read as a shopping request without the model's say-so.
        const wordCount = effectiveQuery.trim().split(/\s+/).length;
        const soundsLikeShopping = wordCount <= MAX_KEYWORD_FALLBACK_WORDS
            && (queryHasCriteria(msgLower, shop) || RECOMMENDATION_PATTERN.test(effectiveQuery));

        if (interpretation === null && !freshOnly && !soundsLikeShopping) {
            return { response: storePart ?? NOT_A_SHOPPING_REQUEST_REPLY };
        }

        reply = toWireReply(buildRecommendationReply(effectiveQuery, dbProducts, { shown: context.shown, freshOnly, shop }));
    }

    // 8. A mixed question keeps both halves: the policy answer first, because it
    // is what the shopper asked about themselves, then what we have in stock.
    if (storePart) {
        return { ...reply, response: `${storePart}\n\n${reply.response}` };
    }

    return reply;
}

/**
 * The same pipeline with the last-resort guard the HTTP layer needs: an
 * unexpected throw becomes the friendly message rather than a 500 the widget
 * renders as "AI Connection Failed".
 */
export async function handleChatSafely(request, deps) {
    try {
        return await handleChat(request, deps);
    } catch (error) {
        (deps.log ?? console).error('Chat pipeline failed:', error);
        return { response: FRIENDLY_ERROR_MESSAGE };
    }
}
