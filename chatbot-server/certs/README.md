# Aiven MySQL CA certificate

A copy of [`storage/certs/aiven-ca.pem`](../../storage/certs/aiven-ca.pem) at
the Laravel app's repo root. Not a secret — it only lets the client verify
the server, safe to commit.

It is duplicated here (rather than read from `../storage/certs/aiven-ca.pem`)
because `chatbot-server` deploys to Railway as its own service with its own
build context, which may not include the rest of the monorepo. `server.js`
checks this path first and falls back to the Laravel copy for local dev, so
either layout works.

**Rotation:** when Aiven issues a new CA, replace it in both `storage/certs/aiven-ca.pem`
and here, then redeploy both services. Keep the file name and contents identical.
