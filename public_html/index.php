<?php

$pageTitle = 'Hospedagem para o seu próximo projeto';
$pageDescription = 'Conheça as opções de hospedagem GeekHost e encontre um ponto de partida para seu site, negócio ou loja online.';
$currentPage = 'inicio';
$technologyCategories = [
    [
        'name' => 'Linguagens e tecnologias web',
        'items' => [
            ['name' => 'PHP', 'icon' => 'php', 'color' => '777BB4'],
            ['name' => 'HTML5', 'icon' => 'html5', 'color' => 'E34F26'],
            ['name' => 'CSS3', 'src' => 'https://cdn.jsdelivr.net/gh/devicons/devicon/icons/css3/css3-original.svg'],
            ['name' => 'JavaScript', 'icon' => 'javascript', 'color' => 'F7DF1E'],
        ],
    ],
    [
        'name' => 'Frameworks PHP',
        'items' => [
            ['name' => 'Laravel', 'icon' => 'laravel', 'color' => 'FF2D20'],
            ['name' => 'CakePHP', 'icon' => 'cakephp', 'color' => 'D33C43'],
            ['name' => 'Symfony', 'icon' => 'symfony', 'color' => '000000'],
            ['name' => 'CodeIgniter', 'icon' => 'codeigniter', 'color' => 'EF4223'],
        ],
    ],
    [
        'name' => 'CMS e e-commerce',
        'items' => [
            ['name' => 'WordPress', 'icon' => 'wordpress', 'color' => '21759B', 'featured' => true],
            ['name' => 'WooCommerce', 'icon' => 'woocommerce', 'color' => '96588A', 'featured' => true],
            ['name' => 'Joomla', 'icon' => 'joomla', 'color' => '5091CD'],
            ['name' => 'Drupal', 'icon' => 'drupal', 'color' => '0678BE'],
            ['name' => 'PrestaShop', 'icon' => 'prestashop', 'color' => 'DF0067'],
        ],
    ],
    [
        'name' => 'Bancos de dados',
        'items' => [
            ['name' => 'MySQL', 'icon' => 'mysql', 'color' => '4479A1'],
            ['name' => 'MariaDB', 'icon' => 'mariadb', 'color' => '003545'],
        ],
    ],
    [
        'name' => 'Ferramentas de desenvolvimento',
        'items' => [
            ['name' => 'Composer', 'icon' => 'composer', 'color' => '885630'],
            ['name' => 'Git', 'icon' => 'git', 'color' => 'F05032'],
            ['name' => 'npm', 'icon' => 'npm', 'color' => 'CB3837'],
            ['name' => 'SSH', 'symbol' => '>_'],
        ],
    ],
    [
        'name' => 'Serviços para seu site',
        'items' => [
            ['name' => 'E-mail profissional', 'symbol' => '@'],
            ['name' => 'Certificado SSL', 'symbol' => '✓'],
            ['name' => 'Gerenciamento de DNS', 'symbol' => 'DNS'],
            ['name' => 'FTP/SFTP', 'symbol' => '↔'],
            ['name' => 'Backups', 'symbol' => '↻'],
            ['name' => 'Instalador de apps', 'symbol' => '+'],
        ],
    ],
];
$testimonials = [
    [
        'name' => 'Mariana Costa',
        'profile' => 'Empreendedora',
        'text' => 'Ter um site profissional fez toda a diferença para o meu negócio. Eu precisava de uma hospedagem fácil de administrar e que me desse tranquilidade no dia a dia. A GeekHost é a escolha que eu buscava para manter meu projeto online.',
        'image' => 'assets/images/depoimentos/Mariana%20Costa.jpg',
        'image_alt' => 'Foto ilustrativa de uma empreendedora em seu ambiente de trabalho',
        'rating' => null,
        'service' => null,
        'url' => null,
    ],
    [
        'name' => 'Rafael Martins',
        'profile' => 'Desenvolvedor web',
        'text' => 'Para quem trabalha com desenvolvimento web, contar com uma hospedagem compatível com as necessidades do projeto é essencial. Busco praticidade na administração e recursos que facilitem a publicação e a manutenção dos meus sites.',
        'image' => 'assets/images/depoimentos/Rafael%20Martins.jpg',
        'image_alt' => 'Foto ilustrativa de um profissional de desenvolvimento web',
        'rating' => null,
        'service' => null,
        'url' => null,
    ],
    [
        'name' => 'Camila Oliveira',
        'profile' => 'Marketing digital',
        'text' => 'Um site bem estruturado é essencial para apresentar uma marca na internet. Quero uma solução que facilite a gestão dos projetos dos meus clientes e acompanhe o crescimento do meu trabalho digital.',
        'image' => 'assets/images/depoimentos/Camila%20Oliveira.jpg',
        'image_alt' => 'Foto ilustrativa de uma profissional de marketing digital',
        'rating' => null,
        'service' => null,
        'url' => null,
    ],
    [
        'name' => 'Lucas Ferreira',
        'profile' => 'Pequeno empresário',
        'text' => 'Minha presença digital precisava de uma base profissional para apresentar meus serviços e facilitar o contato com os clientes. Ter os recursos de hospedagem e gerenciamento reunidos em um só lugar torna tudo mais organizado.',
        'image' => 'assets/images/depoimentos/Lucas%20Ferreira.avif',
        'image_alt' => 'Foto ilustrativa de um pequeno empresário',
        'rating' => null,
        'service' => null,
        'url' => null,
    ],
    [
        'name' => 'Isabela Rocha',
        'profile' => 'Designer freelancer',
        'text' => 'Cada projeto precisa de uma presença online que transmita profissionalismo. Ter uma hospedagem alinhada às necessidades do site é uma parte importante para entregar um trabalho completo aos meus clientes.',
        'image' => 'assets/images/depoimentos/Isabela%20Rocha.jpg',
        'image_alt' => 'Foto ilustrativa de uma designer freelancer',
        'rating' => null,
        'service' => null,
        'url' => null,
    ],
];
require __DIR__ . '/includes/header.php';
?>

