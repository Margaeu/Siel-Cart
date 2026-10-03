import dotenv from 'dotenv';
dotenv.config();

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import OpenAI from 'openai';
import mysql from 'mysql2/promise';

import { createApp } from './lib/app.js';
import { createCatalog } from './lib/catalog.js';
import { rateLimitFromEnv, resolveServiceToken } from './lib/http-guard.js';
import { createLlm } from './lib/llm.js';

// The matching, retrieval and grounding rules live in ./lib so they can be
// tested without a socket, a database or an API key (see ./tests), and so do
// the routes and the request guards (./lib/app.js, ./lib/http-guard.js). This
// file is only the wiring: process configuration and the connection pool.

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Who may call /api/chat. See resolveServiceToken for why a missing token is a
// warning rather than a refusal to start.
const { token: serviceToken, warning: tokenWarning } = resolveServiceToken();
if (tokenWarning) {
    console.warn(tokenWarning);
}

// 1. Initialize OpenRouter Client
const openrouter = new OpenAI({
    baseURL: 'https://openrouter.ai/api/v1',
    apiKey: process.env.OPENROUTER_API_KEY || '',
    defaultHeaders: {
        'HTTP-Referer': 'http://localhost:3000',
        'X-Title': 'Siel Cart E-Commerce Assistant',
    }
});

// 2. Initialize Database Connection Pool
const dbHost = process.env.DB_HOST || 'localhost';
const isLocalDbHost = ['localhost', '127.0.0.1', '::1'].includes(dbHost);

// Aiven requires TLS, and Node's default trust store doesn't include its CA,
// so `rejectUnauthorized: true` with no `ca` fails the handshake on every
// connection attempt. That failure used to be swallowed and answered with a
// hardcoded product list -- which is why production was serving fake "CLSU
// Notebook / Siel Cart Lanyard / UBAP Mug" recommendations with no visible
// error. Mirrors config/database.php's CA lookup so the same committed cert
// works for both services; see chatbot-server/certs/README.md for why there
// are two copies.
function loadDbSslCa() {
    const configured = (process.env.MYSQL_ATTR_SSL_CA || '').trim();
    const candidates = configured
        ? [path.isAbsolute(configured) ? configured : path.resolve(__dirname, configured)]
        : [
            path.resolve(__dirname, 'certs/aiven-ca.pem'),
            path.resolve(__dirname, '../storage/certs/aiven-ca.pem'),
        ];

    for (const candidate of candidates) {
        try {
            return fs.readFileSync(candidate, 'utf8');
        } catch {
            // try next candidate
        }
    }

    return null;
}

const dbSslCa = isLocalDbHost ? null : loadDbSslCa();

if (!isLocalDbHost && !dbSslCa) {
    console.warn(
        'MySQL SSL CA not found (checked MYSQL_ATTR_SSL_CA and chatbot-server/certs/aiven-ca.pem). ' +
        'The connection to Aiven will fail TLS verification and product recommendations will be unavailable.'
    );
}

const dbPool = mysql.createPool({
    host: dbHost,
    port: process.env.DB_PORT || 3306,
    user: process.env.DB_USER || process.env.DB_USERNAME || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || process.env.DB_DATABASE || 'siel_cart',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
    ...(isLocalDbHost ? {} : { ssl: dbSslCa ? { ca: dbSslCa, rejectUnauthorized: true } : { rejectUnauthorized: true } })
});

const catalog = createCatalog(dbPool);

// With no API key there is no model to call, and the pipeline answers from the
// FAQ and the keyword rules alone. That is a degraded service, not a broken
// one, so it starts rather than exiting -- but it says so once at boot, because
// silently keyword-only was previously indistinguishable from working.
const hasApiKey = Boolean(process.env.OPENROUTER_API_KEY);
if (!hasApiKey) {
    console.warn('OPENROUTER_API_KEY is not set: answering from the FAQ and keyword rules only, with no language model.');
}
const llm = hasApiKey ? createLlm({ client: openrouter }) : null;

const app = createApp({
    catalog,
    llm,
    serviceToken,
    pingDatabase: () => dbPool.query('SELECT 1'),
    rateLimitPerMinute: rateLimitFromEnv(),
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server listening on port ${PORT}`);
});
