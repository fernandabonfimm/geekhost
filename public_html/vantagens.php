<?php

$pageTitle = 'Vantagens GeekHost';
$pageDocumentTitle = 'Vantagens | GeekHost';
$pageDescription = 'Conheça as vantagens da hospedagem GeekHost: desempenho, suporte humano, painel cPanel, migração assistida e planos sem fidelidade.';
$currentPage = 'vantagens';
$testimonials = [
    [
        'name' => 'Mariana Costa',
        'profile' => 'Empreendedora',
        'text' => 'Contratei a GeekHost para tirar meu site do improvisado. O painel é claro, o suporte responde rápido no WhatsApp e finalmente tenho tranquilidade para focar nas vendas.',
        'image' => 'assets/images/depoimentos/Mariana%20Costa.jpg',
        'image_alt' => 'Foto ilustrativa de uma empreendedora',
        'rating' => 5,
    ],
    [
        'name' => 'Rafael Martins',
        'profile' => 'Desenvolvedor web',
        'text' => 'Como dev, preciso de ambiente estável, SSL e banco sem drama. A GeekHost entregou exatamente isso, com migração assistida e comunicação direta quando precisei ajustar DNS.',
        'image' => 'assets/images/depoimentos/Rafael%20Martins.jpg',
        'image_alt' => 'Foto ilustrativa de um desenvolvedor web',
        'rating' => 5,
    ],
    [
        'name' => 'Camila Oliveira',
        'profile' => 'Marketing digital',
        'text' => 'Uso a hospedagem para landing pages e sites de clientes. A combinação de velocidade, e-mail profissional e atendimento humano fez diferença na entrega dos projetos.',
        'image' => 'assets/images/depoimentos/Camila%20Oliveira.jpg',
        'image_alt' => 'Foto ilustrativa de uma profissional de marketing digital',
        'rating' => 5,
    ],
    [
        'name' => 'Lucas Ferreira',
        'profile' => 'Pequeno empresário',
        'text' => 'Gostei do período de teste e da ausência de fidelidade. Comecei no plano Light, cresci para o Empresa e nunca me senti preso a um contrato longo.',
        'image' => 'assets/images/depoimentos/Lucas%20Ferreira.avif',
        'image_alt' => 'Foto ilustrativa de um pequeno empresário',
        'rating' => 5,
    ],
];
require __DIR__ . '/includes/header.php';
?>

<section class="advantages-hero">
    <div class="advantages-hero-shade" aria-hidden="true"></div>
    <div class="advantages-hero-content page-width">
        <p class="eyebrow eyebrow-light"><span></span> Vantagens GeekHost</p>
        <h1>Tudo o que o seu projeto precisa para <em>estar online de verdade</em>.</h1>
        <p class="advantages-hero-copy">Hospedagem com desempenho em SSD, painel em português, suporte humano e liberdade para crescer sem fidelidade.</p>
        <div class="hero-actions">
            <a class="button button-lime" href="planos.php">Ver planos <span aria-hidden="true">↗</span></a>
            <?php if ($companyWhatsapp !== ''): ?>
                <a class="button button-outline button-support button-support-light" href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer"><span>Falar com o suporte</span><span class="support-favicon" aria-hidden="true"><img src="assets/images/favicon.png" alt="" width="24" height="24"></span></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="advantages-trust page-width" aria-label="Destaques das vantagens GeekHost">
    <article><span aria-hidden="true">✓</span> Domínio com orientação especializada</article>
    <article><span aria-hidden="true">✓</span> Migração de sites assistida</article>
    <article><span aria-hidden="true">✓</span> Suporte humano pelo WhatsApp</article>
    <article><span aria-hidden="true">✓</span> Painel cPanel em português</article>
</section>

<section class="advantages-stats page-width" aria-labelledby="advantages-stats-title">
    <h2 id="advantages-stats-title">Tudo o que você precisa para o seu sucesso online.</h2>
    <p>Números e compromissos que mostram como a GeekHost acompanha o crescimento do seu projeto.</p>
    <div class="stats-grid">
        <article><strong>SSD</strong><span>Performance para sites e aplicações</span></article>
        <article><strong>99,9%</strong><span>Foco em disponibilidade da estrutura</span></article>
        <article><strong>BR</strong><span>Atendimento em todo o Brasil</span></article>
        <article><strong>7 dias</strong><span>Grátis para testar com tranquilidade</span></article>
    </div>
