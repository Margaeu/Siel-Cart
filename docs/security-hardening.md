# Security hardening: rollout steps and proposals

What changed in the code (October 2026), what still has to be done in the
Azure and Railway dashboards, and what has been proposed but deliberately
not built yet. Nothing here contains real credentials.

Status keys: **Done in code** = merged, tested; **Ops** = someone with
dashboard access must do it; **Proposal** = not built, needs approval.

---

## 1. Chatbot service token (Done in code, Ops to switch on)

**Problem.** The chatbot has a public Railway URL and accepted any `POST
/api/chat` from anywhere, with `cors()` allowing every origin. Laravel's
`throttle:10,1` and 100-character cap only cover traffic that goes through
Laravel, so a direct caller could spend the OpenRouter quota and query the
Aiven catalogue without limit.

**What the code does now**

- `chatbot-server/lib/http-guard.js` checks `Authorization: Bearer <token>`
  against `CHATBOT_SERVICE_TOKEN` (constant-time compare). A wrong or missing
  token gets `401` **before** the body is parsed, the database is queried, or
  a model is called.
- `Api\ChatController` sends the token when its own `CHATBOT_SERVICE_TOKEN` is
  set (`config('services.chatbot.token')`).
- CORS middleware removed: only Laravel calls the service, server to server.
- 32 KB body limit, the same field rules as `ChatController` (message ≤ 100
  characters), and a per-minute ceiling on authenticated calls
  (`CHATBOT_RATE_LIMIT_PER_MINUTE`, default 120).
- Guests no longer see `AI Connection Failed: <exception text>`. They get the
  friendly "assistant is temporarily unavailable" message, and the detail goes
  to the Laravel log. A `401` from the chatbot is logged as a token mismatch.

**Enforcement is opt-in.** With no token set, the chatbot runs open exactly
as before and logs a `SECURITY:` warning at boot in production. Deploying this
code changes nothing until the variable is set on **both** services.

**Switching it on (Ops)**

1. Generate a secret once:
   `node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"`
2. **Azure first.** Web App → Settings → Environment variables → add
   `CHATBOT_SERVICE_TOKEN=<secret>`, then apply (this restarts the app). Laravel
   now sends the header; the open chatbot ignores it, so chat keeps working.
3. **Railway second.** chatbot service → Variables → add the same
   `CHATBOT_SERVICE_TOKEN=<secret>`, then redeploy. The chatbot now rejects
   anything without it.
4. Check: open the storefront chat and ask a question → normal answer. From
   any terminal, `curl -X POST https://<service>.up.railway.app/api/chat -H
   "Content-Type: application/json" -d '{"message":"hi"}'` → `401`.
5. If chat shows "temporarily unavailable" after step 3, the two values
   differ. The Laravel log says `CHATBOT_SERVICE_TOKEN differs ...`. Fix the
   value, or remove the Railway variable to fall back to open mode.

Doing step 3 before step 2 breaks chat until step 2 is done. To rotate the
secret, repeat the steps with a new value, in the same order.

## 2. Health endpoints (Done in code, Ops for monitors)

| URL | Purpose | Touches |
|---|---|---|
| `https://<app>/up` | Laravel liveness | nothing |
| `https://<app>/health/ready` | Laravel readiness | one `select 1`; no session, no chatbot |
| `https://<service>.up.railway.app/health` | chatbot liveness | nothing (never the model) |
| `https://<service>.up.railway.app/health/ready` | chatbot readiness, token required | one `SELECT 1`; never the model |

None of them can spend OpenRouter quota. Suggested (Ops, unverified): an
external uptime check on `/health/ready` and the chatbot's `/health` every 5
minutes, and Railway's health check path set to `/health`.

## 3. Read-only database user for the chatbot (Proposal / Ops)

The chatbot currently connects to Aiven with the admin account (`avnadmin` in
`chatbot-server/.env.example`). It only ever reads. These are exactly the
tables and columns `chatbot-server/lib/catalog.js` queries:

```sql
CREATE USER 'sielcart_chatbot'@'%' IDENTIFIED BY '<generate a strong password>';

GRANT SELECT ON defaultdb.categories       TO 'sielcart_chatbot'@'%';
GRANT SELECT ON defaultdb.products         TO 'sielcart_chatbot'@'%';
GRANT SELECT ON defaultdb.product_variants TO 'sielcart_chatbot'@'%';

-- Popularity and ratings only: no customer, pickup or claimant columns.
GRANT SELECT (id, status, deleted_at)           ON defaultdb.orders      TO 'sielcart_chatbot'@'%';
GRANT SELECT (order_id, product_id, quantity)   ON defaultdb.order_items TO 'sielcart_chatbot'@'%';
GRANT SELECT (product_id, rating, is_approved)  ON defaultdb.reviews     TO 'sielcart_chatbot'@'%';
```