<section class="home-hero">
    <div class="hero-shade" aria-hidden="true"></div>
    <div class="hero-ambient" aria-hidden="true">
        <span class="orb orb-1"></span>
        <span class="orb orb-2"></span>
        <span class="orb orb-3"></span>
        <span class="orb orb-4"></span>
        <span class="grid-line grid-line-1"></span>
        <span class="grid-line grid-line-2"></span>
        <span class="grid-line grid-line-3"></span>
    </div>
    <div class="hero-content page-width">
        <p class="eyebrow eyebrow-light"><span></span> De nerd para nerd</p>
        <h1>
            Sua ideia no ar.<br>
            <span class="hero-title-line">
                Seu projeto merece
                <span class="hero-dynamic-word-shell">
                    <span class="hero-dynamic-word is-visible" data-hero-words='["mais velocidade","mais desempenho","mais liberdade","mais possibilidades","mais tecnologia"]'>mais velocidade</span>
                </span>
            </span>
        </h1>
        <p class="hero-copy">Hospedagem para programadores, agências e empresas. Tenha seu site em uma estrutura gerenciada, com suporte humano pelo WhatsApp e ajuda para trazer seu site para a GeekHost.</p>
        <div class="hero-actions">
            <a class="button button-lime" href="#planos">Conheça os planos <span aria-hidden="true">↘</span></a>
            <a class="button button-outline button-support button-support-light" href="https://wa.me/5516997399174"><span>Fale com o suporte</span><span class="support-favicon" aria-hidden="true"><img src="assets/images/favicon.png" alt="" width="24" height="24"></span></a>
        </div>
        <p class="hero-footnote">7 dias grátis para começar. Sem fidelidade.</p>
    </div>
</section>

