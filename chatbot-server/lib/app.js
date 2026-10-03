// The HTTP surface, built from injected dependencies so the tests can drive the
// real routes and middleware order with a scripted catalogue and model.
// server.js supplies the real pool, OpenRouter client and token.

import express from 'express';

import { Deadline, REQUEST_BUDGET_MS } from './llm.js';
import { handleChatSafely, readConversationContext } from './pipeline.js';
import {
    MAX_BODY_SIZE,
    createRateLimiter,
    jsonBodyErrorHandler,
    requireServiceToken,
    validateChatBody
} from './http-guard.js';

/**
 * @param {object} deps
 * @param {object} deps.catalog          createCatalog(...) result
 * @param {object|null} deps.llm         createLlm(...) result, or null for keyword-only
 * @param {string|null} deps.serviceToken null = not enforced (resolveServiceToken)
 * @param {() => Promise<unknown>} deps.pingDatabase  a cheap round trip, e.g. SELECT 1
 * @param {number} [deps.rateLimitPerMinute]
 * @param {() => number} [deps.now]
 * @param {Console} [deps.log]
 */
export function createApp({ catalog, llm, serviceToken, pingDatabase, rateLimitPerMinute, now, log = console }) {
    const app = express();

    // No CORS middleware, on purpose. The only legitimate caller is Laravel's
    // ChatController, server to server; a browser never calls this service, so
    // there is no origin to allow. The old `cors()` with no options allowed
    // every origin. Note CORS was never the protection anyway -- it only stops
    // a browser reading the reply, not anyone sending the request -- which is
    // why the token check below exists.
    app.disable('x-powered-by');

    const authenticate = requireServiceToken(serviceToken);
    const limit = createRateLimiter({ limit: rateLimitPerMinute, now });

    // Liveness: the process is up and serving HTTP. Deliberately touches
    // nothing -- no database, no model -- so an uptime monitor polling it every
    // minute can never spend OpenRouter quota or hold a pool connection, and a
    // database outage doesn't make the platform restart a healthy process.
    // Open, because it says nothing beyond "running".
    app.get('/health', (req, res) => {
        res.json({ status: 'ok' });
    });

    // Readiness: can this instance answer from the real catalogue? One
    // `SELECT 1`, and still never the model: provider availability changes
    // minute to minute on the free tier, and the pipeline already degrades to
    // keyword answers without it, so it is not a reason to call the service
    // down. Behind the token because it reveals whether the database is
    // reachable.
    app.get('/health/ready', authenticate, async (req, res) => {
        try {
            await pingDatabase();
            res.json({ status: 'ok', database: 'ok', model: llm ? 'configured' : 'not_configured' });
        } catch (error) {
            log.error('Readiness check: database unreachable:', error?.message ?? error);
            res.status(503).json({ status: 'unavailable', database: 'unreachable' });
        }
    });

    app.post(
        '/api/chat',
        authenticate,
        limit,
        express.json({ limit: MAX_BODY_SIZE }),
        jsonBodyErrorHandler,
        async (req, res) => {
            const invalid = validateChatBody(req.body);
            if (invalid) {
                return res.status(400).json({ error: invalid });
            }

            const { message } = req.body;
            const { shown, lastQuery } = readConversationContext(req.body);

            // One budget for the whole request, shared by reading the question and
            // writing the answer. ChatController allows 60 seconds; finishing inside
            // REQUEST_BUDGET_MS means a slow provider still gets our own grounded reply
            // out rather than tripping Laravel's timeout.
            const deadline = new Deadline(REQUEST_BUDGET_MS);

            const reply = await handleChatSafely({ message, shown, lastQuery }, { catalog, llm, deadline });

            return res.json(reply);
        }
    );

    // Anything else (unknown routes fall through to Express's 404, which is
    // fine) that throws ends here as JSON with no stack trace.
    // eslint-disable-next-line no-unused-vars
    app.use((err, req, res, next) => {
        log.error('Unhandled request error:', err?.message ?? err);
        res.status(500).json({ error: 'Internal error.' });
    });

    return app;
}
