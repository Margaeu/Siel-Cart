// The model is allowed to explain a recommendation in its own words. These are
// the checks that keep the explanation from becoming a second, unverified
// source of prices, stock claims, and store policy.

import test from 'node:test';
import assert from 'node:assert/strict';

import { generateLeadIn, validateLeadIn, validateStoreAnswer } from '../lib/ground.js';
import { fakeLlm, generousDeadline, silentLog } from './helpers.js';

const PICKS = [
    { name: 'CLSU Varsity Jacket', price: 480, category_name: 'Apparel', short_description: 'Classic varsity cut' },
    { name: 'CLSU Fleece Hoodie', price: 450, category_name: 'Apparel', short_description: 'Warm fleece layer' }
];

const CATALOGUE = [
    { name: 'CLSU Varsity Jacket' },
    { name: 'CLSU Fleece Hoodie' },
    { name: 'UBAP Notebook' },
    { name: 'CLSU Rain Poncho' }
];

const check = (text) => validateLeadIn(text, { picks: PICKS, catalogue: CATALOGUE });

test('a plain grounded sentence is accepted and punctuated', () => {
    assert.equal(check('These are the warmest layers we have in stock'), 'These are the warmest layers we have in stock:');
    assert.equal(check('Here are some warm options:'), 'Here are some warm options:');
});

test('any numeral is refused, because the list underneath prints every figure', () => {
    assert.equal(check('Here are 2 warm options:'), null);
    assert.equal(check('The jacket is ₱480:'), null);
    assert.equal(check('Only 3 left in stock:'), null);
});

test('a price restated in words is still only ever printed by the backend', () => {
    // The list is generated from the database rows, so the sentence never needs
    // to carry a figure at all; this is the rule that makes that true.
    const withNumber = check('Both are under 500 pesos:');
    assert.equal(withNumber, null);
});

test('an invented product property is refused', () => {
    assert.equal(check('This waterproof jacket suits the rainy season:'), null);
    assert.equal(check('A thermal layer for cold mornings:'), null);
    assert.equal(check('These are insulated for the rain:'), null);
});

test('a claim this store cannot make is refused', () => {
    assert.equal(check('We can deliver these to you:'), null);
    assert.equal(check('You can pay with GCash:'), null);
    assert.equal(check('Free shipping on these picks:'), null);
    assert.equal(check('These come with a warranty:'), null);
});

test('links and URLs are refused, because the backend writes every link', () => {
    assert.equal(check('See [our catalog](https://example.com):'), null);
    assert.equal(check('Visit www.example.com for more:'), null);
    assert.equal(check('Browse [more](/products) here:'), null);
});

test('naming a product that is not in the list is refused', () => {
    assert.equal(check('The UBAP Notebook is also a good match:'), null);
    assert.equal(check('Try the CLSU Rain Poncho:'), null);
});

test('naming a product that IS in the list is allowed', () => {
    assert.equal(
        check('The CLSU Varsity Jacket is the warmest of these'),
        'The CLSU Varsity Jacket is the warmest of these:'
    );
});

test('an empty or over-long sentence is refused', () => {
    assert.equal(check(''), null);
    assert.equal(check('   '), null);
    assert.equal(check('warm '.repeat(80)), null);
    assert.equal(check(null), null);
    assert.equal(check({ text: 'warm' }), null);
});

test('a failed lead-in returns null so the caller keeps its own heading', async () => {
    const rejected = await generateLeadIn({
        llm: fakeLlm(['These waterproof jackets ship free!']),
        message: 'something warm',
        picks: PICKS,
        catalogue: CATALOGUE,
        deadline: generousDeadline(),
        timeoutMs: 5000,
        log: silentLog
    });

    assert.equal(rejected, null);
});

test('an unreachable provider costs the wording, not the answer', async () => {
    const result = await generateLeadIn({
        llm: fakeLlm([new Error('429 rate limited')]),
        message: 'something warm',
        picks: PICKS,
        catalogue: CATALOGUE,
        deadline: generousDeadline(),
        timeoutMs: 5000,
        log: silentLog
    });

    assert.equal(result, null);
});

test('the lead-in prompt is given no price or stock to repeat', async () => {
    const llm = fakeLlm(['Warm layers for the season:']);

    await generateLeadIn({
        llm,
        message: 'something warm',
        picks: PICKS,
        catalogue: CATALOGUE,
        deadline: generousDeadline(),
        timeoutMs: 5000,
        log: silentLog
    });

    const { user } = llm.calls[0];
    assert.ok(!user.includes('480'), 'the price was handed to the model');
    assert.ok(!user.includes('450'), 'the price was handed to the model');
    assert.ok(user.includes('CLSU Varsity Jacket'), 'the model needs the name to explain the pick');
    assert.ok(user.includes('<request>'), 'the shopper text was not delimited');
});

test('a store answer keeps a correctly negated policy statement', () => {
    assert.ok(validateStoreAnswer('Siel Cart does not deliver: orders are collected at the UBAP Office.'));
    assert.ok(validateStoreAnswer('We do not accept GCash. Payment is cash on pickup.'));
});

test('a store answer that asserts a policy the store does not have is refused', () => {
    assert.equal(validateStoreAnswer('We deliver within Nueva Ecija for a small fee.'), null);
    assert.equal(validateStoreAnswer('You may pay with GCash at checkout.'), null);
});

test('invented opening hours are refused', () => {
    assert.equal(validateStoreAnswer('The office is open 8:00 AM to 5:00 PM.'), null);
    assert.equal(validateStoreAnswer('Visit any weekday before 5 pm.'), null);
});

test('an external link in a store answer is refused', () => {
    assert.equal(validateStoreAnswer('See [our policy](https://evil.example/steal).'), null);
    assert.ok(validateStoreAnswer('See our [Privacy Policy](/privacy-policy) for details.'));
});