<section class="proof-strip page-width" aria-label="Destaques da oferta GeekHost">
    <article><strong>4</strong><span>opções para diferentes necessidades</span></article>
    <article><strong>7 dias</strong><span>grátis para começar</span></article>
    <article><strong>Sem</strong><span>fidelidade: cancele quando quiser</span></article>
</section>

<section class="section section-plans page-width" id="planos">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Encontre seu ponto de partida</p>
            <h2>Um plano para<br>cada próximo passo.</h2>
        </div>
        <p class="section-lede">Escolha entre três planos de hospedagem ou fale com a equipe para montar uma solução personalizada.</p>
    </div>
    <div class="plan-grid">
        <article class="plan-card plan-card-light" id="light" data-plan-card>
            <span class="plan-number">01 / PRESENÇA</span>
            <h3>Plano Light</h3>
            <p>Para colocar seu site e suas ideias no ar.</p>
            <p class="plan-price"><strong>R$ 19,90</strong><span>/no 1º mês</span></p>
            <p class="plan-regular-price">A partir do 2º mês: <strong>R$ 35,90/mês</strong></p>
            <div class="plan-first-month"><span>7 dias grátis para começar</span></div>
            <div class="plan-savings"><img class="plan-savings-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="24" height="24"><span><strong>45% OFF | Economize R$ 16,00</strong><small>no 1º mês</small></span></div>
            <ul class="plan-features"><li>5 contas de e-mail</li><li>10 GB de espaço</li><li>Banco de dados ilimitado</li></ul>
            <a class="arrow-link" href="planos.php#light">Conhecer o Light <span aria-hidden="true">↗</span></a>
        </article>
        <article class="plan-card plan-card-green plan-card-recommended" id="empresa" data-plan-card>
            <span class="plan-recommendation">✓ Recomendado</span>
            <span class="plan-number">02 / CRESCIMENTO</span>
            <h3>Plano Empresa</h3>
            <p>Para negócios e equipes que querem crescer online.</p>
            <p class="plan-price"><strong>R$ 29,90</strong><span>/no 1º mês</span></p>
            <p class="plan-regular-price">A partir do 2º mês: <strong>R$ 45,90/mês</strong></p>
            <div class="plan-first-month"><span>7 dias grátis para começar</span></div>
            <div class="plan-savings"><img class="plan-savings-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="24" height="24"><span><strong>35% OFF | Economize R$ 16,00</strong><small>no 1º mês</small></span></div>
            <ul class="plan-features"><li>10 contas de e-mail</li><li>15 GB de espaço</li><li>Banco de dados ilimitado</li></ul>
            <a class="arrow-link" href="planos.php#empresa">Conhecer o Empresa <span aria-hidden="true">↗</span></a>
        </article>
        <article class="plan-card plan-card-lime" id="premium" data-plan-card>
            <span class="plan-number">03 / COMÉRCIO</span>
            <h3>Plano Premium</h3>
            <p>Para projetos que precisam de uma estrutura mais completa.</p>
            <p class="plan-price"><strong>R$ 39,90</strong><span>/no 1º mês</span></p>
            <p class="plan-regular-price">A partir do 2º mês: <strong>R$ 55,90/mês</strong></p>
            <div class="plan-first-month"><span>7 dias grátis para começar</span></div>
            <div class="plan-savings"><img class="plan-savings-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="24" height="24"><span><strong>29% OFF | Economize R$ 16,00</strong><small>no 1º mês</small></span></div>
            <ul class="plan-features"><li>E-mails ilimitados</li><li>25 GB de espaço</li><li>Banco de dados ilimitado</li></ul>
            <a class="arrow-link" href="planos.php#premium">Conhecer o Premium <span aria-hidden="true">↗</span></a>
        </article>
        <article class="plan-card plan-card-custom" id="personalizado" data-plan-card>
            <span class="plan-number">04 / SOB MEDIDA</span>
            <h3>Plano Personalizado</h3>
            <p>Precisa de mais? Entre em contato!</p>
            <p class="plan-custom-note">Vamos entender o seu projeto e conversar sobre uma solução sob medida.</p>
            <a class="arrow-link" href="https://wa.me/5516997399174">Fale com a equipe <span aria-hidden="true">↗</span></a>
        </article>
    </div>
    <p class="plan-commitment"><span>PLANOS SEM FIDELIDADE. Cancele quando quiser.</span></p>
