// The keyword rules. They answer whenever the model cannot, so their existing
// behaviour matters as much as the new behaviour beside it.

import test from 'node:test';
import assert from 'node:assert/strict';

import { expandConcepts, isMoreRequest, parseBudget } from '../lib/vocabulary.js';

test('English budget wording is read as before', () => {
    assert.deepEqual(parseBudget('under 300'), { amount: 300, isCap: true });
    assert.deepEqual(parseBudget('below ₱500'), { amount: 500, isCap: true });
    assert.deepEqual(parseBudget('under ₱500'), { amount: 500, isCap: true });
    assert.deepEqual(parseBudget('300 pesos'), { amount: 300, isCap: false });
    assert.deepEqual(parseBudget('around 250'), { amount: 250, isCap: false });
    assert.deepEqual(parseBudget('max of 1,200'), { amount: 1200, isCap: true });
});

test('the quick-reply chip wording is still read as a cap', () => {
    // "Under ₱250" is a chip this service writes; it was once read as an
    // "around" budget because the currency sign broke the keyword match.
    assert.deepEqual(parseBudget('under ₱250'), { amount: 250, isCap: true });
});

test('a bare number is not a price', () => {
    assert.deepEqual(parseBudget('size 5'), { amount: null, isCap: false });
    assert.deepEqual(parseBudget('i want 2'), { amount: null, isCap: false });
});

test('Filipino caps are read as caps, so the keyword path survives Taglish too', () => {
    assert.deepEqual(parseBudget('hindi lalagpas sa 500'), { amount: 500, isCap: true });
    assert.deepEqual(parseBudget('hindi hihigit sa 500 pesos'), { amount: 500, isCap: true });
    assert.deepEqual(parseBudget('wag lalagpas ng 300'), { amount: 300, isCap: true });
    assert.deepEqual(parseBudget('500 pababa'), { amount: 500, isCap: true });
});

test('a concept is widened to the words that mean the same kind of item', () => {
    const terms = expandConcepts(['t-shirt']);

    assert.ok(terms.includes('shirt'), 'a product named "... shirt" would be missed');
    assert.ok(terms.includes('polo'));
    assert.ok(terms.includes('tee'));
});

test('widening never crosses into a different kind of item', () => {
    const terms = expandConcepts(['jacket']);

    assert.ok(terms.includes('windbreaker'), 'a windbreaker is a jacket');
    assert.ok(terms.includes('coat'));
    assert.ok(!terms.includes('shirt'), 'a jackets request must not come back as shirts');
    assert.ok(!terms.includes('mug'));
});

test('a concept in no group is searched as itself', () => {
    assert.deepEqual(expandConcepts(['poncho']), ['poncho']);
});

test('the widened term list stays bounded', () => {
    const terms = expandConcepts(['shirt', 'jacket', 'bag', 'mug', 'cap', 'pen'], 8);

    assert.ok(terms.length <= 8);
    assert.equal(new Set(terms).size, terms.length, 'duplicates were not removed');
});

test('"more" only continues a conversation that already recommended something', () => {
    assert.equal(isMoreRequest('show me more', { shown: [], lastQuery: '' }), false);
    assert.equal(isMoreRequest('show me more', { shown: ['a-slug'], lastQuery: 'jackets' }), true);
    assert.equal(
        isMoreRequest('what else can you help me with exactly, in detail, please', { shown: ['a-slug'], lastQuery: 'jackets' }),
        false,
        'a long question is not a request for more products'
    );
});
