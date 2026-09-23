# Aiven MySQL CA certificate

Put the Aiven-issued CA certificate here as `aiven-ca.pem`. It is **not**
a secret (it only lets the client verify the server, it doesn't
authenticate the client) — safe to commit.

Download it from the Aiven console: your MySQL service → **Overview** →
**Connection information** → **CA certificate** (download button).

`config/database.php` resolves `storage/certs/aiven-ca.pem` automatically
for the `mysql`/`mariadb` connections, so no `MYSQL_ATTR_SSL_CA` env var is
needed on any machine (local, CI, or Azure App Service) once the file is
here. Set `MYSQL_ATTR_SSL_CA` only if a specific machine needs to point at
a different cert.
