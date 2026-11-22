<?php

declare(strict_types=1);

$panelTitle = 'Hospedagem DirectAdmin';
$panelSection = 'admin-hosting';
$panelLead = 'Contas DA, filas, SSL e auditoria — sem expor credenciais.';
require dirname(__DIR__) . '/_layout.php';
require_once dirname(__DIR__, 2) . '/includes/TicketService.php';

if (!TicketService::isAdminEmail((string) ($panelUser['email'] ?? ''))) {
    http_response_code(403);
    echo '<div class="gh-alert gh-alert-danger">Acesso restrito à equipe GeekHost.</div>';
    require dirname(__DIR__) . '/_footer.php';
    exit;
}
?>
<section
    class="admin-hosting"
    data-admin-hosting
    data-csrf="<?= e($csrf) ?>"
    data-api="<?= e(url('/api/admin/hosting')) ?>"
>
    <div class="admin-ticket-kpis" data-hosting-kpis>
        <article class="gh-card invoice-kpi">
            <div class="invoice-kpi-top"><span>Flag create/suspend</span></div>
            <strong data-kpi="flag">—</strong>
        </article>
        <article class="gh-card invoice-kpi">
            <div class="invoice-kpi-top"><span>API DA</span></div>
            <strong data-kpi="probe">—</strong>
        </article>
        <article class="gh-card invoice-kpi">
            <div class="invoice-kpi-top"><span>Contas</span></div>
            <strong data-kpi="accounts">0</strong>
        </article>
        <article class="gh-card invoice-kpi">
            <div class="invoice-kpi-top"><span>Jobs falhos</span></div>
            <strong data-kpi="failed">0</strong>
        </article>
    </div>

    <div class="panel-page-actions" style="margin-bottom:1rem;display:flex;gap:.75rem;flex-wrap:wrap">
        <button type="button" class="gh-btn gh-btn-secondary" data-run-cron>
            <i data-lucide="refresh-cw" aria-hidden="true"></i> Rodar fila agora
        </button>
        <button type="button" class="gh-btn gh-btn-secondary" data-reload>
            <i data-lucide="rotate-ccw" aria-hidden="true"></i> Atualizar
        </button>
    </div>

    <article class="gh-card" style="margin-bottom:1.25rem">
        <h3 style="margin:0 0 .75rem">Contas DirectAdmin</h3>
        <div class="gh-table-wrap">
            <table class="gh-table" data-accounts-table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>DA user</th>
                        <th>Plano</th>
                        <th>Domínio</th>
                        <th>Status</th>
                        <th>Erro</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-accounts-body>
                    <tr><td colspan="7">Carregando…</td></tr>
                </tbody>
            </table>
        </div>
    </article>

    <div style="display:grid;gap:1.25rem;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
        <article class="gh-card">
            <h3 style="margin:0 0 .75rem">Jobs</h3>
            <div data-jobs-list class="admin-hosting-list">Carregando…</div>
        </article>
        <article class="gh-card">
            <h3 style="margin:0 0 .75rem">Auditoria</h3>
            <div data-audit-list class="admin-hosting-list">Carregando…</div>
        </article>
    </div>
</section>
<style>
.admin-hosting-list{font-size:.875rem;max-height:420px;overflow:auto;display:grid;gap:.5rem}
.admin-hosting-list article{border-top:1px solid rgba(15,23,42,.08);padding-top:.5rem}
.admin-hosting-list strong{display:block}
.admin-hosting .gh-table{width:100%;border-collapse:collapse;font-size:.875rem}
.admin-hosting .gh-table th,.admin-hosting .gh-table td{padding:.55rem .4rem;text-align:left;border-bottom:1px solid rgba(15,23,42,.08);vertical-align:top}
</style>
<script>
(() => {
  const root = document.querySelector('[data-admin-hosting]');
  if (!root) return;
  const api = root.dataset.api;
  const csrf = root.dataset.csrf;
  const el = (sel) => root.querySelector(sel);

  async function load() {
    const res = await window.ghApi(api + '?action=overview');
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Falha');
    el('[data-kpi="flag"]').textContent = data.provisioningEnabled ? 'ON' : 'OFF';
    el('[data-kpi="probe"]').textContent = data.probe?.ok ? 'OK' : (data.probe?.http_status || '—');
    el('[data-kpi="accounts"]').textContent = String((data.accounts || []).length);
    const failed = (data.jobs || []).filter((j) => j.status === 'failed').length;
    el('[data-kpi="failed"]').textContent = String(failed);

    const body = el('[data-accounts-body]');
    body.innerHTML = '';
    if (!(data.accounts || []).length) {
      body.innerHTML = '<tr><td colspan="7">Nenhuma conta ainda.</td></tr>';
    } else {
      for (const a of data.accounts) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${escapeHtml(a.userEmail || a.userName || a.userId)}</td>
          <td><code>${escapeHtml(a.daUsername || '')}</code></td>
          <td>${escapeHtml(a.planCode || '')}</td>
          <td>${escapeHtml(a.primaryDomain || '—')}</td>
          <td>${escapeHtml(a.status || '')}</td>
          <td>${escapeHtml(a.lastError || '')}</td>
          <td><button type="button" class="gh-btn gh-btn-secondary" data-reprocess="${escapeHtml(a.id)}">Reprocessar</button></td>`;
        body.appendChild(tr);
      }
    }

    el('[data-jobs-list]').innerHTML = (data.jobs || []).slice(0, 40).map((j) => `
      <article>
        <strong>${escapeHtml(j.job_type)} · ${escapeHtml(j.status)}</strong>
        <span>${escapeHtml(j.da_username || j.account_public_id || '')} · tentativas ${j.attempts || 0}</span>
        <span>${escapeHtml(j.last_error || '')}</span>
      </article>`).join('') || 'Nenhum job.';

    el('[data-audit-list]').innerHTML = (data.audit || []).slice(0, 40).map((a) => `
      <article>
        <strong>${escapeHtml(a.action)} · ${escapeHtml(a.actor)}</strong>
        <span>${escapeHtml(a.da_username || a.account_public_id || '')}</span>
        <span>${escapeHtml(a.created_at || '')}</span>
      </article>`).join('') || 'Sem eventos.';
  }

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  root.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('[data-reprocess],[data-run-cron],[data-reload]');
    if (!btn) return;
    try {
      if (btn.hasAttribute('data-reload')) {
        await load();
        return;
      }
      const action = btn.hasAttribute('data-run-cron') ? 'run_cron' : 'reprocess';
      const payload = { action, csrfToken: csrf };
      if (action === 'reprocess') payload.id = btn.getAttribute('data-reprocess');
      const res = await window.ghApi(api, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Falha');
      await load();
    } catch (e) {
      alert(e.message || 'Erro');
    }
  });

  const start = () => load().catch((e) => {
    el('[data-accounts-body]').innerHTML = `<tr><td colspan="7">${escapeHtml(e.message)}</td></tr>`;
  });
  if (window.ghApi) start();
  else document.addEventListener('DOMContentLoaded', start);
})();
</script>
<?php require dirname(__DIR__) . '/_footer.php'; ?>
