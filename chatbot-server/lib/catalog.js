// Every read of the shop's data. The query structure is written here and only
// values are ever bound, so nothing a customer or a language model produces can
// change the shape of a statement.

import { nameVocabulary } from './vocabulary.js';

// Thrown when MySQL itself could not answer. The pipeline turns this into
// CATALOG_UNAVAILABLE_MESSAGE, which is a different answer from "nothing
// matched" and from "that is sold out": with no catalogue we do not know which
// of those is true.
export class CatalogUnavailableError extends Error {
    constructor(cause) {
        super(`Catalog unavailable: ${cause?.code || '(no code)'} - ${cause?.message || cause}`);
        this.name = 'CatalogUnavailableError';
        this.cause = cause;
    }
}

// Variant-based products (has_variants = 1) keep their real price and stock on
// product_variants, not on the products row itself -- p.price is NULL and
// p.stock_quantity is unused for those. Pull the lowest active-variant price and
// total active-variant stock for them, and fall back to the product's own
// columns otherwise.
//
// The MIN is taken over variants that are IN STOCK, not merely active. With a
// plain MIN(pv.price) a shirt whose ₱350 Small is sold out and whose ₱800 XL is
// the only one left was advertised at ₱350 and matched a "under ₱500" request,
// so the recommendation was for a price nobody could actually buy it at. The
// HAVING keeps such a product only when some variant still has stock, and the
// price then quoted is the cheapest one a customer can really pick.
const PRICE_EXPRESSION = `CASE WHEN p.has_variants = 1
            THEN MIN(CASE WHEN pv.stock_quantity > 0 THEN pv.price END)
            ELSE MAX(p.price) END`;

const STOCK_EXPRESSION = `CASE WHEN p.has_variants = 1
            THEN COALESCE(SUM(pv.stock_quantity), 0)
            ELSE MAX(p.stock_quantity) END`;

const AVAILABLE_PRODUCTS_SQL = (extraColumns, extraWhere = '') => `SELECT
        p.name,
        p.slug,
        p.has_variants,
        p.short_description,
        c.id AS category_id,
        c.name AS category_name,
        ${PRICE_EXPRESSION} AS price,
        ${STOCK_EXPRESSION} AS stock_quantity
        ${extraColumns}
     FROM products p
     JOIN categories c ON c.id = p.category_id AND c.is_active = 1
     LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
     WHERE p.is_active = 1 AND p.deleted_at IS NULL
     ${extraWhere}
     GROUP BY p.id, p.name, p.slug, p.has_variants, p.short_description, c.id, c.name
     HAVING ${STOCK_EXPRESSION} > 0 AND ${PRICE_EXPRESSION} IS NOT NULL`;
// The HAVING repeats the expressions instead of naming the select aliases.
// `stock_quantity` is a real column on both products and product_variants, and
// only MySQL resolves the alias ahead of them -- everywhere else the same
// statement is an "ambiguous column name" error.

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

// The variant rows behind one product, so a size or colour preference can be
// checked against what is actually on the shelf rather than guessed from the
// product's name. Only active variants with stock: an inactive or sold-out
// variant is not something a customer can choose.
const VARIANTS_SQL = `SELECT p.slug, pv.name, pv.price, pv.stock_quantity
     FROM product_variants pv
     JOIN products p ON p.id = pv.product_id
     WHERE pv.is_active = 1
       AND pv.stock_quantity > 0
       AND p.is_active = 1
       AND p.deleted_at IS NULL
       AND p.slug IN (?)`;

