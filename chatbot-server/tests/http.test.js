// The HTTP surface: what a request must get past before it may cost anything.
// Drives the real Express app (lib/app.js) over a loopback socket, with a
// scripted catalogue and model, so the middleware ORDER is what is tested --
// "401 before the database" is only true if authentication actually runs first.

import test from 'node:test';
import assert from 'node:assert/strict';

import { createApp } from '../lib/app.js';
import { createCatalog } from '../lib/catalog.js';
import {
    MAX_MESSAGE_LENGTH,
    createRateLimiter,
    resolveServiceToken,
    tokensMatch,
    validateChatBody
} from '../lib/http-guard.js';
import { fakeLlm, seedShop, silentLog, sqliteAdapter } from './helpers.js';

const TOKEN = 'a'.repeat(64);

// A catalogue and model that record any use at all. A rejected request must
// leave both untouched.
function spies() {
    const touched = [];
    const catalog = new Proxy({}, {
        get(_target, prop) {
            touched.push(`catalog.${String(prop)}`);
            return async () => { throw new Error('catalogue must not be reached'); };
        }
    });
    const llm = {
        calls: [],
        async complete(request) {
            touched.push('llm.complete');
            this.calls.push(request);
            throw new Error('model must not be reached');
        }
    };
    let pings = 0;
    const pingDatabase = async () => { pings += 1; touched.push('db.ping'); };
    return { catalog, llm, pingDatabase, touched, pings: () => pings };
}

async function withServer(deps, run) {
    const app = createApp({ log: silentLog, ...deps });
    const server = await new Promise(resolve => {
        const s = app.listen(0, '127.0.0.1', () => resolve(s));
    });
    const base = `http://127.0.0.1:${server.address().port}`;
    try {
        await run(base);
    } finally {
        await new Promise(resolve => server.close(resolve));
    }
}

const post = (base, body, { token = TOKEN, raw } = {}) => fetch(`${base}/api/chat`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {})
    },
    body: raw ?? JSON.stringify(body)
});

test('a request without the service token is refused before the catalogue or model is touched', async () => {
    const s = spies();
    await withServer({ ...s, serviceToken: TOKEN }, async base => {
        const res = await post(base, { message: 'Recommend a hoodie' }, { token: null });
        assert.equal(res.status, 401);
        assert.deepEqual(await res.json(), { error: 'Unauthorized.' });
    });
    assert.deepEqual(s.touched, []);
});

test('a wrong token is refused the same way, including one that differs only in length', async () => {
    const s = spies();
    await withServer({ ...s, serviceToken: TOKEN }, async base => {
        for (const token of ['b'.repeat(64), TOKEN.slice(0, -1), `${TOKEN}a`]) {
            const res = await post(base, { message: 'hi' }, { token });
            assert.equal(res.status, 401, `token ${token.length} chars`);
        }
    });
    assert.deepEqual(s.touched, []);
});

test('authentication runs before the body is parsed, so an unauthenticated oversized body is a 401, not a 413', async () => {
    const s = spies();
    await withServer({ ...s, serviceToken: TOKEN }, async base => {
        const res = await post(base, null, { token: null, raw: JSON.stringify({ message: 'x'.repeat(100_000) }) });
        assert.equal(res.status, 401);
    });
});

test('with the right token the request reaches the pipeline and is answered', async () => {
    const catalog = createCatalog(sqliteAdapter(seedShop()), { log: silentLog });
    await withServer({ catalog, llm: null, serviceToken: TOKEN, pingDatabase: async () => {} }, async base => {
        const res = await post(base, { message: 'How do I pay?' });
        assert.equal(res.status, 200);
        const body = await res.json();
        assert.equal(typeof body.response, 'string');
        assert.ok(body.response.length > 0);
    });
});

test('an oversized body is answered 413 as JSON, not an HTML error page', async () => {
    const s = spies();
    await withServer({ ...s, serviceToken: TOKEN }, async base => {
        const res = await post(base, null, { raw: JSON.stringify({ message: 'hi', shown: ['x'.repeat(40_000)] }) });
        assert.equal(res.status, 413);
        assert.match(res.headers.get('content-type'), /application\/json/);
    });
    assert.deepEqual(s.touched, []);
});

