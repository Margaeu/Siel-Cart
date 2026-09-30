// Test doubles. Two of them are real implementations rather than mocks:
//
//   - the catalogue runs the service's own SQL against an in-memory SQLite
//     database, so a query change is checked by an engine and not by a string
//     comparison. The sold-out-variant rule in particular is only meaningful
//     when something actually executes the aggregate.
//   - the language model is a queue of canned replies, so every test states
//     exactly what the model said and no test reaches the network.

import { DatabaseSync } from 'node:sqlite';

/**
 * mysql2's `query(sql, params) -> [rows, fields]` over SQLite, including the
 * array expansion mysql2 does for `IN (?)`.
 */
export function sqliteAdapter(db) {
    return {
        async query(sql, params = []) {
            let text = sql;
            const flat = [];

            for (const param of params) {
                if (Array.isArray(param)) {
                    text = text.replace('?', param.map(() => '?').join(', '));
                    flat.push(...param);
                } else {
                    flat.push(param);
                }
            }

            return [db.prepare(text).all(...flat)];
        }
    };
}

const SCHEMA = `
CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT, is_active INTEGER);
CREATE TABLE products (
    id INTEGER PRIMARY KEY, category_id INTEGER, name TEXT, slug TEXT,
    short_description TEXT, description TEXT, price REAL, stock_quantity INTEGER,
    is_active INTEGER, has_variants INTEGER, deleted_at TEXT
);
CREATE TABLE product_variants (
    id INTEGER PRIMARY KEY, product_id INTEGER, name TEXT, price REAL,
    stock_quantity INTEGER, is_active INTEGER
);
CREATE TABLE orders (id INTEGER PRIMARY KEY, status TEXT, deleted_at TEXT);
CREATE TABLE order_items (id INTEGER PRIMARY KEY, order_id INTEGER, product_id INTEGER, quantity INTEGER);
CREATE TABLE reviews (id INTEGER PRIMARY KEY, product_id INTEGER, rating INTEGER, is_approved INTEGER);
`;

/**
 * A small shop that exercises every rule the pipeline has to get right:
 *
 *   - "CLSU Windbreaker Jacket" has a SOLD OUT ₱350 Small and an in-stock ₱800
 *     XL. Its real price is ₱800, so it must NOT answer "jacket under ₱500".
 *   - "CLSU Varsity Jacket" has an in-stock ₱480 Medium, so it must.
 *   - "CLSU Rain Poncho" is listed but has no stock: asking for it is answered
 *     "out of stock", not "we don't sell that".
 *   - the last three rows are invisible to the storefront (soft-deleted,
 *     inactive, and in an inactive category) and must never be recommended.
 */
export function seedShop() {
    const db = new DatabaseSync(':memory:');
    db.exec(SCHEMA);

    db.exec(`
        INSERT INTO categories (id, name, is_active) VALUES
            (1, 'Apparel', 1),
            (2, 'Stationery', 1),
            (3, 'Archived Stock', 0);

        INSERT INTO products (id, category_id, name, slug, short_description, description, price, stock_quantity, is_active, has_variants, deleted_at) VALUES
            (1, 1, 'CLSU Windbreaker Jacket', 'clsu-windbreaker-jacket', 'Lightweight outer layer', 'A light jacket in university colours.', NULL, 0, 1, 1, NULL),
            (2, 1, 'CLSU Fleece Hoodie', 'clsu-fleece-hoodie', 'Warm fleece layer for cool evenings', 'Brushed fleece hoodie.', 450, 12, 1, 0, NULL),
            (3, 1, 'CLSU Rain Poncho', 'clsu-rain-poncho', 'Packable cover', 'A packable poncho.', 250, 0, 1, 0, NULL),
            (4, 2, 'UBAP Notebook', 'ubap-notebook', 'Ruled 80 leaves', 'A ruled notebook.', 120, 30, 1, 0, NULL),
            (5, 2, 'CLSU Tote Bag', 'clsu-tote-bag', 'Canvas tote', 'A canvas tote bag.', 180, 8, 1, 0, NULL),
            (9, 1, 'CLSU Varsity Jacket', 'clsu-varsity-jacket', 'Classic varsity cut', 'A varsity jacket.', NULL, 0, 1, 1, NULL),
            (6, 1, 'Deleted Tee', 'deleted-tee', NULL, NULL, 100, 5, 1, 0, '2026-01-01 00:00:00'),
            (7, 1, 'Inactive Mug', 'inactive-mug', NULL, NULL, 90, 5, 0, 0, NULL),
            (8, 3, 'Archived Cap', 'archived-cap', NULL, NULL, 100, 5, 1, 0, NULL);

        INSERT INTO product_variants (id, product_id, name, price, stock_quantity, is_active) VALUES
            (1, 1, 'Small',  350, 0, 1),
            (2, 1, 'XL',     800, 4, 1),
            (3, 9, 'Medium', 480, 6, 1),
            (4, 9, 'Large',  520, 0, 1),
            (5, 9, 'Hidden', 100, 9, 0);

        INSERT INTO orders (id, status, deleted_at) VALUES (1, 'completed', NULL);
        INSERT INTO order_items (id, order_id, product_id, quantity) VALUES (1, 1, 4, 7);
        INSERT INTO reviews (id, product_id, rating, is_approved) VALUES (1, 4, 5, 1);
    `);

    return db;
}

/**
 * A language model that says exactly what a test tells it to.
 *
 * `replies` is consumed in order. An entry may be a string (the completion), an
 * Error (thrown, so the fallback chain is exercised), or a function of the
 * request. Running out of entries throws, which is how a test asserts that no
 * further model call was made.
 */
export function fakeLlm(replies = []) {
    const queue = [...replies];
    const calls = [];

    return {
        calls,
        get remaining() {
            return queue.length;
        },
        async complete(request) {
            calls.push(request);

            if (queue.length === 0) {
                throw new Error('fakeLlm: no reply queued for this call');
            }

            const next = queue.shift();
            if (next instanceof Error) throw next;
            if (typeof next === 'function') return next(request);
            return next;
        }
    };
}

// A model that is simply unreachable, however many times it is called.
export const deadLlm = () => ({
    calls: [],
    async complete() {
        throw new Error('503 Service Unavailable');
    }
});

export const silentLog = { warn() {}, error() {}, log() {} };

// A deadline that never runs out, so a test measures behaviour rather than the
// clock. llm.js refuses to start a call with under a second left.
export const generousDeadline = () => ({
    remaining: () => 60000,
    expired: () => false,
    slice: (preferred) => preferred,
    child() { return generousDeadline(); }
});

export const expiredDeadline = () => ({
    remaining: () => 0,
    expired: () => true,
    slice: () => 0,
    child() { return expiredDeadline(); }
});

// The JSON an interpretation call is expected to return.
export const interpretation = (fields) => JSON.stringify({
    intent: 'product_search',
    concepts: [],
    category: null,
    budget: null,
    variant_preference: null,
    store_question: null,
    ...fields
});
