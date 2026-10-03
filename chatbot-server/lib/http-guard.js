// What a request has to get past before it is allowed to cost anything.
//
// The service has a public Railway domain, and before this module it accepted
// any POST from anywhere with CORS wide open. That made Laravel's protections
// decorative: ChatController's throttle:10,1 and 100-character cap only apply
// to traffic that goes through Laravel, and nothing forced it to. A direct
// caller could spend the OpenRouter quota and query the Aiven catalogue as
// fast as it liked. The rules here are checked in an order chosen so that the
// cheap rejection always comes first:
//
//   1. service token   -- a header compare; no body parsed, no DB, no model
//   2. rate limit      -- counts authenticated calls only (see below)
//   3. body size       -- express.json's own limit, answered as JSON
//   4. shape and length -- the same rules ChatController validates

import crypto from 'node:crypto';

// Must equal App\Http\Controllers\Api\ChatController::MAX_MESSAGE_LENGTH. The
// widget's maxlength is rendered from the PHP constant, Laravel rejects
// anything longer with a 422, and this is the same limit again for a caller
// that skipped Laravel. Change all three together.
export const MAX_MESSAGE_LENGTH = 100;
export const MAX_SHOWN_ITEMS = 100;
export const MAX_SHOWN_ITEM_LENGTH = 255;

// The largest legitimate body is 100 slugs of 255 characters plus two
// 100-character strings, about 26 KB of JSON at the very worst; real ones are
// a few hundred bytes. 32kb leaves headroom without letting a caller make us
// buffer and parse megabytes.
export const MAX_BODY_SIZE = '32kb';

// Authenticated calls per minute. Every authenticated call comes from the
// Laravel app (Azure), which already throttles each shopper to 10 a minute, so
// this ceiling only matters if the token leaks or a bug loops. Configurable
// because the right number depends on traffic the repo cannot see.
export const DEFAULT_RATE_LIMIT_PER_MINUTE = 120;

// Railway sets RAILWAY_ENVIRONMENT(_NAME) on every deployment, and the
// production start is the case that must not run without a token.
export function isProductionEnvironment(env = process.env) {
    return env.NODE_ENV === 'production'
        || Boolean(env.RAILWAY_ENVIRONMENT)
        || Boolean(env.RAILWAY_ENVIRONMENT_NAME);
}

const GENERATE_HINT = 'Generate one with: node -e "console.log(require(\'crypto\').randomBytes(32).toString(\'hex\'))"';

/**
 * Decide at boot which token, if any, requests must carry.
 *
 * Enforcement is opt-in: with no CHATBOT_SERVICE_TOKEN the service runs open,
 * exactly as it did before this check existed, and says so loudly in
 * production. Refusing to start would be the stricter choice, but it turns a
 * variable forgotten on one platform into a chatbot outage, and the rollout
 * needs the variable set on two (Azure, then Railway -- see
 * docs/azure-deployment.md). Setting the token on both is what closes the
 * endpoint; the warning below is what keeps an unset one from going unnoticed.
 */
export function resolveServiceToken(env = process.env) {
    const token = (env.CHATBOT_SERVICE_TOKEN || '').trim();

    if (token !== '') {
        return {
            token,
            warning: token.length < 32
                ? `CHATBOT_SERVICE_TOKEN is shorter than 32 characters and easier to guess. ${GENERATE_HINT}`
                : null
        };
    }

    return {
        token: null,
        warning: isProductionEnvironment(env)
            ? `SECURITY: CHATBOT_SERVICE_TOKEN is not set, so /api/chat accepts requests from anyone on the internet, bypassing the storefront's rate limit. Set it on this service and as CHATBOT_SERVICE_TOKEN on the Laravel app. ${GENERATE_HINT}`
            : 'CHATBOT_SERVICE_TOKEN is not set: accepting unauthenticated requests (fine for local development).'
    };
}

// Hashing both sides first makes the comparison constant-time regardless of
// length: timingSafeEqual throws on unequal lengths, and returning early on a
// length mismatch would itself leak the token's length.
export function tokensMatch(expected, provided) {
    if (typeof expected !== 'string' || typeof provided !== 'string') return false;
    const a = crypto.createHash('sha256').update(expected).digest();
    const b = crypto.createHash('sha256').update(provided).digest();
    return crypto.timingSafeEqual(a, b);
}

