# GeekHost Admin Panel — Technical Plan

**Status:** Phase 1 scaffolding live — **separate `/admin` app** (not the client `/painel`)  
**Branch:** `cursor/admin-panel-9869` (from `cursor/panel-saas-redesign-9869`)  
**Date:** 2026-11-19 (America/Sao_Paulo)

### Correction
`/painel` is the **client** panel only.  
**Admin GeekHost** is standalone at **`/admin`** (sidebar ops console). DirectAdmin reseller identity `geek6923` appears in admin branding/settings; staff login uses GeekHost users seeded from `GEEKHOST_ADMIN_EMAILS`.

### Phase 1 shipped
- `/admin/login`, layout, light/dark, period filter
- Dashboard metrics from DB; vendas/clientes/hospedagens/domínios/sites/planos/configurações with real queries
- `AdminGate` + schema tables; client sidebar no longer pretends to be ops admin

---

## 1. Current system snapshot

### Stack
- PHP (no framework), SQLite/MySQL via PDO (`database/schema.sql` + `geekhost_migrate`)
- Client panel: `/painel/*` — Plus Jakarta Sans, Lucide, `panel.css` (`#01e04f`)
- Auth: `Auth` session cookie `gh_panel_session` (7h), CSRF tokens
- “Admin” today: email allowlist `GEEKHOST_ADMIN_EMAILS` via `TicketService::isAdminEmail()` — **no RBAC**
- Asaas: `AsaasClient` + `OrderService` + webhook `/api/webhooks/asaas` (idempotent)
- DirectAdmin: `DirectAdminClient` + `ProvisioningService` (flag `DIRECTADMIN_PROVISIONING_ENABLED=false` default)
- Plans: `plans.php` — `light` / `empresa` / `premium` with `da_package` → `Plano-*-GeekHost`

### Existing admin islands (reuse, do not duplicate)
| Area | UI | API |
|---|---|---|
| Support tickets | `/painel/admin/tickets` | `/api/admin/tickets` |
| DA hosting queue | `/painel/admin/hosting` | `/api/admin/hosting` |

### Data entities already present
`customers`, `users`, `orders`, `subscriptions`, `services`, `webhook_events`, `user_domains`, `user_sites`, `hosting_accounts`, `hosting_jobs`, `hosting_audit_logs`, support_* tables, `support_notifications`

### Gaps vs requested admin product
- No dedicated admin shell (sidebar of 13 sections, light/dark, period picker)
- No roles/permissions tables
- No company settings / dunning rules / email templates tables
- No admin audit log (only hosting_audit_logs)
- No admin list/search APIs for orders, users, domains, sites, subscriptions
- No dashboard metrics service / chart series
- No manual order/checkout-from-admin flow
- No DA login-as SSO token endpoint for browser
- Charts library not in repo
- Plan limits in prompt (Premium 3 / Empresa unlimited) **differ** from code (Empresa 3 / Premium 10) — needs product decision before changing checkout limits

### Hard constraints (from brief)
- Do **not** break Asaas checkout or institutional site
- Do **not** mock production data
- Do **not** auto-provision on manual client create (only after paid confirmation)
- Do **not** expose DA login keys / Asaas tokens to frontend
- Prefer dry-run / feature flags for destructive DA ops

---

## 2. Architecture decision

### Admin app location
**`/admin/*`** — completely separate from `/painel` (client).

- Own layout: `public_html/admin/_layout.php`, `assets/css/admin.css`, `assets/js/admin.js`
- Own login entry: `/admin/login` (Auth session + `admin_users` membership required)
- Client panel stays customer-only; do not bolt ops UI onto `/painel`
- Legacy `/painel/admin/tickets|hosting` can redirect into `/admin/...` later

Route pattern:
- Pages: `/admin/{section}`
- APIs: `/api/admin/{resource}` with CSRF on mutating POSTs
- Extend `router.php` map for pretty URLs (XAMPP-compatible)

### AuthZ model (new)
Replace sole allowlist check with DB-backed admins while keeping env bootstrap:

1. Seed Super Admin rows from `GEEKHOST_ADMIN_EMAILS` on migrate
2. Tables:
   - `admin_users` (user_id FK → users, role, status, invited_at, mfa_pending…)
   - `admin_roles` + `admin_permissions` + `admin_role_permissions`
   - Or simpler v1: `admin_users.role` enum + permission map in PHP (`AdminGate`)
3. `AdminGate::require(permission)` on every `/api/admin/*` and page
4. Frontend only hides menus; backend enforces

Roles from brief: `super_admin`, `financeiro`, `suporte`, `comercial`