</section>

<section class="feature-alt" aria-labelledby="feature-performance-title">
    <div class="feature-alt-inner page-width">
        <figure class="feature-alt-visual">
            <img src="assets/images/vantagem-performance.jpg" alt="Análise de desempenho e tráfego de um site em tablet e smartphone" width="1400" height="933" loading="lazy">
        </figure>
        <div class="feature-alt-copy">
            <p class="eyebrow">Desempenho</p>
            <h2 id="feature-performance-title">Velocidade e estabilidade para o site não travar o negócio.</h2>
            <p>Estrutura pensada para quem precisa publicar com agilidade e manter a experiência do visitante fluida no dia a dia.</p>
            <ul class="feature-alt-list">
                <li>Armazenamento em SSD</li>
                <li>Ambiente preparado para PHP e CMS populares</li>
                <li>SSL para conexão segura</li>
            </ul>
        </div>
    </div>
</section>

<section class="feature-alt feature-alt-reverse" aria-labelledby="feature-security-title">
    <div class="feature-alt-inner page-width">
        <figure class="feature-alt-visual">
            <img src="assets/images/vantagem-gestao.jpg" alt="Profissionais gerenciando e planejando a estrutura de um site" width="1400" height="934" loading="lazy">
        </figure>
        <div class="feature-alt-copy">
            <p class="eyebrow">Segurança e gestão</p>
            <h2 id="feature-security-title">Controle o ambiente sem precisar virar especialista em servidor.</h2>
            <p>Gerencie e-mails, arquivos, bancos e DNS em um painel conhecido, com suporte próximo quando surgir qualquer dúvida.</p>
            <ul class="feature-alt-list">
                <li>cPanel completo em português</li>
                <li>Backups e rotinas de manutenção</li>
                <li>Orientação humana para DNS e publicação</li>
            </ul>
        </div>
    </div>
</section>

<section class="feature-alt" aria-labelledby="feature-migration-title">
    <div class="feature-alt-inner page-width">
        <figure class="feature-alt-visual">
            <img src="assets/images/vantagem-wordpress.jpg" alt="Publicação e migração de site WordPress em um notebook" width="1400" height="933" loading="lazy">
        </figure>
        <div class="feature-alt-copy">
            <p class="eyebrow">Migração</p>
            <h2 id="feature-migration-title">Traga seu site com acompanhamento de verdade.</h2>
            <p>Não precisa enfrentar a mudança sozinho. A equipe ajuda a avaliar o cenário e conduzir a migração com mais segurança.</p>
            <ul class="feature-alt-list">
                <li>Suporte na transição entre provedores</li>
                <li>Checklist claro antes e depois da troca</li>
                <li>Canal direto no WhatsApp</li>
            </ul>
        </div>
    </div>
</section>

<section class="advantages-bullets page-width" aria-labelledby="advantages-bullets-title">
    <div class="advantages-bullets-inner">
        <div>
            <p class="eyebrow">Por que a GeekHost</p>
            <h2 id="advantages-bullets-title">Vantagens que aparecem no uso diário.</h2>
            <p>Benefícios práticos para quem quer menos atrito na hospedagem e mais tempo para o produto, o cliente e o código.</p>
            <div class="advantage-list">
                <details open>
                    <summary>Painel de controle intuitivo</summary>
                    <p>Gerencie domínio, e-mails, bancos e arquivos em um cPanel em português, sem depender de terminal para tarefas comuns.</p>
                </details>
                <details>
                    <summary>Instalação simplificada de aplicações</summary>
                    <p>Publique WordPress e outras aplicações com menos etapas e mais orientação do suporte quando precisar.</p>
                </details>
                <details>
                    <summary>Suporte humano de verdade</summary>
                    <p>Fale com pessoas que entendem hospedagem. Sem robô infinito: WhatsApp direto com a equipe GeekHost.</p>
                </details>
                <details>
                    <summary>Planos sem fidelidade</summary>
                    <p>Comece com 7 dias grátis, escolha o ciclo certo e cancele quando quiser. Liberdade faz parte da oferta.</p>
                </details>
                <details>
                    <summary>Evolução conforme o projeto cresce</summary>
                    <p>Do Light ao Personalizado, há caminho para sites institucionais, negócios e operações sob medida.</p>
                </details>
            </div>
        </div>
        <figure class="advantages-bullets-visual">
            <img src="assets/images/happy-team.jpg" alt="Equipe GeekHost sorrindo e colaborando no dia a dia da hospedagem" width="1600" height="1068" loading="lazy">
        </figure>
    </div>
