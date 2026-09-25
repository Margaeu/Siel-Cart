# Deploying Siel Cart to Azure App Service (F1 preview)

A low-cost live-test deployment: Azure App Service Linux F1 (built-in PHP 8.2
image, Nginx + PHP-FPM), Aiven MySQL, Cloudflare R2 for media, and GitHub
Actions for build and deploy. Nothing here contains real credentials; every
value below is a placeholder.

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
| `SESSION_DRIVER` / `CACHE_STORE` | `file` / `file` |
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

## Free-tier limits

**Azure App Service F1**: 60 CPU minutes per day, 1 GB RAM, 1 GB storage, no
Always On (the app sleeps when idle and the next request waits for a cold
start, often tens of seconds), no deployment slots, no SLA, and file
sessions/cache are lost on redeploy.

**Azure for Students (GitHub Student Developer Pack)**: F1 itself is free, so
this setup doesn't use your student credit. The subscription is tied to your
student status and a 12-month term (renewable while you stay eligible), and
student subscriptions can only create resources in an allowed set of regions.
If creating the App Service fails with a policy error, choose another region.
Upgrading to B1 (for Always On or more CPU) starts drawing on the credit, and
resources are disabled when the credit or term runs out. Check **Cost
Management** in the portal before changing tiers.

**Aiven MySQL free tier**: single node, 1 GB RAM, 1 GB storage, 76 maximum
connections, no SLA, and the service may be powered off after a period of
inactivity (power it on again in the console). Check Aiven's current plan page;
limits change.

**Cloudflare R2 free tier**: 10 GB-month storage plus a monthly allowance of
Class A (write) and Class B (read) operations; egress is free. The `r2.dev`
public development URL is rate-limited and not meant for production. Use a
custom domain before real traffic.

**PHP 8.2** receives security fixes only until **31 December 2026**. This
deployment stays on 8.2 as requested; test on PHP 8.3 or 8.4 next and switch
the Azure stack and `PHP_VERSION` in the workflow together.