test('malformed JSON is a 400 with no stack trace', async () => {
    const s = spies();
    await withServer({ ...s, serviceToken: TOKEN }, async base => {
        const res = await post(base, null, { raw: '{"message": ' });
        assert.equal(res.status, 400);
        const text = await res.text();
        assert.doesNotMatch(text, /at .*\.js/);
    });
    assert.deepEqual(s.touched, []);
});

test('a message over the limit is refused before the pipeline runs', async () => {
    const s = spies();
    await withServer({ ...s, serviceToken: TOKEN }, async base => {
        const res = await post(base, { message: 'a'.repeat(MAX_MESSAGE_LENGTH + 1) });
        assert.equal(res.status, 400);
    });
    assert.deepEqual(s.touched, []);
});

test('authenticated calls beyond the per-minute ceiling get 429 with Retry-After', async () => {
    const catalog = createCatalog(sqliteAdapter(seedShop()), { log: silentLog });
    let clock = 1_000_000;
    await withServer({ catalog, llm: null, serviceToken: TOKEN, pingDatabase: async () => {}, rateLimitPerMinute: 2, now: () => clock }, async base => {
        assert.equal((await post(base, { message: 'How do I pay?' })).status, 200);
        assert.equal((await post(base, { message: 'How do I pay?' })).status, 200);
        const limited = await post(base, { message: 'How do I pay?' });
        assert.equal(limited.status, 429);
        assert.ok(Number(limited.headers.get('retry-after')) >= 1);

        clock += 60_000;
        assert.equal((await post(base, { message: 'How do I pay?' })).status, 200);
    });
});

test('unauthenticated requests do not use up the allowance the storefront needs', async () => {
    const catalog = createCatalog(sqliteAdapter(seedShop()), { log: silentLog });
    await withServer({ catalog, llm: null, serviceToken: TOKEN, pingDatabase: async () => {}, rateLimitPerMinute: 1 }, async base => {
        for (let i = 0; i < 5; i++) {
            assert.equal((await post(base, { message: 'hi' }, { token: 'nope' })).status, 401);
        }
        assert.equal((await post(base, { message: 'How do I pay?' })).status, 200);
    });
});

test('liveness touches neither the database nor the model, and needs no token', async () => {
    const s = spies();
    await withServer({ ...s, serviceToken: TOKEN }, async base => {
        const res = await fetch(`${base}/health`);
        assert.equal(res.status, 200);
        assert.deepEqual(await res.json(), { status: 'ok' });
    });
    assert.deepEqual(s.touched, []);
});

test('readiness pings the database once, never the model, and is behind the token', async () => {
    const s = spies();
    await withServer({ ...s, serviceToken: TOKEN }, async base => {
        assert.equal((await fetch(`${base}/health/ready`)).status, 401);
        assert.equal(s.pings(), 0);

        const res = await fetch(`${base}/health/ready`, { headers: { Authorization: `Bearer ${TOKEN}` } });
        assert.equal(res.status, 200);
        assert.equal((await res.json()).database, 'ok');
    });
    assert.deepEqual(s.touched, ['db.ping']);
    assert.equal(s.llm.calls.length, 0);
});

test('readiness reports 503 when the database is unreachable', async () => {
    await withServer({
        catalog: {}, llm: fakeLlm(), serviceToken: TOKEN,
        pingDatabase: async () => { throw new Error('ECONNREFUSED 10.0.0.1:3306'); }
    }, async base => {
        const res = await fetch(`${base}/health/ready`, { headers: { Authorization: `Bearer ${TOKEN}` } });
        assert.equal(res.status, 503);
        const text = await res.text();
        assert.doesNotMatch(text, /ECONNREFUSED|10\.0\.0\.1/, 'connection details are logged, not returned');
    });
});

