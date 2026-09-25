# Aiven MySQL CA certificate

Put the Aiven-issued CA certificate here as `aiven-ca.pem`. It is **not**
a secret (it only lets the client verify the server, it doesn't
authenticate the client) — safe to commit. Never create, copy, or
substitute a certificate from anywhere other than your own Aiven service.

Download it from the Aiven console: your MySQL service → **Overview** →
**Connection information** → **CA certificate** (download button).

## How it is used

`config/database.php` resolves the CA for the `mysql`/`mariadb` connections:

1. `MYSQL_ATTR_SSL_CA`, if set. A relative path is read from the project
   root; absolute Linux (`/home/...`) and Windows (`C:\...`) paths are used
   as given.
2. Otherwise `storage/certs/aiven-ca.pem`, when the file is readable.

When a CA is configured, PDO verifies the server certificate against it
(`DB_SSL_VERIFY_SERVER_CERT` defaults to on).

With **`DB_SSL_REQUIRE_VERIFIED_CA=true`** (always on Azure):

- a missing or unreadable CA throws a `RuntimeException` while the config
  loads, so `php artisan config:cache` in `startup.sh` fails and the app does
  not start;
- `DB_SSL_VERIFY_SERVER_CERT=false` is refused with the same exception.

The app therefore can't fall back to an unverified connection.

## Azure

- Path on the server: `/home/site/wwwroot/storage/certs/aiven-ca.pem`, set
  as `MYSQL_ATTR_SSL_CA` in App Settings.
- The deploy workflow fails before building if this file is missing from
  the repository.
- `startup.sh` makes the file `root:www-data` with mode `640`: PHP can read
  it but cannot replace it.

## Rotation

When Aiven issues a new CA (the console will say so), download it, replace
`aiven-ca.pem`, commit, and deploy. Keep the file name unchanged so no
setting needs to change. If both the old and new CA must be trusted during a
changeover, concatenate them into the one PEM file.