### Design system
- Extend GeekHost tokens (`#01e04f`, graphite/black, Plus Jakarta, Lucide)
- New `admin.css` with light + dark (`data-theme`)
- Collapsible sidebar matching requested IA
- Charts: Chart.js (CDN) — lightweight, no build step
- Tables: server pagination + mobile card fallback
- Skeletons + toast pattern already used in client panel

---

## 3. Metric definitions (dashboard)

All money from `orders.amount_cents` unless noted. Asaas remains payment source of truth; local DB is the reporting mirror.

| Metric | Rule |
|---|---|
| Faturamento bruto (período) | Sum `orders.amount_cents` where `created_at` in range (all non-canceled) |
| Receita recebida | Sum where `status = paid_pending_provisioning` OR paid markers (`paid_at` in range) |
| Receita líquida estimada | Recebida − known gateway fees **only if** fee field exists; else show “estimativa indisponível” |
| MRR | Sum `subscriptions.renew_amount_cents` (or monthly equivalent) for `status=active`, normalized to monthly |
| ARR | MRR × 12 |
| Ticket médio | Recebida / count paid orders in period |
| Em aberto | Sum awaiting_payment in period |
| Vencido | Sum overdue |
| Reembolsos | Count/sum when refund status exists (omit until Asaas refund sync exists) |
| Clientes totais | `users` count |
| Clientes ativos | users with hosting_status=active OR active subscription |
| Novos clientes | users.created_at in period |
| Inadimplentes | users with subscription overdue_since set |
| Hospedagens | `hosting_accounts` by status |
| Provisionamentos pendentes/erro | hosting_jobs / accounts status |
| Domínios / sites | `user_domains` / `user_sites` counts |

**Do not** treat every registered user as paying customer.  
Compare previous period of equal length for % deltas.  
Cache dashboard aggregates 60–120s in `storage/cache` or DB table `admin_metric_snapshots`.

---

## 4. Module map (reuse vs new)

| Module | Reuse | New work |
|---|---|---|
| Dashboard | orders, users, hosting_* | `AdminMetricsService`, charts API |
| Vendas | OrderService | `listForAdmin`, detail, notes, reprocess hook |
| Faturamento | orders + subscriptions | extract/ledger views, CSV export |
| Cobranças | AsaasClient.getPayment + orders | dunning config, reminders queue |
| Clientes | Auth + customers/users | CRM list/detail, edit, block, notes |
| Pedido manual | OrderService.createCheckout | admin-initiated checkout + copy link |
| Hospedagens | ProvisioningService + DA client | expand admin hosting UI, login-as |
| Domínios/Sites | DomainSiteService | admin global lists |
| Planos | plans.php | admin CRUD overlay table `plan_overrides` **or** editable JSON settings — avoid breaking checkout prices without explicit rule |
| Admins | Auth invite tokens | admin_users + invites |
| Config | env + new `app_settings` | company, dunning, notification templates |
| Relatórios | metrics | export CSV/XLSX/PDF (CSV first; PDF via simple HTML→print or Dompdf if available) |
| Auditoria | hosting_audit_logs pattern | `admin_audit_logs` |
| Notificações admin | NotificationService pattern | `admin_notifications` |
| Busca global | — | `AdminSearchService` |

---

## 5. Schema additions (proposed)

```
admin_users (id, user_id, role, status, created_by, created_at, updated_at)
admin_audit_logs (id, admin_user_id, action, resource_type, resource_id, result, ip, correlation_id, detail_json, created_at)
admin_notifications (id, type, title, body, link, read_at, created_at)
admin_notes (id, resource_type, resource_id, admin_user_id, body, created_at)
app_settings (key, value_json, updated_at, updated_by)
dunning_rules (id, days_offset, channel, template_key, enabled)
plan_overrides (optional) — only if UI must edit prices without deploy
```

Soft-delete / deactivate flags on users: `account_status` (`active|blocked|canceled`) — additive column, default active.

Migrations: extend `schema.sql` + `geekhost_ensure_columns` (existing pattern). No destructive alters.

---

## 6. API surface (v1)

All under `/api/admin/*`, gated by `AdminGate`.