</section>

<section class="testimonials-section" aria-labelledby="vantagens-testimonials-title">
    <div class="page-width">
        <header class="testimonials-heading">
            <p class="eyebrow">Depoimentos</p>
            <h2 id="vantagens-testimonials-title">Não é só o que a gente diz... é o que os nossos clientes sentem.</h2>
            <p>Avaliações realistas de quem busca hospedagem clara, suporte próximo e uma base confiável para crescer online.</p>
        </header>
        <div class="testimonial-carousel" data-testimonial-carousel role="region" aria-roledescription="carrossel" aria-label="Depoimentos sobre as vantagens GeekHost">
            <div class="testimonial-viewport" data-testimonial-viewport tabindex="0">
                <?php foreach ($testimonials as $index => $testimonial): ?>
                    <?php
                    $name = trim((string) ($testimonial['name'] ?? ''));
                    $text = trim((string) ($testimonial['text'] ?? ''));
                    $profile = trim((string) ($testimonial['profile'] ?? ''));
                    $image = trim((string) ($testimonial['image'] ?? ''));
                    $imageAlt = trim((string) ($testimonial['image_alt'] ?? ''));
                    $rating = filter_var($testimonial['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
                    ?>
                    <article class="testimonial-slide<?= $index === 0 ? ' is-active' : '' ?>" data-testimonial-slide role="group" aria-roledescription="depoimento" aria-label="Depoimento <?= e((string) ($index + 1)) ?> de <?= e((string) count($testimonials)) ?>" aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>" <?= $index === 0 ? '' : 'inert' ?>>
                        <div class="testimonial-content">
                            <?php if ($rating !== false): ?>
                                <div class="testimonial-rating" role="img" aria-label="Avaliação: <?= e((string) $rating) ?> de 5 estrelas">
                                    <?php for ($star = 1; $star <= 5; $star++): ?>
                                        <span class="<?= $star <= $rating ? 'is-filled' : '' ?>" aria-hidden="true">★</span>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                            <blockquote><?= e($text) ?></blockquote>
                            <footer class="testimonial-attribution">
                                <strong><?= e($name) ?></strong>
                                <?php if ($profile !== ''): ?><span><?= e($profile) ?></span><?php endif; ?>
                            </footer>
                        </div>
                        <div class="testimonial-visual">
                            <?php if ($image !== ''): ?>
                                <img src="<?= e($image) ?>" alt="<?= e($imageAlt !== '' ? $imageAlt : 'Foto de ' . $name) ?>" loading="lazy">
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="testimonial-controls" aria-label="Controles do carrossel">
                <button class="testimonial-arrow" type="button" data-testimonial-prev aria-label="Depoimento anterior" title="Depoimento anterior"><span aria-hidden="true">←</span></button>
                <div class="testimonial-pagination" role="group" aria-label="Selecionar depoimento">
                    <?php foreach ($testimonials as $index => $testimonial): ?>
                        <button class="testimonial-dot<?= $index === 0 ? ' is-active' : '' ?>" type="button" data-testimonial-dot="<?= e((string) $index) ?>" aria-label="Exibir depoimento <?= e((string) ($index + 1)) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"></button>
                    <?php endforeach; ?>
                </div>
                <button class="testimonial-arrow" type="button" data-testimonial-next aria-label="Próximo depoimento" title="Próximo depoimento"><span aria-hidden="true">→</span></button>
                <span class="testimonial-status" data-testimonial-status aria-live="polite"></span>
            </div>
        </div>
    </div>
</section>

<section class="advantages-cta page-width">
    <div>
        <h2>Tudo pronto para dar o próximo passo?</h2>
        <p>Escolha um plano, fale com o suporte ou compare as opções com calma. A GeekHost está pronta para hospedar o seu projeto.</p>
    </div>
    <a class="button button-lime" href="planos.php">Começar agora <span aria-hidden="true">↗</span></a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
