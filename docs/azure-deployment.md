# Deploying Siel Cart to Azure App Service

A low-cost live-test deployment: Azure App Service Linux (built-in PHP 8.2
image, Nginx + PHP-FPM), Aiven MySQL, Cloudflare R2 for media, and GitHub
Actions for build and deploy. Nothing here contains real credentials; every
value below is a placeholder.

The live app runs on **B1 (Basic)**, not F1 — it was upgraded at some point
for Always On, and the App Service Plan (`ASP-SielCartgroup-9fe3`) was
confirmed by `az appservice plan show` to be `Basic/B1` (1 vCPU, 1.75 GB RAM).
See "Performance notes" below before assuming F1's limits apply.

## How a deployment works

1. A push to `main` (or **Actions → Deploy to Azure App Service → Run workflow**)
   runs `.github/workflows/deploy.yml`.
2. The workflow checks that `nginx.conf` matches `default` and that
   `storage/certs/aiven-ca.pem` exists, validates Composer, runs the full test
   suite, builds the Vite assets, installs production-only Composer
   dependencies, and checks platform requirements. **Any failure stops the run
   before anything reaches Azure.**
3. It zips the app (no `.env` files, tests, `node_modules`, logs, or caches)
   to `../release.zip` and deploys it with `azure/webapps-deploy@v3`.
4. Azure starts the container and runs `startup.sh`, which installs the Nginx
   site from `default`, optionally migrates, and rebuilds Laravel's caches.
5. The workflow smoke-tests `https://<app>/up` and `https://<app>/products`,
   retrying through the cold start, and fails if either never responds.

## One-time Azure setup

In the Azure portal, on the Web App:

- **Settings → Configuration → General settings**
  - Stack: **PHP**, version **8.2**
  - Startup command: `bash /home/site/wwwroot/startup.sh`
  - HTTPS Only: **On**
  - SCM Basic Auth Publishing Credentials: **On** (needed to download a
    publish profile)
- **Settings → Environment variables → App settings**: add every setting in
  the table below, including `SCM_DO_BUILD_DURING_DEPLOYMENT=false` so Azure
  does not try to rebuild the already-built package.

Then **Overview → Download publish profile**, and in GitHub add it as the
repository secret **`AZURE_WEBAPP_PUBLISH_PROFILE`** (Settings → Secrets and
variables → Actions). The workflow deploys to the app named in
`AZURE_WEBAPP_NAME` at the top of `deploy.yml`; change it there if your app
has a different name.

## App settings

`.env.azure.example` has the full list with placeholders. Enter them as App
Settings, never as an uploaded `.env` file.

