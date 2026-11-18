<?php

declare(strict_types=1);

$panelTitle = 'Tickets de Suporte';
$panelSection = 'admin-tickets';
$panelLead = 'Gestão completa dos chamados dos clientes.';
$panelScripts = ['assets/js/admin-tickets.js'];
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
    class="support-page admin-tickets"
    data-admin-tickets
    data-csrf="<?= e($csrf) ?>"
    data-api="<?= e(url('/api/admin/tickets')) ?>"
    data-attach-api="<?= e(url('/api/painel/tickets-attachment')) ?>"
>
    <div class="admin-ticket-kpis" data-admin-kpis>
        <article class="gh-card invoice-kpi"><div class="invoice-kpi-top"><span>Abertos</span></div><strong data-kpi="open">0</strong></article>
        <article class="gh-card invoice-kpi"><div class="invoice-kpi-top"><span>Em atendimento</span></div><strong data-kpi="in_progress">0</strong></article>
        <article class="gh-card invoice-kpi"><div class="invoice-kpi-top"><span>Aguardando cliente</span></div><strong data-kpi="awaiting_client">0</strong></article>
        <article class="gh-card invoice-kpi"><div class="invoice-kpi-top"><span>Resolvidos</span></div><strong data-kpi="resolved">0</strong></article>
        <article class="gh-card invoice-kpi"><div class="invoice-kpi-top"><span>Urgentes</span></div><strong data-kpi="urgent">0</strong></article>
    </div>

    <div class="support-workspace is-admin">
        <aside class="support-list-col">
            <div class="support-list-head">
                <div>
                    <h3>Fila de tickets</h3>
                    <span class="support-count-pill" data-ticket-count>0</span>
                </div>
            </div>
            <div class="support-list-filters">
                <label class="support-search">
                    <i data-lucide="search" aria-hidden="true"></i>
                    <input type="search" placeholder="Número, assunto ou e-mail…" data-filter-q autocomplete="off">
                </label>
                <select data-filter-status aria-label="Status">
                    <option value="">Todos os status</option>
                    <option value="open">Aberto</option>
                    <option value="in_progress">Em atendimento</option>
                    <option value="awaiting_client">Aguardando cliente</option>
                    <option value="resolved">Resolvido</option>
                    <option value="closed">Fechado</option>
                </select>
                <select data-filter-priority aria-label="Prioridade">
                    <option value="">Todas prioridades</option>
                    <option value="urgent">Urgente</option>
                    <option value="high">Alta</option>
                    <option value="normal">Normal</option>
                    <option value="low">Baixa</option>
                </select>
                <select data-filter-category aria-label="Categoria">
                    <option value="">Todas categorias</option>
                </select>
            </div>
            <div class="support-ticket-list" data-ticket-list></div>
        </aside>

        <section class="support-chat-col" data-chat-col>
            <div class="support-chat-empty" data-chat-empty>
                <strong>Selecione um ticket da fila</strong>
                <p>Responda clientes, altere status e registre notas internas.</p>
            </div>
            <div class="support-chat" data-chat hidden>
                <header class="support-chat-head">
                    <button type="button" class="gh-btn gh-btn-secondary support-back" data-back-list>
                        <i data-lucide="arrow-left" aria-hidden="true"></i> Voltar
                    </button>
                    <div class="support-chat-title">
                        <span class="support-id-badge" data-chat-number>#—</span>
                        <div>
                            <h3 data-chat-subject>—</h3>
                            <p>
                                <span data-chat-client>—</span>
                                · <span data-chat-category>—</span>
                                · <span data-chat-priority>—</span>
                                · <span data-chat-status>—</span>
                            </p>
                        </div>
                    </div>
                    <div class="support-chat-actions">
                        <select data-set-status aria-label="Alterar status">
                            <option value="open">Aberto</option>
                            <option value="in_progress">Em atendimento</option>
                            <option value="awaiting_client">Aguardando cliente</option>
                            <option value="resolved">Resolvido</option>
                            <option value="closed">Fechado</option>
                        </select>
                        <button type="button" class="gh-btn gh-btn-secondary" data-assign-me>Atribuir a mim</button>
                    </div>
                </header>
                <div class="support-messages" data-messages></div>
                <form class="support-composer" data-reply-form>
                    <label class="support-internal-toggle">
                        <input type="checkbox" data-internal>
                        Observação interna (invisível ao cliente)
                    </label>
                    <textarea name="body" rows="3" placeholder="Responder ao cliente…" data-reply-body maxlength="8000"></textarea>
                    <div class="support-attach-preview" data-reply-files></div>
                    <div class="support-composer-bar">
                        <label class="gh-btn gh-btn-secondary support-attach-btn">
                            <i data-lucide="paperclip" aria-hidden="true"></i> Anexar
                            <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.log,.zip,image/*,application/pdf,text/plain" hidden data-reply-attach>
                        </label>
                        <button type="submit" class="gh-btn gh-btn-primary" data-reply-submit>
                            <i data-lucide="send" aria-hidden="true"></i> Enviar
                        </button>
                    </div>
                    <p class="support-form-error" data-reply-error hidden></p>
                </form>
            </div>
        </section>
    </div>
</section>
<?php require dirname(__DIR__) . '/_footer.php'; ?>