</section>

<section class="technology-section" aria-labelledby="technology-title">
    <div class="page-width technology-heading">
        <div>
            <p class="eyebrow">Seu ambiente de hospedagem</p>
            <h2 id="technology-title">Tecnologias para tirar seu projeto do papel.</h2>
        </div>
        <p class="section-lede">Linguagens, plataformas, ferramentas e serviços para seu site.</p>
    </div>
    <div class="technology-marquee" aria-label="Tecnologias e serviços disponíveis na hospedagem GeekHost">
        <div class="technology-track">
            <?php for ($group = 0; $group < 2; $group++): ?>
                <div class="technology-group" <?= $group > 0 ? 'aria-hidden="true"' : '' ?>>
                    <?php foreach ($technologyCategories as $category): ?>
                        <div class="technology-category">
                            <!-- <span class="technology-category-name"><?= e($category['name']) ?></span> -->
                            <div class="technology-items">
                                <?php foreach ($category['items'] as $technology): ?>
                                    <div class="technology-item <?= !empty($technology['featured']) ? 'technology-item-featured' : '' ?>">
                                        <?php if (isset($technology['src'])): ?>
                                            <img src="<?= e($technology['src']) ?>" alt="" width="46" height="46" loading="lazy">
                                        <?php elseif (isset($technology['icon'])): ?>
                                            <img src="https://cdn.simpleicons.org/<?= e($technology['icon']) ?>/<?= e($technology['color']) ?>" alt="" width="46" height="46" loading="lazy">
                                        <?php else: ?>
                                            <span class="technology-symbol" aria-hidden="true"><?= e($technology['symbol']) ?></span>
                                        <?php endif; ?>
                                        <!-- <span><?= e($technology['name']) ?></span> -->
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<section class="benefits-section" id="vantagens">
    <div class="page-width">
        <div class="section-heading">
            <div>
                <p class="eyebrow">GeekHost, de nerd para nerd</p>
                <h2>Mais estrutura.<br>Menos complicação.</h2>
            </div>
            <p class="section-lede">Você cuida das ideias e do negócio. A GeekHost oferece recursos e atendimento para ajudar seu projeto a seguir em frente.</p>
        </div>
        <div class="benefit-grid">
            <article class="benefit-item"><span>01</span><h3>Performance em SSD</h3><p>Plataforma baseada em SSD e estrutura pensada para aplicações que precisam de agilidade.</p></article>
            <article class="benefit-item"><span>02</span><h3>Hospedagem gerenciada</h3><p>Conte com uma equipe para apoiar a rotina de hospedagem e deixar você focar no seu projeto.</p></article>
            <article class="benefit-item"><span>03</span><h3>Painel cPanel em português</h3><p>Gerencie hospedagem e e-mails em um painel conhecido, completo e fácil de usar.</p></article>
            <article class="benefit-item"><span>04</span><h3>Suporte humano</h3><p>Fale com o time GeekHost pelo WhatsApp quando precisar de orientação.</p></article>
        </div>
        <p class="benefit-note">7 dias grátis no início da contratação, conforme as condições comerciais. Todos os planos são sem fidelidade.</p>
    </div>
</section>

