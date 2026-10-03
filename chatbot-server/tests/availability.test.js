// "Is the hoodie in stock?" is a question about the shelf. These pin down that
// it is answered from the stock rows -- per product, per size -- instead of the
// FAQ's generic sentence plus a recommendation list, and that no model is
// needed to do it.

import test from 'node:test';
import assert from 'node:assert/strict';

import { CatalogUnavailableError, createCatalog } from '../lib/catalog.js';
import { handleChat } from '../lib/pipeline.js';
import { optionLabel, sizesMentioned } from '../lib/availability.js';
import { fakeLlm, generousDeadline, seedShop, silentLog, sqliteAdapter } from './helpers.js';

// The seed shop plus a hoodie sold in sizes: Small gone, Medium and Large left,
// and a Dark Green hoodie that is sold out entirely.
function shopWithHoodies() {
    const db = seedShop();
    db.exec(`
        INSERT INTO products (id, category_id, name, slug, short_description, description, price, stock_quantity, is_active, has_variants, deleted_at) VALUES
            (20, 1, 'CLSU Athletes Hoodie', 'clsu-athletes-hoodie', NULL, NULL, NULL, 0, 1, 1, NULL),
            (21, 1, 'Honor Hoodie', 'honor-hoodie', NULL, NULL, NULL, 0, 1, 1, NULL);
        INSERT INTO product_variants (id, product_id, name, price, stock_quantity, is_active, sort_order) VALUES
            (20, 20, 'CLSU Athletes Hoodie - Small',  899, 0, 1, 0),
            (21, 20, 'CLSU Athletes Hoodie - Medium', 899, 3, 1, 1),
            (22, 20, 'CLSU Athletes Hoodie - Large',  899, 1, 1, 2),
            (23, 21, 'Honor Hoodie - Small', 750, 0, 1, 0),
            (24, 21, 'Honor Hoodie - Large', 750, 9, 1, 1);
    `);
    return db;
}

function ask(message, db = shopWithHoodies()) {
    const llm = fakeLlm([]);
    return handleChat(
        { message, shown: [], lastQuery: '' },
        { catalog: createCatalog(sqliteAdapter(db), { log: silentLog }), llm, log: silentLog, deadline: generousDeadline() }
    ).then(reply => ({ ...reply, modelCalls: llm.calls.length }));
}

test('"is the hoodie in stock?" says what is in stock, per product, and asks which size', async () => {
    const reply = await ask('Is the hoodie in stock?');

    assert.match(reply.response, /^Yes, we have hoodies in stock/);
    assert.doesNotMatch(reply.response, /shown on each product's page/, 'the generic FAQ sentence came back');
    assert.match(reply.response, /\[CLSU Athletes Hoodie[^\]]*\]\(\/product\/clsu-athletes-hoodie\)/);
    // One size per line, nested under the product's name.
    assert.match(reply.response, /\(\/product\/clsu-athletes-hoodie\)\n  - Small · sold out\n  - Medium · only 3 left\n  - Large · only 1 left/);
    assert.match(reply.response, /\(\/product\/honor-hoodie\)\n  - Small · sold out\n  - Large\n/);
    assert.match(reply.response, /Which size are you looking for\?/);
    assert.equal(reply.modelCalls, 0, 'stock is read from the database, not asked of a model');
});

test('the size chips are complete questions that route back to the same answer', async () => {
    const first = await ask('Is the hoodie in stock?');
    assert.deepEqual(first.chips, ['Is the hoodie available in Medium?', 'Is the hoodie available in Large?']);

    const followUp = await ask(first.chips[0]);
    assert.match(followUp.response, /Medium/);
    assert.match(followUp.response, /Yes, \*\*Medium\*\* is available for:/);
    assert.match(followUp.response, /\*\*Honor Hoodie\*\* doesn't come in \*\*Medium\*\*/);
});

test('a size that is in stock is confirmed, with the price and what is left', async () => {
    const reply = await ask('Is the hoodie in large size available?');

    assert.match(reply.response, /Yes, \*\*Large\*\* is available for:/);
    assert.match(reply.response, /CLSU Athletes Hoodie – ₱899\.00\]\(\/product\/clsu-athletes-hoodie\) — Large in stock · only 1 left/);
    assert.match(reply.response, /Honor Hoodie – ₱750\.00/);
    assert.equal(reply.modelCalls, 0);
});

test('a size that is sold out says so and names the sizes that are not', async () => {
    const reply = await ask('Is the hoodie available in Small?');

    assert.match(reply.response, /\*\*Small\*\* is sold out for \*\*CLSU Athletes Hoodie\*\*, which is still available in: Medium, Large\./);
    assert.match(reply.response, /\*\*Honor Hoodie\*\*[^.]*Large/);
    assert.ok(!/^Yes/.test(reply.response), 'a sold-out size was confirmed');
});