export function createCatalog(db, { log = console } = {}) {
    // The categories the storefront lets customers browse to. Read from the database
    // on every request, like the products, so a category added, renamed or switched
    // off in the admin is reflected here without touching this file. Empty on a
    // failure: item words ("jacket") still work, only category names stop matching.
    async function fetchActiveCategories() {
        try {
            const [rows] = await db.query('SELECT id, name FROM categories WHERE is_active = 1');
            return rows;
        } catch (dbError) {
            log.error('Category fetch error:', dbError.code || '(no code)', '-', dbError.message);
            return [];
        }
    }

    // Every product the storefront lists, in stock or not: the names a shopper may
    // ask for. Same visibility rules as the stock query above (active, not deleted,
    // in an active category) but without the stock filter.
    async function fetchCatalogue() {
        try {
            const [rows] = await db.query(
                `SELECT p.id, p.name, p.slug, p.category_id
                   FROM products p
                   JOIN categories c ON c.id = p.category_id AND c.is_active = 1
                  WHERE p.is_active = 1 AND p.deleted_at IS NULL`
            );
            return rows;
        } catch (dbError) {
            log.error('Catalogue fetch error:', dbError.code || '(no code)', '-', dbError.message);
            return [];
        }
    }

    async function fetchAvailableProducts() {
        try {
            try {
                const [rows] = await db.query(AVAILABLE_PRODUCTS_SQL(POPULARITY_COLUMNS));
                return rows;
            } catch (richError) {
                // A schema without the sales/review tables must not take the whole
                // catalogue down with it: recommend from the basic columns instead.
                log.warn('Popularity query failed, using basic catalogue:', richError.code || '(no code)', '-', richError.message);
                const [rows] = await db.query(AVAILABLE_PRODUCTS_SQL(''));
                return rows;
            }
        } catch (dbError) {
            // Logged with the driver's error code (e.g. ECONNREFUSED,
            // ER_ACCESS_DENIED_ERROR, HANDSHAKE_SSL_ERROR) because the message
            // alone doesn't distinguish "wrong host/credentials" from "TLS
            // verification failed" -- both used to fall back to the same stub
            // product list, so this log is the only way to tell which.
            //
            // Raised rather than returned as an empty list: an unreachable
            // database is not the same answer as an empty shop, and the two used
            // to be indistinguishable to the caller.
            log.error('Database fetch error:', dbError.code || '(no code)', '-', dbError.message);
            throw new CatalogUnavailableError(dbError);
        }
    }

    // Products whose name or description carries any of `terms`, optionally
    // inside one category. The statement's shape is fixed here; every term goes
    // in as a bound LIKE value, so a concept the model invented can only ever be
    // a search string. No model-written SQL is executed anywhere.
    async function searchProducts(terms, { categoryId = null } = {}) {
        const cleaned = terms.filter(t => typeof t === 'string' && t.trim() !== '');
        if (cleaned.length === 0 && categoryId === null) return [];

        const clauses = [];
        const params = [];

        if (cleaned.length > 0) {
            const perTerm = cleaned
                .map(() => '(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)')
                .join(' OR ');
            clauses.push(`(${perTerm})`);
            for (const term of cleaned) {
                const like = `%${escapeLike(term)}%`;
                params.push(like, like, like);
            }
        }

        if (categoryId !== null) {
            clauses.push('c.id = ?');
            params.push(categoryId);
        }

        try {
            const where = `AND ${clauses.join(' AND ')}`;
            const [rows] = await db.query(AVAILABLE_PRODUCTS_SQL(POPULARITY_COLUMNS, where), params);
            return rows;
        } catch (richError) {
            log.warn('Semantic search with popularity failed, retrying basic:', richError.code || '(no code)', '-', richError.message);
            try {
                const where = `AND ${clauses.join(' AND ')}`;
                const [rows] = await db.query(AVAILABLE_PRODUCTS_SQL('', where), params);
                return rows;
            } catch (dbError) {
                log.error('Semantic search error:', dbError.code || '(no code)', '-', dbError.message);
                throw new CatalogUnavailableError(dbError);
            }
        }
    }

    // Active, in-stock variants for the given product slugs, grouped by slug, so
    // a "size Large" preference is checked against real rows.
    async function fetchVariantsFor(slugs) {
        if (!Array.isArray(slugs) || slugs.length === 0) return new Map();
        try {
            const [rows] = await db.query(VARIANTS_SQL, [slugs]);
            const bySlug = new Map();
            for (const row of rows) {
                if (!bySlug.has(row.slug)) bySlug.set(row.slug, []);
                bySlug.get(row.slug).push(row);
            }
            return bySlug;
        } catch (dbError) {
            // A variant lookup is an enhancement on top of a recommendation that
            // already stands: losing it must not lose the recommendation.
            log.warn('Variant fetch error:', dbError.code || '(no code)', '-', dbError.message);
            return new Map();
        }
    }

    // Everything the vocabulary above is built from, fetched fresh per request.
    async function loadShop() {
        const [categories, catalogue] = await Promise.all([fetchActiveCategories(), fetchCatalogue()]);
        return { categories, catalogue, extraGroups: nameVocabulary(catalogue) };
    }

    return { fetchActiveCategories, fetchCatalogue, fetchAvailableProducts, searchProducts, fetchVariantsFor, loadShop };
}

// A customer may type "50%" or "a_b". Left unescaped those are LIKE wildcards
// and the search silently widens to everything.
export const escapeLike = (term) => String(term).replace(/[\\%_]/g, '\\$&');
