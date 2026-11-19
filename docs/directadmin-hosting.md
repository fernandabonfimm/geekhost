# GeekHost × DirectAdmin hosting automation

Branch: `cursor/panel-saas-redesign-9869`  
Implemented: DirectAdmin client, post-payment provisioning queue, DNS/SSL cron, WP Softaculous path, admin panel, mocks.

## Env vars (server `.env` only — never commit secrets)

| Variable | Purpose |
|---|---|
| `DIRECTADMIN_URL` | e.g. `https://br74-da.valueserver.net.br:2222` |
| `DIRECTADMIN_USERNAME` | Reseller DA username (required for Basic Auth) |
| `DIRECTADMIN_LOGIN_KEY` | Login key value used as **password** (DA login-key auth) |
| `DIRECTADMIN_PASSWORD` | Fallback if login key empty |
| `DIRECTADMIN_PROVISIONING_ENABLED` | `false` default — set `true`/`1` to allow create/suspend/ssl/wp |
| `DIRECTADMIN_DRY_RUN` | `1` = log mutating ops, return success without calling DA |
| `DIRECTADMIN_VERIFY_SSL` | `1` default |
| `DIRECTADMIN_PACKAGE_LIGHT/EMPRESA/PREMIUM` | Map GeekHost plans → DA package names |
| `DIRECTADMIN_PROVISIONAL_DOMAIN_SUFFIX` | Optional `{user}.suffix` only if GeekHost DNS is real |
| `DIRECTADMIN_PROVISIONAL_DNS_READY` | Must be `1` to use provisional subdomain |
| `CRON_HOSTING_TOKEN` | Token for HTTP cron |

Flip automation:

```
DIRECTADMIN_PROVISIONING_ENABLED=true
DIRECTADMIN_DRY_RUN=1   # first smoke
# then DRY_RUN=0 after ValueServer whitelist + package names confirmed
```

Credentials live in the private store brief; do not put real keys in git.

## Swagger findings (`/static/swagger.json`, OpenAPI 2.0, ~274 paths)

**Auth:** `DaBasicAuth` (HTTP Basic). Login keys: username = reseller user, password = login key.

**Present in modern REST:**
- `GET /api/users/list`, `GET /api/users/{username}/config|usage`
- `GET /api/reseller-packages`
- `GET|POST /api/domain-tls/domains/...` (ACME provision, certs, dry-run)
- `POST /api/wordpress/install-quick` (Softaculous/WP; user session context)
- `POST /api/session/login-as/switch|return`
- `POST /api/login`

**Not in modern REST (classic CMD_API required):**
- Create hosting user → `CMD_API_ACCOUNT_USER` (`action=create`)
- Suspend/unsuspend → `CMD_API_SELECT_USERS`
- Add domain → `CMD_API_DOMAIN`
- Package list fallback → `CMD_API_PACKAGES_USER`

## Live probe (this agent environment)

- Reseller username: `geek6923` (contact: `geekhostdigital@gmail.com`). Login key/password stay in private credentials note / local `.env` only.
- Auth OK with Basic Auth (`geek6923` + login key): `GET /api/users/list` → HTTP 200.
- Egress IP observed: `191.23.127.254` (Imunify360 not blocking this run after correct username).
- Reseller packages confirmed and linked in checkout catalog (`plans.php` → `da_package` / `geekhost_plan_da_package()`):
  - checkout `light` → `Plano-Light-GeekHost`
  - checkout `empresa` → `Plano-Empresa-GeekHost`
  - checkout `premium` → `Plano-Premium-GeekHost`
- Local `.env`: `DIRECTADMIN_PROVISIONING_ENABLED=false`, `DIRECTADMIN_DRY_RUN=1`. Dry-run `createUser` returns success without calling DA mutate.
- **Live create/suspend still disabled** until explicitly authorized after dry-run smoke.

## Code map

| Piece | Path |
|---|---|
| Client | `public_html/includes/DirectAdminClient.php` |
| Provisioning | `public_html/includes/ProvisioningService.php` |
| Stub hook | `public_html/includes/ProvisioningStub.php` → delegates to service |
| Asaas webhook | `public_html/api/webhooks/asaas.php` → `OrderService::handleWebhook` → `onOrderPaid` → `ProvisioningStub::queue` |
| Instant card pay | `OrderService` create path also queues provisioning when status is paid |
| Overdue suspend | `OrderService::enforceOverdueSuspension` → DA suspend job |
| Domains/sites | `DomainSiteService` queues `add_domain` / `wordpress` |
| Cron (~5 min) | `public_html/cron/hosting.php` |
| Admin UI | `public_html/painel/admin/hosting.php` + `api/admin/hosting.php` |
| Schema | `hosting_accounts`, `hosting_jobs`, `hosting_audit_logs` (+ `ssl_status` on domains/sites) |
| Mock tests | `php tests/directadmin_provisioning_test.php` |

## Paid → hosting flow

1. Asaas confirms payment (webhook re-fetches charge; amount already validated in OrderService).
2. Order → `paid_pending_provisioning`; `services` row created; `ProvisioningService::queueFromOrder`.
3. Creates `hosting_accounts` (unique DA username, encrypted password) + `provision` job.
4. If no customer domain and provisional DNS is not configured → status `blocked_no_domain` (no invented DNS).
5. Cron / admin “Rodar fila” runs jobs only when `DIRECTADMIN_PROVISIONING_ENABLED` is on.
6. On success: account `active`, order `provisioning_status=done`, user `hosting_status=active`, notification + welcome email.
7. Domains: DNS poll → when ns1/ns2.geekhost.com.br OK → SSL job via `domain-tls` → `ssl_status=active` only after certs confirmed.
8. Sites: blank = default DA docroot; WordPress = login-as + `/api/wordpress/install-quick`.

## Domain limits

Backend uses `geekhost_plan_domain_limit()` from `plans.php` (currently Light 1 / Empresa 3 / Premium 10). Brief mentioned different marketing numbers — product catalog in code is the source of truth.

## Admin

Staff emails in `GEEKHOST_ADMIN_EMAILS` see **Hospedagem DA** in the panel sidebar: accounts, jobs, audit, reprocess, run queue, connectivity probe (no secrets returned).

## Blocked / needs ValueServer

1. Whitelist automation server IP in Imunify360.
2. Confirm reseller username for login-key auth.
3. Confirm reseller can: create users, suspend, add domains, login-as, TLS ACME, WordPress install.
4. Align `DIRECTADMIN_PACKAGE_*` with real package names from `GET /api/reseller-packages`.
5. Enable `DIRECTADMIN_PROVISIONING_ENABLED=true` only after dry-run smoke.

## Cron example

```
*/5 * * * * php /path/to/public_html/cron/hosting.php
# or
*/5 * * * * curl -fsS "https://geekhost.com.br/cron/hosting.php?token=$CRON_HOSTING_TOKEN"
```