Then set `DB_USERNAME` / `DB_PASSWORD` on the Railway service to the new user
and redeploy. Check with `GET /health/ready` and a product question in chat.
Re-check the grants whenever `catalog.js` starts reading a new column: a
missing grant shows up as "I can't reach our product catalog". Aiven manages
users in its console (Users tab); whether it allows column-level `GRANT` from
a SQL client on this plan has **not been verified**.

## 4. Security headers (Done in code)

`App\Http\Middleware\SecurityHeaders`, global, so the Filament panel gets them
too:

- `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: strict-origin-when-cross-origin`: enforced.
- `Strict-Transport-Security: max-age=31536000`, only on HTTPS requests (Azure
  is HTTPS-only). No `includeSubDomains` or `preload`.
- `Content-Security-Policy-Report-Only`: **reports, does not block.** Allows
  self, `fonts.bunny.net`, the R2 public URL, `data:`/`blob:` images, and
  `'unsafe-inline'`/`'unsafe-eval'` (Livewire, Alpine and Filament need them).
  Not sent while `npm run dev` is running hot, because browsers reject Vite's
  `http://[::1]:5173` origin in a CSP.

Checked on 2026-10-03 in Chromium against a scratch SQLite copy, with the
production-shaped policy: `/`, `/products`, `/cart`, `/login`, `/about` and
`/admin/login` produced no CSP reports, and a deliberate cross-origin image
did (so reporting works). Logged-in admin pages and pages with real R2 media
were **not** exercised. To enforce: watch the browser console on the live
site for "violates the following Content Security Policy directive" while
using the admin panel and storefront. Once that has been clean for a release,
rename the header to `Content-Security-Policy` in the middleware.

## 5. Admin-safety guards (Done in code)

- **Deleting administrators:** nobody can delete their own account, and no
  single or bulk delete may remove every remaining active super admin
  (`User::selectionAccessLossBlockedReason()`). Shield lets `super_admin` past
  every policy check, so these guards sit on `EditUser` and `UsersTable`
  themselves.
- **Active theme** can't be deleted, singly or in bulk; activate another first.
- **Delete confirmations** for orders, banners and themes now say what is lost
  and whether it can be restored. Order deletes say plainly that deleting is
  not cancelling: no restock, no email.

## 6. Admin MFA (Proposal, after the 2026-10-05 defense)

Filament 4.12 has TOTP app authentication built in, and its dependencies
(`pragmarx/google2fa`, `pragmarx/google2fa-qrcode`) are already installed. No
new package is needed. It does need a schema change, which is why it is not
built yet:

1. Migration: add to `users` `app_authentication_secret` (text, nullable) and
   `app_authentication_recovery_codes` (text, nullable).
2. `User` implements `Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication`
   and `HasAppAuthenticationRecovery`. Cast both columns `encrypted` /
   `encrypted:array`, add them to `$hidden`, and keep them out of
   `getActivitylogOptions()`.
3. `AdminPanelProvider`: `->profile()` (the panel has no profile page today, and
   MFA is set up from it), then
   `->multiFactorAuthentication([AppAuthentication::make()->recoverable()])`.
   Leave `isRequired` false at first. Make it required once every admin has
   enrolled.
4. **Enrollment:** each admin opens Profile → "Set up authenticator app", scans
   the QR code (Google Authenticator, Microsoft Authenticator, 1Password …),
   confirms a code, and stores the recovery codes offline.
5. **Recovery:** a lost phone is handled with a recovery code at login. For an
   admin with neither, a super admin clears the two columns for that user (a
   small `php artisan admin:reset-mfa {email}` command over Azure SSH, logged to
   the activity log). The last super admin's recovery codes must be kept
   somewhere other than their phone.
6. Tests: login asks for a code once enrolled, a recovery code works once, and
   the reset command clears both columns.

## 7. Recorded decisions

- **Customer and admin share one session** (customer logout also ends an admin
  login in the same browser). Accepted as is on 2026-10-03; the SRS is final.
  Not a defect to fix.
- **The chatbot keeps no conversation state** (no chat table, no history).
  CLAUDE.md's old "conversation memory" paragraph was wrong and has been
  removed.