| Setting | Value |
|---|---|
| `SCM_DO_BUILD_DURING_DEPLOYMENT` | `false` |
| `APP_NAME` | `SIEL CART` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | a stable `base64:` key (see below) |
| `APP_URL` | `https://<app>.azurewebsites.net` (HTTPS) |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stderr` / `warning` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | exactly as shown in Aiven |
| `DB_SSL_REQUIRE_VERIFIED_CA` | `true` |
| `MYSQL_ATTR_SSL_CA` | `/home/site/wwwroot/storage/certs/aiven-ca.pem` |
| `QUEUE_CONNECTION` | `sync` |
| `SESSION_DRIVER` / `CACHE_STORE` | `file` / `file` (recommended below, but **not currently set** on the live app — see note) |
| `SESSION_SECURE_COOKIE` | `true` |
| `FILESYSTEM_DISK` | `r2` |
| `CLOUDFLARE_R2_ACCESS_KEY_ID`, `CLOUDFLARE_R2_SECRET_ACCESS_KEY` | R2 API token credentials |
| `CLOUDFLARE_R2_BUCKET`, `CLOUDFLARE_R2_ENDPOINT`, `CLOUDFLARE_R2_PUBLIC_URL` | see R2 below |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | your SMTP provider |
| `RUN_MIGRATIONS_ON_STARTUP` | `false` (see first deployment) |

**`APP_KEY`** — generate it once (`php artisan key:generate --show`) and never
change it. Rotating it signs every user out and makes anything encrypted with
the old key unreadable.

**Queues** — F1 runs no background worker, so `QUEUE_CONNECTION=sync` sends
queued mail and notifications (e.g. the admin invitation) inside the request.

**Sessions** — file sessions live on the instance; a redeploy or restart can
clear them and sign everyone out. That is acceptable for a single-instance
preview, not for production.

**Verified 2026-09-26**: `SESSION_DRIVER` and `CACHE_STORE` are **not actually
set** in the live app's App Settings (`az webapp config appsettings list`
confirms only `APP_DEBUG`, `DB_HOST`, `LOG_CHANNEL`, `QUEUE_CONNECTION` are
present among these). No `.env` file is deployed either (the workflow deletes
it after tests). So both fall back to `config/session.php` /
`config/cache.php`'s own default of `database`, not `file` as this table
recommends — sessions and cache reads/writes are hitting Aiven MySQL, not the
instance disk. This isn't necessarily worse (Azure's `/home` is
network-backed storage anyway, so `file` wouldn't be fast either), but it
means the setting above was never actually applied. Decide deliberately
between `file` and `database` and set the App Setting explicitly rather than
relying on the framework default.

## Aiven MySQL

- Copy **host, port, database, user and password exactly** from the Aiven
  service's *Connection information*. Aiven uses a **custom port**, not 3306.
- Download the **CA certificate** from the same page and commit it as
  `storage/certs/aiven-ca.pem` (it is public, not a secret). Do not create or
  copy one from anywhere else. See `storage/certs/README.md`.
- With `DB_SSL_REQUIRE_VERIFIED_CA=true`, `config/database.php` refuses to load
  without a readable CA and always verifies the server certificate, so the
  app fails at startup instead of silently connecting unverified.
- Allow Azure's outbound IPs in Aiven's *Allowed IP addresses* if you have
  restricted access (App Service → Networking → Outbound addresses).

## Cloudflare R2

- Create a bucket and an **R2 API token** with Object Read & Write on that
  bucket. Its access key ID and secret go in `CLOUDFLARE_R2_ACCESS_KEY_ID` /
  `CLOUDFLARE_R2_SECRET_ACCESS_KEY`.
- `CLOUDFLARE_R2_ENDPOINT` is `https://<account-id>.r2.cloudflarestorage.com`.
- `CLOUDFLARE_R2_PUBLIC_URL` is the bucket's public base URL, either the
  `r2.dev` development URL or a custom domain.
- The `r2` disk throws on a failed write, so an upload that doesn't reach R2
  fails the save instead of storing a broken path. Failed deletes after a
  committed delete are logged, not shown to the user.

## Uploads

Each image or video is limited to **10 MB** (Livewire and validation rules).
`public/.user.ini` sets PHP to `upload_max_filesize=12M` and
`post_max_size=64M` (five 10 MB photos in one request plus overhead), and
Nginx allows `client_max_body_size 64M`. If Azure ever stops honouring
`.user.ini`, set the App Setting `PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d:/home/site/ini`
and put the same values in `/home/site/ini/uploads.ini` over SSH.

## First deployment and migrations

1. Complete the one-time setup, commit the Aiven CA, and make sure the test
   suite passes locally (`php artisan test`).
2. Set `RUN_MIGRATIONS_ON_STARTUP=true` in App Settings.
3. Push to `main` (or run the workflow). On start, `startup.sh` runs
   `php artisan migrate --force --isolated`, retrying up to five times, ten
   seconds apart, and aborts the start if the database never accepts it.
4. Watch the **Log stream** until you see the migrations finish and the smoke
   tests pass, then check `https://<app>/up` and `https://<app>/products`.
5. Set `RUN_MIGRATIONS_ON_STARTUP` back to `false`. Turn it on again only for
   a deployment that adds migrations.

To create the first admin account, open **Development Tools → SSH** and run
`php artisan db:seed --class=SystemSetupSeeder --force`, then sign in and
change the seeded password straight away. Do **not** run the full
`DatabaseSeeder` there: `PermanentProductSeeder` truncates the product tables.

