<?php

declare(strict_types=1);

$panelTitle = 'Suporte Técnico';
$panelSection = 'suporte';
$panelLead = '';
$panelScripts = ['assets/js/suporte.js'];
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/TicketService.php';

$isStaff = TicketService::isAdminEmail((string) ($panelUser['email'] ?? ''));
$openTicketId = trim((string) ($_GET['ticket'] ?? ''));
?>
<section
    class="support-page"
    data-support
    data-csrf="<?= e($csrf) ?>"
    data-api="<?= e(url('/api/painel/tickets')) ?>"
    data-attach-api="<?= e(url('/api/painel/tickets-attachment')) ?>"
    data-user-name="<?= e((string) ($panelUser['name'] ?? 'Cliente')) ?>"
    data-user-photo="<?= e(!empty($panelUser['photoPath']) ? asset((string) $panelUser['photoPath']) : '') ?>"
    data-open-ticket="<?= e($openTicketId) ?>"
>
    <article class="support-hero">
        <div class="support-hero-copy">
            <h2>Suporte Técnico</h2>
            <p>Abra um ticket, acompanhe o atendimento e tire suas dúvidas com nosso time.</p>
            <ul class="support-hero-pills">
                <li><i data-lucide="zap" aria-hidden="true"></i> Atendimento rápido</li>
                <li><i data-lucide="shield-check" aria-hidden="true"></i> Acompanhamento</li>
                <li><i data-lucide="history" aria-hidden="true"></i> Histórico completo</li>
            </ul>
            <button type="button" class="gh-btn gh-btn-primary" data-open-create>
                <i data-lucide="plus" aria-hidden="true"></i> Abrir novo ticket
            </button>
        </div>
        <div
            class="support-hero-bg"
            style="background-image:url('<?= e(asset('assets/images/banner-inicio-para-letras-escuras.png')) ?>')"
            role="img"
            aria-label="Banner Suporte Técnico GeekHost"
        ></div>
    </article>

    <?php if ($isStaff): ?>
        <p class="support-admin-link">
            <a class="gh-link" href="<?= e(url('/painel/admin/tickets')) ?>">
                <i data-lucide="shield" aria-hidden="true"></i> Abrir painel administrativo de tickets
            </a>
        </p>
    <?php endif; ?>

    <div class="support-tabs" role="tablist">
        <button type="button" class="support-tab is-active" role="tab" aria-selected="true" data-tab="list">
            <i data-lucide="ticket" aria-hidden="true"></i>
            Meus tickets
            <span class="support-tab-badge" data-ticket-count>0</span>
        </button>
        <button type="button" class="support-tab" role="tab" aria-selected="false" data-tab="create">
            <i data-lucide="plus" aria-hidden="true"></i>
            Abrir novo ticket
        </button>
    </div>

    <div class="support-panel is-active" data-panel-tab="list">
        <div class="support-workspace" data-workspace>
            <aside class="support-list-col" data-list-col>
                <div class="support-list-head">
                    <div>
                        <h3>Meus tickets</h3>
                        <span class="support-count-pill" data-ticket-count>0</span>
                    </div>
                    <select data-filter-status aria-label="Filtrar por status">
                        <option value="">Todos os status</option>
                        <option value="open">Aberto</option>
                        <option value="in_progress">Em atendimento</option>
                        <option value="awaiting_client">Aguardando resposta</option>
                        <option value="resolved">Resolvido</option>
                        <option value="closed">Fechado</option>
                    </select>
                </div>
                <div class="support-list-filters">
                    <label class="support-search">
                        <i data-lucide="search" aria-hidden="true"></i>
                        <input type="search" placeholder="Buscar por assunto ou número…" data-filter-q autocomplete="off">
                    </label>
                    <select data-filter-category aria-label="Filtrar por categoria">
                        <option value="">Todas as categorias</option>
                    </select>
                </div>
                <div class="support-ticket-list" data-ticket-list aria-live="polite"></div>
                <div class="support-pagination" data-pagination hidden></div>
            </aside>

            <section class="support-chat-col" data-chat-col>
                <div class="support-chat-empty" data-chat-empty>
                    <strong>Selecione um ticket</strong>
                    <p>Escolha um chamado à esquerda para ver o histórico e responder.</p>
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
                                    <span data-chat-category>—</span>
                                    · Aberto em <span data-chat-created>—</span>
                                    · <span class="support-status-dot" data-chat-status-wrap><i data-chat-status-dot></i> <span data-chat-status>—</span></span>
                                </p>
                            </div>
                        </div>
                        <div class="support-chat-actions">
                            <button type="button" class="gh-btn gh-btn-secondary" data-resolve hidden>Marcar resolvido</button>
                            <button type="button" class="gh-btn gh-btn-secondary" data-reopen hidden>Reabrir</button>
                        </div>
                    </header>
                    <div class="support-messages" data-messages></div>
                    <form class="support-composer" data-reply-form>
                        <div class="support-closed-note" data-closed-note hidden>Este ticket está encerrado. Você ainda pode consultar o histórico.</div>
                        <textarea name="body" rows="3" placeholder="Digite sua resposta…" data-reply-body maxlength="8000"></textarea>
                        <div class="support-attach-preview" data-reply-files></div>
                        <div class="support-composer-bar">
                            <label class="gh-btn gh-btn-secondary support-attach-btn">
                                <i data-lucide="paperclip" aria-hidden="true"></i> Anexar arquivo
                                <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.log,.zip,image/*,application/pdf,text/plain" hidden data-reply-attach>
                            </label>
                            <button type="submit" class="gh-btn gh-btn-primary" data-reply-submit>
                                <i data-lucide="send" aria-hidden="true"></i> Enviar resposta
                            </button>
                        </div>
                        <p class="support-form-error" data-reply-error hidden></p>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <div class="support-panel" data-panel-tab="create" hidden>
        <form class="gh-card gh-card-pad support-create" data-create-form>
            <div class="gh-card-head">
                <h3>Abrir novo ticket</h3>
            </div>
            <div class="support-form-grid">
                <label>
                    <span>Assunto *</span>
                    <input type="text" name="subject" maxlength="160" required placeholder="Resumo do problema">
                </label>
                <label>
                    <span>Categoria *</span>
                    <select name="category" required data-category-select>
                        <option value="">Selecione…</option>
                    </select>
                </label>
                <label>
                    <span>Serviço relacionado</span>
                    <select name="serviceId" data-service-select>
                        <option value="">Nenhum</option>
                    </select>
                </label>
                <label>
                    <span>Prioridade</span>
                    <select name="priority">
                        <option value="low">Baixa</option>
                        <option value="normal" selected>Normal</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
                    </select>
                    <small class="support-hint">A prioridade é uma classificação inicial e não garante prazo automático.</small>
                </label>
            </div>
            <label class="support-full">
                <span>Descrição *</span>
                <textarea name="body" rows="7" required minlength="10" maxlength="8000" placeholder="Descreva o problema com o máximo de detalhes possível…"></textarea>
            </label>
            <div class="support-full">
                <span>Anexos</span>
                <label class="gh-btn gh-btn-secondary support-attach-btn">
                    <i data-lucide="paperclip" aria-hidden="true"></i> Selecionar arquivos
                    <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.log,.zip,image/*,application/pdf,text/plain" hidden data-create-attach>
                </label>
                <div class="support-attach-preview" data-create-files></div>
                <small class="support-hint">Imagens, PDF, TXT/LOG ou ZIP até 5 MB cada (máx. 5 arquivos).</small>
            </div>
            <p class="support-form-error" data-create-error hidden></p>
            <div class="support-create-actions">
                <button type="submit" class="gh-btn gh-btn-primary" data-create-submit>
                    <i data-lucide="send" aria-hidden="true"></i> Enviar ticket
                </button>
            </div>
        </form>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
