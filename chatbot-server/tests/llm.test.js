// The model chain and the one budget that covers a whole HTTP request.
// ChatController waits 60 seconds; everything here exists so that a slow or
// dead provider produces our own grounded answer well inside that.

import test from 'node:test';
import assert from 'node:assert/strict';

import { Deadline, INTERPRET_BUDGET_MS, INTERPRET_TIMEOUT_MS, PER_CALL_TIMEOUT_MS, REQUEST_BUDGET_MS, createLlm } from '../lib/llm.js';
import { silentLog } from './helpers.js';

const reply = (content) => ({ choices: [{ message: { content } }] });

// A client that answers per model name. `onCall` sees every request so a test
// can assert on the abort signal it was given.
function fakeClient(handlers, onCall = () => {}) {
    return {
        chat: {
            completions: {
                async create(body, options) {
                    onCall(body, options);
                    const handler = handlers[body.model];
                    if (!handler) throw new Error(`404 no such model: ${body.model}`);
                    return typeof handler === 'function' ? handler(body, options) : handler;
                }
            }
        }
    };
}

const generous = () => new Deadline(60000);

test('the whole request budget fits inside the timeout Laravel allows', () => {
    // 60s is ChatController's Http::timeout. The worst case here is the
    // interpretation chain followed by the generation chain.
    const worstCase = 4 * INTERPRET_TIMEOUT_MS + 4 * PER_CALL_TIMEOUT_MS;

    assert.ok(REQUEST_BUDGET_MS < 60000, 'the budget must finish before Laravel gives up');
    assert.ok(REQUEST_BUDGET_MS <= worstCase, 'the budget is what actually bounds the request, not the per-call caps');
    assert.ok(INTERPRET_BUDGET_MS < REQUEST_BUDGET_MS, 'reading the question must leave time to answer it');
});

test('a stage budget cannot outlive the request budget it came from', () => {
    let clock = 0;
    const request = new Deadline(5000, () => clock);

    assert.equal(request.child(8000).remaining(), 5000, 'the stage outlived the request');
    assert.equal(request.child(2000).remaining(), 2000);

    clock += 4000;
    assert.equal(request.child(8000).remaining(), 1000, 'the stage ignored time already spent');
});

test('a rate-limited interpretation chain gives up inside its own budget', async () => {
    let clock = 0;
    const advance = (ms) => { clock += ms; };

    // Every model hangs until aborted, which is what a 429-throttled free tier
    // looks like from here.
    const client = {
        chat: {
            completions: {
                async create(_body, { signal }) {
                    return new Promise((_resolve, reject) => {
                        signal.addEventListener('abort', () => reject(new Error('Request was aborted.')));
                    });
                }
            }
        }
    };

    const llm = createLlm({ client, models: ['a', 'b', 'c', 'd'], log: silentLog });
    const request = new Deadline(REQUEST_BUDGET_MS, () => clock);
    const stage = request.child(INTERPRET_BUDGET_MS);

    // The real clock drives the aborts; this one only accounts for the budget.
    const call = llm.complete({ system: 's', user: 'u', deadline: stage, perCallMs: 50 });
    const ticker = setInterval(() => advance(2000), 10);

    await assert.rejects(() => call);
    clearInterval(ticker);

    assert.ok(request.remaining() > 0, 'interpretation consumed the whole request budget');
});

test('the first model that answers wins and no other is tried', async () => {
    const seen = [];
    const llm = createLlm({
        client: fakeClient({ 'model-a': reply('  hello  ') }, (body) => seen.push(body.model)),
        models: ['model-a', 'model-b'],
        log: silentLog
    });

    assert.equal(await llm.complete({ system: 's', user: 'u', deadline: generous() }), 'hello');
    assert.deepEqual(seen, ['model-a']);
});

test('a dead model falls through to the next one', async () => {
    const seen = [];
    const llm = createLlm({
        client: fakeClient({ 'model-b': reply('second') }, (body) => seen.push(body.model)),
        models: ['model-a', 'model-b'],
        log: silentLog
    });

    assert.equal(await llm.complete({ system: 's', user: 'u', deadline: generous() }), 'second');
    assert.deepEqual(seen, ['model-a', 'model-b']);
});

test('an empty payload counts as a failure, not as an answer', async () => {
    const llm = createLlm({
        client: fakeClient({ 'model-a': reply('   '), 'model-b': reply('real answer') }),
        models: ['model-a', 'model-b'],
        log: silentLog
    });

    assert.equal(await llm.complete({ system: 's', user: 'u', deadline: generous() }), 'real answer');
});

test('a safety preamble some free models prepend is stripped', async () => {
    const llm = createLlm({
        client: fakeClient({ 'model-a': reply('User Safety: Safe\nHere are some picks:') }),
        models: ['model-a'],
        log: silentLog
    });

    assert.equal(await llm.complete({ system: 's', user: 'u', deadline: generous() }), 'Here are some picks:');
});

test('every model failing raises, so the caller can fall back deterministically', async () => {
    const llm = createLlm({ client: fakeClient({}), models: ['model-a', 'model-b'], log: silentLog });

    await assert.rejects(() => llm.complete({ system: 's', user: 'u', deadline: generous() }), /404 no such model/);
});

test('a hung call is aborted, and the upstream request really is cancelled', async () => {
    let abortedSignal = null;

    const client = fakeClient({
        'model-a': (_body, { signal }) => new Promise((_resolve, reject) => {
            signal.addEventListener('abort', () => {
                abortedSignal = signal;
                reject(new Error('Request was aborted.'));
            });
        }),
        'model-b': reply('answer from the second model')
    });

    const llm = createLlm({ client, models: ['model-a', 'model-b'], log: silentLog });

    const answer = await llm.complete({
        system: 's',
        user: 'u',
        deadline: generous(),
        perCallMs: 40
    });

    assert.equal(answer, 'answer from the second model');
    assert.ok(abortedSignal?.aborted, 'the hung call was left running instead of being cancelled');
});

test('a call is not started when the budget cannot cover it', async () => {
    let called = 0;
    const llm = createLlm({
        client: fakeClient({ 'model-a': reply('hi') }, () => { called++; }),
        models: ['model-a'],
        log: silentLog
    });

    const spent = new Deadline(500);

    await assert.rejects(() => llm.complete({ system: 's', user: 'u', deadline: spent }));
    assert.equal(called, 0, 'a call was started with no time left to finish it');
});

test('the deadline shrinks a per-call timeout to whatever is left', () => {
    let clock = 1000;
    const deadline = new Deadline(5000, () => clock);

    assert.equal(deadline.slice(6000), 5000);
    clock += 3000;
    assert.equal(deadline.slice(6000), 2000);
    clock += 3000;
    assert.equal(deadline.remaining(), 0);
    assert.equal(deadline.expired(), true);
});