## Logs and verification

- **Monitoring → App Service logs**: turn on *Application logging
  (Filesystem)*. Laravel logs to `stderr`, so errors show up in the container log.
- **Monitoring → Log stream** shows `startup.sh` output (Nginx check,
  migrations, cache commands) and application errors live. From a terminal:
  `az webapp log tail --name <app> --resource-group <resource-group>`.
- `https://<app>/up` returns 200 when Laravel boots;
  `https://<app>/products` confirms database-backed pages render.

## Rolling back

Deployments are whole packages built from one commit, so roll back by
redeploying a known-good one:

- **Actions → Deploy to Azure App Service →** open the last good run →
  **Re-run all jobs**, or
- **Run workflow** and pick a known-good branch or tag.

Migrations are not rolled back automatically. If the bad release ran a
migration, reverse it deliberately (`php artisan migrate:rollback` over SSH)
before or after redeploying, depending on the change.

## Performance notes (verified 2026-09-26)

The storefront was compared against an external reference site that returned
in ~80ms TTFB versus ours at 300ms-plus warm and multiple seconds cold. What
was checked, on the live subscription, before touching anything:

- **Plan**: `ASP-SielCartgroup-9fe3` is `Basic/B1` (1 vCPU, 1.75 GB RAM),
  Always On is `true`, `numberOfWorkers: 1` (single instance, no scale-out).
- **The Node chatbot app (`sielcart-chatbot`) runs on the same plan** as
  `sielcart` — confirmed via `az webapp show --query serverFarmId` on both.
  They share one vCPU.
- **HTTP/2 was off** (`http20Enabled: false`) and has been turned on
  (`az webapp config set --http20-enabled true`); `httpsOnly` and
  `minTlsVersion` (1.2) were left untouched. Confirmed via browser
  `PerformanceNavigationTiming.nextHopProtocol === "h2"` after the change.
- **A/B test**: `sielcart-chatbot` was stopped, `/up` (a zero-DB, zero-logic
  route) was measured warm before/during/after, then the chatbot was
  restarted. Warm `/up` was ~300-450ms **with the chatbot running** and
  ~310-440ms **with it stopped** — no measurable difference. **The idle
  chatbot is not starving the storefront of CPU**; it only costs CPU while
  actively answering a chat request, which is intermittent, not constant
  contention. Don't assume moving it to its own plan will fix the baseline.
- **The real floor**: even `/up` — no database, no Livewire, nothing — sits
  around 300-450ms warm on this plan, and any process restart (a config
  change, a redeploy, or the app being swapped back in after going idle)
  produces single-digit-second cold starts (observed up to ~12s on `/up`
  right after a config-triggered restart) while PHP-FPM and OPcache warm back
  up on a single shared vCPU. That warm-up cost is inherent to one vCPU
  compiling and autoloading Laravel's vendor tree; more cores (B2+) would
  shorten it, but the 300-450ms *warm* floor is the more telling number
  because a real customer clicking around mid-session is hitting warm
  requests, not cold ones.