- `GET metrics/overview?from&to&granularity`
- `GET metrics/series?metric&from&to&granularity`
- `GET search?q=`
- `GET/POST sales`, `GET sales/{id}`, `POST sales/{id}/notes`, `POST sales/{id}/reprocess`
- `GET billing/summary`, `GET billing/ledger`, `GET billing/export`
- `GET/POST charges`, charge actions (copy link, refresh Asaas, remind)
- `GET/POST clients`, `GET/PATCH clients/{id}`, block/unblock, password reset invite
- `POST clients/{id}/orders` → real Asaas checkout/payment URL
- `GET/POST hostings`, suspend/unsuspend/sync/reprocess, `POST hostings/{id}/login-as` → short-lived redirect URL server-side
- `GET domains`, `GET sites`, refresh DNS
- `GET/PATCH plans` (metadata; price edits explicit)
- `GET/POST admins`, role changes (super_admin only)
- `GET/PATCH settings`
- `GET reports/{type}`, export
- `GET audit`, `GET notifications`

---

## 7. DirectAdmin / Asaas integration notes

### Asaas
- Reuse `createCheckout` / payment create for manual sales
- Webhook already marks paid → `ProvisioningStub::queue` → ProvisioningService
- Admin “marcar como pago” **forbidden** without Asaas confirmation
- Refunds: implement only after `getPayment` shows refunded status

### DirectAdmin
- Reuse client; keep provisioning flag
- Login-as: server calls `/api/session/login-as/switch`, issues one-time admin redirect via DA session cookie proxy **or** open DA URL with documented SSO if available — never send login key to browser
- Package change: CMD_API / REST only when enabled
- Delete hosting: permission `hosting.delete` + typed confirmation + audit

---

## 8. Implementation phases

### Phase 1 — Foundations (this follows plan approval)
- `AdminGate` + `admin_users` seed from env
- `_admin_layout.php`, sidebar IA, light/dark, router routes
- Move tickets + hosting into new shell links
- `admin_audit_logs` + request correlation id
- Smoke: staff login → admin home shell

### Phase 2 — Dashboard + Vendas + Faturamento
- Metrics service + date presets
- Chart.js series endpoints
- Sales list/detail (real orders)
- Billing summary + CSV export
- Recent activity feed from orders + hosting_audit + webhook_events

### Phase 3 — Clientes + pedido manual + checkout link
- CRM list/profile tabs
- Create client (no provision)
- Manual order → Asaas link “Copiar link”
- Notes + block/unblock + password setup resend

### Phase 4 — Hospedagens + Domínios + Sites
- Expand hosting admin (filters, suspend/reactivate gated)
- Global domains/sites admin views
- DA sync + usage when API allows
- Login-as investigation/implement if supported

### Phase 5 — Cobranças + régua
- Charges board from overdue/awaiting orders
- Configurable dunning offsets in `app_settings` / `dunning_rules`
- Cron reminders (idempotent), no auto-suspend beyond existing 2-month policy unless settings say otherwise

### Phase 6 — Planos + Admins + Config + Relatórios + Auditoria
- Plans admin UI bound to `plans.php` + optional overrides
- Admin invites/roles
- Company/DA/Asaas status panels (secrets masked)
- Report exports
- Full audit page + admin notifications center
- Global search

### Phase 7 — Hardening
- Automated tests (mocks for Asaas/DA) covering the 20 required cases
- Permission negative tests
- Responsive QA
- No live DA create/delete in CI

---

## 9. Testing strategy

- PHP scripts under `tests/admin_*.php` (same pattern as `tests/directadmin_provisioning_test.php`) until PHPUnit is introduced
- Mock `AsaasClient` / `DirectAdminClient` transports
- SQLite temp DB per run
- Never hit production DA mutate in tests

---

## 10. Risks & open decisions

1. **Plan limits mismatch** (brief vs `plans.php`) — confirm before changing checkout limits.
2. **Net revenue / fees** — Asaas fee fields may be incomplete; show gross + “líquido indisponível” until fee sync exists.
3. **DA login-as** — depends on reseller permission; fallback = open DA URL without SSO.
4. **Excel/PDF** — ship CSV first; XLSX/PDF as follow-up if libraries are acceptable.
5. **MFA for admins** — invite + password setup first; MFA as phase-6+ if TOTP library approved.
6. **GitHub app** — ManagePullRequest currently blocked; continue pushing branch `cursor/panel-saas-redesign-9869` (or new `cursor/admin-panel-9869` if preferred for isolation).

---

## 11. Non-goals (explicit)

- Redesigning public marketing site
- Rewriting client `/painel` UX beyond a staff link into admin
- Mock arrays as production data sources
- Enabling live DA provisioning without existing flag/authorization
- Marking orders paid without Asaas confirmation

---

## 12. Next step after plan approval

Start **Phase 1** on branch (prefer `cursor/admin-panel-9869` stacked on current redesign branch, or continue same branch if coordinator prefers single PR): AdminGate + admin layout shell + route map + seed admins + migrate tickets/hosting nav into the new shell.
