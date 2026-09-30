// The service's real SQL, run against an in-memory SQLite database. These are
// the claims the rest of the system trusts: that a price quoted is a price
// someone can pay, and that nothing hidden from the storefront is recommended.

import test from 'node:test';
import assert from 'node:assert/strict';

import { CatalogUnavailableError, createCatalog, escapeLike } from '../lib/catalog.js';
import { seedShop, silentLog, sqliteAdapter } from './helpers.js';

const openCatalog = () => createCatalog(sqliteAdapter(seedShop()), { log: silentLog });

const bySlug = (rows) => Object.fromEntries(rows.map(r => [r.slug, r]));

test('a sold-out cheap variant does not set the price a product is offered at', async () => {
    const rows = bySlug(await openCatalog().fetchAvailableProducts());

    // Small is ₱350 but has no stock; XL at ₱800 is the only one left.
    assert.equal(Number(rows['clsu-windbreaker-jacket'].price), 800);
    assert.equal(Number(rows['clsu-windbreaker-jacket'].stock_quantity), 4);
});

test('the cheapest IN-STOCK variant sets the price', async () => {
    const rows = bySlug(await openCatalog().fetchAvailableProducts());

    // Medium ₱480 is in stock, Large ₱520 is not, Hidden ₱100 is inactive.
    assert.equal(Number(rows['clsu-varsity-jacket'].price), 480);
    assert.equal(Number(rows['clsu-varsity-jacket'].stock_quantity), 6);
});

test('products with no stock at all are left out of the available list', async () => {
    const rows = bySlug(await openCatalog().fetchAvailableProducts());

    assert.ok(!('clsu-rain-poncho' in rows), 'a sold-out product is not available');
});

test('soft-deleted, inactive, and inactive-category products are never available', async () => {
    const rows = bySlug(await openCatalog().fetchAvailableProducts());

    assert.ok(!('deleted-tee' in rows), 'soft-deleted product leaked');
    assert.ok(!('inactive-mug' in rows), 'inactive product leaked');
    assert.ok(!('archived-cap' in rows), 'product in an inactive category leaked');
});

test('the full catalogue lists sold-out products, because they can still be asked for', async () => {
    const catalogue = await openCatalog().fetchCatalogue();
    const names = catalogue.map(p => p.name);

    assert.ok(names.includes('CLSU Rain Poncho'));
    assert.ok(!names.includes('Deleted Tee'));
    assert.ok(!names.includes('Archived Cap'));
});

test('only active categories are offered', async () => {
    const names = (await openCatalog().fetchActiveCategories()).map(c => c.name);

    assert.deepEqual(names.sort(), ['Apparel', 'Stationery']);
});

test('search matches a product description, not only its name', async () => {
    // "warm" appears in the hoodie's short_description and in no product name.
    const found = await openCatalog().searchProducts(['warm']);

    assert.deepEqual(found.map(p => p.slug), ['clsu-fleece-hoodie']);
});

test('search can be narrowed to one category', async () => {
    const catalog = openCatalog();

    const everywhere = await catalog.searchProducts(['clsu']);
    const apparelOnly = await catalog.searchProducts(['clsu'], { categoryId: 1 });

    assert.ok(everywhere.some(p => p.slug === 'clsu-tote-bag'), 'the tote is in Stationery');
    assert.ok(!apparelOnly.some(p => p.slug === 'clsu-tote-bag'), 'the category filter was not applied');
    assert.ok(apparelOnly.every(p => Number(p.category_id) === 1));
});

test('search applies the same in-stock variant price as the full listing', async () => {
    const found = bySlug(await openCatalog().searchProducts(['jacket']));

    assert.equal(Number(found['clsu-windbreaker-jacket'].price), 800);
    assert.equal(Number(found['clsu-varsity-jacket'].price), 480);
});

test('search terms are bound as values, never spliced into the statement', async () => {
    const seen = [];
    const catalog = createCatalog({
        async query(sql, params) {
            seen.push({ sql, params });
            return [[]];
        }
    }, { log: silentLog });

    await catalog.searchProducts(["'; DROP TABLE products; --"], { categoryId: 7 });

    const { sql, params } = seen[0];
    assert.ok(!sql.includes('DROP TABLE'), 'the term reached the SQL text');
    assert.ok(params.includes("%'; DROP TABLE products; --%"), 'the term was not bound as a parameter');
    assert.ok(params.includes(7), 'the category id was not bound as a parameter');
});

test('LIKE wildcards a customer typed are escaped instead of widening the search', () => {
    assert.equal(escapeLike('100% cotton'), '100\\% cotton');
    assert.equal(escapeLike('a_b'), 'a\\_b');
});

test('variants are returned only when active and in stock', async () => {
    const variants = await openCatalog().fetchVariantsFor(['clsu-varsity-jacket']);

    assert.deepEqual(
        variants.get('clsu-varsity-jacket').map(v => v.name).sort(),
        ['Medium'],
        'a sold-out or inactive variant was offered as choosable'
    );
});

test('an unreachable database raises rather than reporting an empty shop', async () => {
    const catalog = createCatalog({
        async query() {
            const error = new Error('connect ECONNREFUSED');
            error.code = 'ECONNREFUSED';
            throw error;
        }
    }, { log: silentLog });

    await assert.rejects(() => catalog.fetchAvailableProducts(), CatalogUnavailableError);
});

test('a schema with no sales or review tables still returns the basic catalogue', async () => {
    let attempt = 0;
    const catalog = createCatalog({
        async query(sql) {
            attempt++;
            if (sql.includes('units_sold')) throw Object.assign(new Error('no such table: order_items'), { code: 'ER_NO_SUCH_TABLE' });
            return [[{ slug: 'ubap-notebook', price: 120 }]];
        }
    }, { log: silentLog });

    const rows = await catalog.fetchAvailableProducts();

    assert.equal(attempt, 2, 'the basic query should be the second attempt');
    assert.deepEqual(rows, [{ slug: 'ubap-notebook', price: 120 }]);
});