function bearerFrom(header) {
    const match = /^Bearer\s+(.+)$/i.exec(typeof header === 'string' ? header : '');
    return match ? match[1].trim() : null;
}

/**
 * Express middleware. With a null token (no CHATBOT_SERVICE_TOKEN, see
 * resolveServiceToken) every request passes.
 */
export function requireServiceToken(token) {
    return (req, res, next) => {
        if (token === null) return next();

        if (!tokensMatch(token, bearerFrom(req.get('authorization')))) {
            return res.status(401).json({ error: 'Unauthorized.' });
        }

        return next();
    };
}

/**
 * A fixed-window counter over ALL requests that reach it.
 *
 * It is global rather than per IP on purpose. It runs after the token check,
 * so everything it counts came from the Laravel app; and behind Railway's
 * proxy every caller shares the proxy's address anyway, so a per-IP counter
 * placed before authentication would have let a flood of unauthenticated
 * requests use up the allowance and lock the real storefront out.
 */
export function createRateLimiter({ limit = DEFAULT_RATE_LIMIT_PER_MINUTE, windowMs = 60_000, now = () => Date.now() } = {}) {
    let windowStart = now();
    let count = 0;

    return (req, res, next) => {
        const t = now();
        if (t - windowStart >= windowMs) {
            windowStart = t;
            count = 0;
        }

        count += 1;
        if (count > limit) {
            res.set('Retry-After', String(Math.max(1, Math.ceil((windowStart + windowMs - t) / 1000))));
            return res.status(429).json({ error: 'Too many requests.' });
        }

        return next();
    };
}

export function rateLimitFromEnv(env = process.env) {
    const parsed = Number.parseInt(env.CHATBOT_RATE_LIMIT_PER_MINUTE ?? '', 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : DEFAULT_RATE_LIMIT_PER_MINUTE;
}

// Laravel's max: rule counts characters with mb_strlen, i.e. code points.
// String.length counts UTF-16 units, so an emoji would count twice here and a
// message Laravel accepted could be refused.
const charLength = (s) => [...s].length;

/**
 * The same rules ChatController validates, so a request that skipped Laravel
 * gets no more room than one that went through it. Returns an error message,
 * or null when the body is acceptable.
 */
export function validateChatBody(body) {
    if (body === null || typeof body !== 'object' || Array.isArray(body)) {
        return 'Request body must be a JSON object.';
    }

    const { message, shown, last_query: lastQuery } = body;

    if (typeof message !== 'string' || message.trim() === '') {
        return 'Message is required.';
    }
    if (charLength(message) > MAX_MESSAGE_LENGTH) {
        return `Message may not be longer than ${MAX_MESSAGE_LENGTH} characters.`;
    }

    if (shown !== undefined && shown !== null) {
        if (!Array.isArray(shown) || shown.length > MAX_SHOWN_ITEMS) {
            return `shown must be a list of at most ${MAX_SHOWN_ITEMS} items.`;
        }
        if (shown.some(s => typeof s !== 'string' || charLength(s) > MAX_SHOWN_ITEM_LENGTH)) {
            return `Each shown item must be a string of at most ${MAX_SHOWN_ITEM_LENGTH} characters.`;
        }
    }

    if (lastQuery !== undefined && lastQuery !== null) {
        if (typeof lastQuery !== 'string' || charLength(lastQuery) > MAX_MESSAGE_LENGTH) {
            return `last_query must be a string of at most ${MAX_MESSAGE_LENGTH} characters.`;
        }
    }

    return null;
}

/**
 * Turns express.json's errors into JSON answers instead of Express's HTML
 * error page (which also carried a stack trace outside production).
 */
export function jsonBodyErrorHandler(err, req, res, next) {
    if (err?.type === 'entity.too.large') {
        return res.status(413).json({ error: 'Request body too large.' });
    }
    if (err?.type === 'entity.parse.failed') {
        return res.status(400).json({ error: 'Request body must be valid JSON.' });
    }
    return next(err);
}