<section class="people-section" aria-labelledby="people-title">
    <div class="page-width">
        <header class="people-heading">
            <p class="eyebrow">Presença digital, feita por pessoas</p>
            <h2 id="people-title">Seu projeto online começa com uma boa parceria.</h2>
            <p>Apresente seu trabalho, reúna sua equipe e facilite o contato com quem acompanha seu negócio.</p>
        </header>
        <article class="people-story">
            <figure class="people-story-visual">
                <img src="assets/images/home-photos/Trabalho%20em%20equipe.jpg" alt="Equipe reunida ao redor de um notebook durante uma conversa de trabalho" loading="lazy" width="1200" height="800">
            </figure>
            <div class="people-story-copy">
                <span class="people-story-label">Trabalho em equipe</span>
                <h3>Ideias compartilhadas também precisam de um espaço online.</h3>
                <p>Coloque seu projeto em um site próprio e mantenha as informações do seu trabalho organizadas para seu público.</p>
            </div>
        </article>
        <article class="people-story people-story-reverse">
            <figure class="people-story-visual">
                <img src="assets/images/home-photos/Neg%C3%B3cios%20online.jpg" alt="Profissionais conversando e trabalhando juntos em um espaço comercial" loading="lazy" width="1200" height="801">
            </figure>
            <div class="people-story-copy">
                <span class="people-story-label">Negócios online</span>
                <h3>Mostre o que sua empresa faz com uma presença profissional.</h3>
                <p>Apresente seus serviços em um endereço próprio e torne mais simples para as pessoas conhecerem e encontrarem seu negócio.</p>
            </div>
        </article>
    </div>
</section>