test('"sold out" and "never made in that size" are told apart', async () => {
    const reply = await ask('Is the jacket available in Small?');

    assert.match(reply.response, /\*\*Small\*\* is sold out for \*\*CLSU Windbreaker Jacket\*\*/);
    assert.match(reply.response, /\*\*CLSU Varsity Jacket\*\* doesn't come in \*\*Small\*\*/);
});

test('a size the item does not come in is not reported as sold out', async () => {
    const reply = await ask('Is the hoodie available in XXL?');

    assert.match(reply.response, /couldn't find a \*\*XXL\*\* option/);
    assert.match(reply.response, /What's in stock: Medium, Large/);
    assert.doesNotMatch(reply.response, /sold out for/);
});

test('an item with nothing left is reported as out of stock, not recommended around', async () => {
    const reply = await ask('Is the poncho in stock?');

    assert.match(reply.response, /\*\*CLSU Rain Poncho\*\* is out of stock/);
    assert.doesNotMatch(reply.response, /\]\(\/product\//, 'a sold-out item was linked as if buyable');
    assert.deepEqual(reply.chips, []);
});

test('a plain item with no sizes is answered without asking for one', async () => {
    const reply = await ask('Is the tote bag available?');

    assert.match(reply.response, /^Yes, \*\*CLSU Tote Bag\*\* is in stock\./);
    assert.match(reply.response, /\(\/product\/clsu-tote-bag\)/);
    assert.doesNotMatch(reply.response, /Which size/);
});

test('another policy in the same message is still answered, ahead of the stock', async () => {
    const reply = await ask('Is the hoodie in stock and do you deliver?');

    assert.match(reply.response, /pickup/i);
    assert.match(reply.response, /Yes, we have hoodies in stock/);
    assert.ok(reply.response.search(/pickup/i) < reply.response.search(/Yes, we have/), 'the policy answer should lead');
});

test('a general stock question with no item still gets the FAQ, and "do you have" still recommends', async () => {
    const general = await ask('How do I know if something is in stock?');
    assert.match(general.response, /shown on each product's page/);

    const search = await ask('Do you have hoodies?');
    assert.doesNotMatch(search.response, /\n  - /);
});

test('an unreachable database is reported, not answered as "out of stock"', async () => {
    const catalog = createCatalog(sqliteAdapter(shopWithHoodies()), { log: silentLog });
    catalog.fetchStockFor = async () => { throw new CatalogUnavailableError(new Error('down')); };

    const reply = await handleChat(
        { message: 'Is the hoodie in stock?', shown: [], lastQuery: '' },
        { catalog, llm: null, log: silentLog, deadline: generousDeadline() }
    );
    assert.match(reply.response, /can't reach our product catalog/);
});

test('option labels and size mentions are read the way shoppers write them', () => {
    assert.equal(optionLabel('CLSU Athletes Hoodie - Large'), 'Large');
    assert.equal(optionLabel('Black'), 'Black');
    assert.deepEqual(sizesMentioned('is it in large size?'), ['large']);
    assert.deepEqual(sizesMentioned('do you have size M'), ['M']);
    assert.deepEqual(sizesMentioned("it's I'm fine"), [], 'letters inside words are not sizes');
});

// Variants named after the product with no separator ("CLSU Tumbler Green"), and
// colours rather than sizes: the option is the colour, not the whole name.
function shopWithTumbler() {
    const db = seedShop();
    db.exec(`
        INSERT INTO products (id, category_id, name, slug, short_description, description, price, stock_quantity, is_active, has_variants, deleted_at) VALUES
            (30, 2, 'CLSU Tumbler', 'clsu-tumbler', NULL, NULL, NULL, 0, 1, 1, NULL);
        INSERT INTO product_variants (id, product_id, name, price, stock_quantity, is_active, sort_order) VALUES
            (30, 30, 'CLSU Tumbler Green', 45, 5, 1, 0),
            (31, 30, 'CLSU Tumbler Yellow', 750, 0, 1, 1);
    `);
    return db;
}

test('a colour option is shown and matched by the colour, not the variant\'s full name', async () => {
    const list = await ask('Is the tumbler in stock?', shopWithTumbler());
    assert.match(list.response, /\n  - Green · only 5 left\n  - Yellow · sold out/);
    assert.match(list.response, /Which option are you looking for\?/);
    assert.deepEqual(list.chips, ['Is the tumbler available in Green?']);

    const yellow = await ask('Is the tumbler available in yellow?', shopWithTumbler());
    assert.match(yellow.response, /\*\*Yellow\*\* is sold out for \*\*CLSU Tumbler\*\*, which is still available in: Green\./);

    const green = await ask('Is the green tumbler available?', shopWithTumbler());
    assert.match(green.response, /^Yes, \*\*CLSU Tumbler\*\* is available in \*\*Green\*\*\./);
});

test('option labels drop the product name whether or not a separator follows it', () => {
    assert.equal(optionLabel('CLSU Tumbler Green', 'CLSU Tumbler'), 'Green');
    assert.equal(optionLabel('CLSU Notebook - Gray', 'CLSU Notebook'), 'Gray');
    assert.equal(optionLabel('Honor and Glory - Large', 'Sielesyuan Shirt'), 'Large');
    assert.equal(optionLabel('CLSU Tumbler', 'CLSU Tumbler'), 'CLSU Tumbler', 'a name that is only the product name is kept');
});

// One product per colour, each with its own sizes ("Sielesyuan T-Shirt - Brown").
function shopWithColourShirts() {
    const db = seedShop();
    db.exec(`
        INSERT INTO products (id, category_id, name, slug, short_description, description, price, stock_quantity, is_active, has_variants, deleted_at) VALUES
            (40, 1, 'Sielesyuan T-Shirt - White & Black', 'tee-white-black', NULL, NULL, NULL, 0, 1, 1, NULL),
            (41, 1, 'Sielesyuan T-Shirt - Brown', 'tee-brown', NULL, NULL, NULL, 0, 1, 1, NULL),
            (42, 1, 'Sielesyuan T-Shirt - Green', 'tee-green', NULL, NULL, NULL, 0, 1, 1, NULL),
            (43, 1, 'Sielesyuan T-Shirt - Black', 'tee-black', NULL, NULL, NULL, 0, 1, 1, NULL);
        INSERT INTO product_variants (id, product_id, name, price, stock_quantity, is_active, sort_order) VALUES
            (40, 40, 'Small', 350, 2, 1, 0), (41, 40, 'Large', 350, 4, 1, 1),
            (42, 41, 'Small', 350, 0, 1, 0), (43, 41, 'Large', 350, 0, 1, 1),
            (44, 42, 'Small', 350, 5, 1, 0), (45, 42, 'Large', 350, 0, 1, 1),
            (46, 43, 'Small', 350, 9, 1, 0), (47, 43, 'Large', 350, 9, 1, 1);
    `);
    return db;
}

test('a colour in the question narrows to the products of that colour', async () => {
    const reply = await ask('Is the black shirt in stock?', shopWithColourShirts());

    assert.match(reply.response, /^Yes, we have black shirts in stock/);
    assert.match(reply.response, /tee-white-black/);
    assert.match(reply.response, /tee-black\)/);
    assert.doesNotMatch(reply.response, /tee-green|tee-brown/, 'a shirt of another colour was listed');
    assert.deepEqual(reply.chips, ['Is the black shirt available in Small?', 'Is the black shirt available in Large?']);
});

test('a colour that is sold out is reported as sold out, not hidden behind the others', async () => {
    const reply = await ask('Is the brown shirt available?', shopWithColourShirts());

    assert.match(reply.response, /\*\*Sielesyuan T-Shirt - Brown\*\* is out of stock/);
    assert.doesNotMatch(reply.response, /tee-green|tee-black/);
});

test('a colour and a size together answer for that product and that size', async () => {
    const reply = await ask('Is the green t-shirt available in Large?', shopWithColourShirts());

    assert.match(reply.response, /\*\*Large\*\* is sold out for \*\*Sielesyuan T-Shirt - Green\*\*, which is still available in: Small\./);
    assert.doesNotMatch(reply.response, /tee-black|tee-white-black/);
});

test('without a colour every shirt is reported, and a size word never narrows', async () => {
    const all = await ask('Is the shirt in stock?', shopWithColourShirts());
    for (const slug of ['tee-white-black', 'tee-green', 'tee-black']) assert.match(all.response, new RegExp(slug));
    assert.match(all.response, /\*\*Sielesyuan T-Shirt - Brown\*\* is out of stock/);

    const large = await ask('Are shirts available in Large?', shopWithColourShirts());
    assert.match(large.response, /Yes, \*\*Large\*\* is available for:/);
});

test('more matches than are checked are admitted rather than passed off as the whole shop', async () => {
    const db = shopWithColourShirts();
    for (let i = 0; i < 4; i++) {
        db.exec(`INSERT INTO products (id, category_id, name, slug, price, stock_quantity, is_active, has_variants) VALUES (${60 + i}, 1, 'Sielesyuan T-Shirt - Extra ${i}', 'tee-extra-${i}', 100, 5, 1, 0)`);
    }
    const reply = await ask('Is the shirt in stock?', db);
    assert.match(reply.response, /There are 3 more matching products/);
});