test('no CORS headers are sent: a browser on another origin cannot read replies', async () => {
    const catalog = createCatalog(sqliteAdapter(seedShop()), { log: silentLog });
    await withServer({ catalog, llm: null, serviceToken: TOKEN, pingDatabase: async () => {} }, async base => {
        const res = await fetch(`${base}/api/chat`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Origin: 'https://evil.example', Authorization: `Bearer ${TOKEN}` },
            body: JSON.stringify({ message: 'How do I pay?' })
        });
        assert.equal(res.headers.get('access-control-allow-origin'), null);
        assert.equal(res.headers.get('x-powered-by'), null);
    });
});

test('with no token configured (local development) requests pass without one', async () => {
    const catalog = createCatalog(sqliteAdapter(seedShop()), { log: silentLog });
    await withServer({ catalog, llm: null, serviceToken: null, pingDatabase: async () => {} }, async base => {
        assert.equal((await post(base, { message: 'How do I pay?' }, { token: null })).status, 200);
    });
});

// ---- the pure pieces ---------------------------------------------------------

test('resolveServiceToken: a missing token is never fatal, but production is warned loudly', () => {
    const local = resolveServiceToken({});
    assert.equal(local.token, null);
    assert.doesNotMatch(local.warning, /SECURITY/);

    for (const env of [{ NODE_ENV: 'production' }, { RAILWAY_ENVIRONMENT: 'production' }, { RAILWAY_ENVIRONMENT_NAME: 'production' }]) {
        const prod = resolveServiceToken(env);
        assert.equal(prod.token, null);
        assert.match(prod.warning, /SECURITY/);
    }

    assert.deepEqual(resolveServiceToken({ CHATBOT_SERVICE_TOKEN: `  ${TOKEN}  ` }), { token: TOKEN, warning: null });
    assert.match(resolveServiceToken({ CHATBOT_SERVICE_TOKEN: 'short' }).warning, /shorter than 32/);
});

test('tokensMatch compares exactly and tolerates non-strings', () => {
    assert.equal(tokensMatch(TOKEN, TOKEN), true);
    assert.equal(tokensMatch(TOKEN, TOKEN.toUpperCase()), false);
    assert.equal(tokensMatch(TOKEN, ''), false);
    assert.equal(tokensMatch(TOKEN, null), false);
    assert.equal(tokensMatch(TOKEN, undefined), false);
});

test('validateChatBody mirrors ChatController: 100 characters counted as Laravel counts them', () => {
    assert.equal(MAX_MESSAGE_LENGTH, 100);
    assert.equal(validateChatBody({ message: 'a'.repeat(100) }), null);
    assert.notEqual(validateChatBody({ message: 'a'.repeat(101) }), null);
    // 100 emoji are 200 UTF-16 units but 100 characters to mb_strlen.
    assert.equal(validateChatBody({ message: '😀'.repeat(100) }), null);

    for (const bad of [null, [], 'text', {}, { message: '' }, { message: '   ' }, { message: 42 }]) {
        assert.notEqual(validateChatBody(bad), null, JSON.stringify(bad));
    }

    assert.equal(validateChatBody({ message: 'more', shown: ['a', 'b'], last_query: 'hoodie' }), null);
    assert.equal(validateChatBody({ message: 'more', shown: null, last_query: null }), null);
    assert.notEqual(validateChatBody({ message: 'more', shown: Array(101).fill('a') }), null);
    assert.notEqual(validateChatBody({ message: 'more', shown: ['x'.repeat(256)] }), null);
    assert.notEqual(validateChatBody({ message: 'more', shown: [1] }), null);
    assert.notEqual(validateChatBody({ message: 'more', shown: 'a' }), null);
    assert.notEqual(validateChatBody({ message: 'more', last_query: 'q'.repeat(101) }), null);
});

test('the rate limiter resets on a new window', () => {
    let t = 0;
    const limiter = createRateLimiter({ limit: 1, windowMs: 1000, now: () => t });
    const results = [];
    const res = { set() {}, status(code) { results.push(code); return { json() {} }; } };
    limiter({}, res, () => results.push('next'));
    limiter({}, res, () => results.push('next'));
    t = 1000;
    limiter({}, res, () => results.push('next'));
    assert.deepEqual(results, ['next', 429, 'next']);
});