<section class="testimonials-section" aria-labelledby="testimonials-title">
    <div class="page-width">
        <header class="testimonials-heading">
            <p class="eyebrow">Voz de clientes</p>
            <h2 id="testimonials-title">Quem escolhe a GeekHost, recomenda!</h2>
            <p>Conheça histórias e experiências relacionadas à criação e hospedagem de projetos digitais.</p>
        </header>
        <?php if ($testimonials): ?>
            <div class="testimonial-carousel" data-testimonial-carousel role="region" aria-roledescription="carrossel" aria-label="Depoimentos de clientes GeekHost">
                <div class="testimonial-viewport" data-testimonial-viewport tabindex="0">
                    <?php foreach ($testimonials as $index => $testimonial): ?>
                        <?php
                        $name = trim((string) ($testimonial['name'] ?? ''));
                        $text = trim((string) ($testimonial['text'] ?? ''));
                        $company = trim((string) ($testimonial['company'] ?? ''));
                        $profile = trim((string) ($testimonial['profile'] ?? ''));
                        $image = trim((string) ($testimonial['image'] ?? ''));
                        $imageAlt = trim((string) ($testimonial['image_alt'] ?? ''));
                        $photoReference = trim((string) ($testimonial['photo_reference'] ?? ''));
                        $photoReferenceParts = $photoReference !== '' ? parse_url($photoReference) : false;
                        if (!is_array($photoReferenceParts) || ($photoReferenceParts['scheme'] ?? '') !== 'https' || ($photoReferenceParts['host'] ?? '') !== 'www.freepik.com') {
                            $photoReference = '';
                        }
                        $service = trim((string) ($testimonial['service'] ?? ''));
                        $rating = filter_var($testimonial['rating'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
                        $url = trim((string) ($testimonial['url'] ?? ''));
                        $urlParts = $url !== '' ? parse_url($url) : false;
                        if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($urlParts) || !in_array($urlParts['scheme'] ?? '', ['http', 'https'], true)) {
                            $url = '';
                        }
                        $nameParts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                        $initials = '';
                        foreach (array_slice($nameParts, 0, 2) as $namePart) {
                            $nameCharacters = preg_split('//u', $namePart, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                            $initials .= $nameCharacters[0] ?? '';
                        }
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
                                    <?php if ($company !== ''): ?><span><?= e($company) ?></span><?php endif; ?>
                                    <?php if ($service !== ''): ?><span class="testimonial-service">Serviço: <?= e($service) ?></span><?php endif; ?>
                                    <?php if ($url !== ''): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">Acessar projeto</a><?php endif; ?>
                                </footer>
                            </div>
                            <div class="testimonial-visual">
                                <?php if ($image !== ''): ?>
                                    <img src="<?= e($image) ?>" alt="<?= e($imageAlt !== '' ? $imageAlt : 'Imagem autorizada enviada por ' . $name) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="testimonial-avatar" aria-hidden="true"><span><?= e($initials) ?></span></div>
                                    <?php if ($photoReference !== ''): ?><a class="testimonial-photo-reference" href="<?= e($photoReference) ?>" target="_blank" rel="noopener noreferrer">Referência para escolher foto</a><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if (count($testimonials) > 1): ?>
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
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="testimonials-empty" role="status">
                <span class="testimonials-empty-mark" aria-hidden="true">✳</span>
                <p>Estamos reunindo depoimentos autorizados de clientes GeekHost. Histórias reais aparecerão aqui quando aprovadas.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="faq-section" id="perguntas-frequentes" aria-labelledby="faq-title">
    <div class="page-width faq-layout">
        <div class="faq-heading">
            <p class="eyebrow">Perguntas frequentes</p>
            <h2 id="faq-title">Respostas para<br>começar com clareza.</h2>
            <a class="button button-outline button-support" href="https://wa.me/5516997399174"><span>Ainda tem dúvidas? Fale com a equipe</span><span class="support-favicon" aria-hidden="true"><img src="assets/images/favicon.png" alt="" width="24" height="24"></span></a>
        </div>
        <div class="faq-list">
            <details>
                <summary>O que é hospedagem de sites e qual a diferença para um domínio?</summary>
                <p>A hospedagem guarda os arquivos do site em um servidor e mantém o conteúdo disponível na internet. O domínio é o endereço que as pessoas usam para encontrar o site. São serviços diferentes e podem ser contratados separadamente.</p>
            </details>
            <details>
                <summary>Qual plano é melhor para o meu projeto?</summary>
                <p>O Light é uma opção para colocar um primeiro site no ar; o Empresa atende negócios que querem crescer online; e o Premium é voltado a projetos em expansão. Compare os ciclos e confirme os recursos de cada plano antes de contratar.</p>
            </details>
            <details>
                <summary>Como funciona o preço promocional do primeiro mês?</summary>
                <p>Após os 7 dias grátis, aplica-se o valor promocional informado para o primeiro mês. A partir do segundo mês, passa a valer o preço mensal regular exibido para cada plano.</p>
            </details>
            <details>
                <summary>A GeekHost pode migrar meu site?</summary>
                <p>Fale com a equipe para verificar as possibilidades e as condições de migração do seu site para a GeekHost.</p>
            </details>
            <details>
                <summary>Existe um período para testar a hospedagem?</summary>
                <p>Há teste grátis de 7 dias, conforme as condições comerciais. Consulte a equipe para conhecer as regras aplicáveis ao plano escolhido.</p>
            </details>
        </div>
    </div>
</section>

<section class="closing-cta page-width">
    <p class="eyebrow">Vamos tirar do papel?</p>
    <div class="closing-row"><h2>Seu próximo passo<br>começa pelo plano certo.</h2><a class="button button-dark" href="#planos">Ver planos e preços <span aria-hidden="true">↗</span></a></div>
</section>

<section class="generator-promo" id="gerador" aria-labelledby="generator-title">
    <div class="generator-promo-inner page-width">
        <div class="generator-promo-copy">
            <p class="eyebrow eyebrow-light">Ferramenta gratuita</p>
            <h2 id="generator-title">Um toque.<br>Uma conversa.</h2>
            <p>Crie um link direto para o WhatsApp e gere um QR code para compartilhar seu atendimento.</p>
            <a class="button button-lime" href="gerador.php">Acessar o gerador <span aria-hidden="true">↗</span></a>
        </div>
        <img class="generator-promo-image" src="assets/images/link-qrcoce.png" alt="Mascote GeekHost ao lado de um link e de um QR code do WhatsApp" width="1328" height="1056" loading="lazy">
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>