- **Not verified** (couldn't check without a shell into the container, which
  this environment's safety sandbox blocks — retrieving the publish profile
  or SSH credentials counts as credential materialization): whether OPcache
  is actually enabled/tuned in the **PHP-FPM** pool serving requests, versus
  just the CLI SAPI (the two can load different `php.ini`s on the same
  image). Azure's built-in Linux PHP 8.2 image ships OPcache on by default,
  but that's an assumption, not a measurement. To check it yourself: **Azure
  Portal → sielcart → Development Tools → SSH → Go** (a real terminal inside
  the running container, not a separate sandbox), then run `ps aux | grep
  php-fpm` to find the FPM binary's path and run `<that path> -i | grep -i
  opcache` — the FPM binary's own `-i` dump reflects the FPM SAPI's config,
  which plain `php -i` does not. Confirm `opcache.enable => On => On` and
  ideally `opcache.validate_timestamps => Off` for production (files never
  change between deploys, so timestamp checks on every request are wasted
  work). For live runtime stats (hit rate, memory used), drop a short-lived,
  token-gated script into `public/` in that same SSH session that calls
  `opcache_get_status()`, hit it once over HTTPS, then delete it immediately
  — nginx's `location ~ \.php$` block (see `default`) forwards any existing
  `.php` file under the docroot straight to the live FPM pool, so this is the
  only way to see real request-time OPcache behavior rather than static config.
- **Aiven MySQL is on the Developer plan** (confirmed directly, not the
  legacy Free tier). Developer is a paid, dedicated-resource plan and does
  **not** auto-suspend on inactivity — that pausing behavior is specific to
  Aiven's old Free tier. This rules out "DB waking from suspension" as the
  explanation for the occasional large spikes seen on DB-touching routes like
  `/products`; those spikes are more likely just the same single-vCPU
  App Service CPU contention described above compounding on a route that also
  has to do DB + Livewire work, not a separate database-side cold start.
  Aiven's region relative to Malaysia West (cross-region network hops add
  fixed per-query latency) is still unverified — worth checking in the Aiven
  console if `/products` keeps measurably outrunning `/up` by more than the
  query cost alone would suggest.

**Conclusion**: the gap versus a fast reference site is not missing code
optimization (assets are already trimmed, gzip's on, N+1s are already guarded
against per this file's architecture notes) — it's that a single shared vCPU
is simply slower per request than whatever the comparison site runs on, and
every cold start pays a multi-second PHP/OPcache warm-up tax on top of that.
Fixing the floor means more CPU (B2+) or accepting B1's ceiling; fixing cold
starts means avoiding unnecessary restarts and keeping the always-on instance
genuinely warm.

## Free-tier limits

**Azure App Service F1**: 60 CPU minutes per day, 1 GB RAM, 1 GB storage, no
Always On (the app sleeps when idle and the next request waits for a cold
start, often tens of seconds), no deployment slots, no SLA, and file
sessions/cache are lost on redeploy. **The live app is no longer on this
tier** — see "Performance notes" above — but a fresh deployment following
this doc from scratch would start here.

**Azure for Students (GitHub Student Developer Pack)**: F1 itself is free, so
starting here doesn't use your student credit. The subscription is tied to
your student status and a 12-month term (renewable while you stay eligible),
and student subscriptions can only create resources in an allowed set of
regions. If creating the App Service fails with a policy error, choose
another region. Upgrading to B1 (for Always On or more CPU) starts drawing on
the credit, and resources are disabled when the credit or term runs out — the
live app is already on B1 and therefore already drawing on the credit. Check
**Cost Management + Billing** in the portal for the current credit balance
before changing tiers again; it isn't reliably readable through the CLI for
an Azure for Students subscription. As of 2026-09-26, retail pricing for
Malaysia West was **B1 ≈ $0.017/hr (~$12.4/mo), B2 ≈ $0.034/hr (~$24.8/mo)**
— a second B1 plan for the chatbot costs the same as upgrading the shared
plan to B2, but gives the two apps dedicated CPU instead of a shared pool.

**Aiven MySQL free tier**: single node, 1 GB RAM, 1 GB storage, 76 maximum
connections, no SLA, and the service may be powered off after a period of
inactivity (power it on again in the console). Check Aiven's current plan page;
limits change. **The live database is on the Developer plan, not Free** —
paid, dedicated resources, no auto-suspend. Sizing/connection limits differ
from the free tier above; check the current numbers on the service's page in
the Aiven console rather than assuming free-tier limits apply.

**Cloudflare R2 free tier**: 10 GB-month storage plus a monthly allowance of
Class A (write) and Class B (read) operations; egress is free. The `r2.dev`
public development URL is rate-limited and not meant for production. Use a
custom domain before real traffic.

**PHP 8.2** receives security fixes only until **31 December 2026**. This
deployment stays on 8.2 as requested; test on PHP 8.3 or 8.4 next and switch
the Azure stack and `PHP_VERSION` in the workflow together.
