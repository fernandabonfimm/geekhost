<?php

$pageTitle = 'Termos de Uso';
$pageDocumentTitle = 'Termos de Uso | GeekHost';
$pageDescription = 'Termos de Uso da GeekHost: condições de contratação, responsabilidades e regras de uso dos serviços de hospedagem da FBS Tecnologia.';
$currentPage = 'termos-de-uso';
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero page-width legal-hero">
    <p class="eyebrow">Documentos legais</p>
    <h1>Termos de Uso</h1>
    <p class="page-hero-copy">Condições gerais de uso dos serviços GeekHost, operados pela FBS Tecnologia. Última atualização: 5 de novembro de 2026.</p>
</section>

<section class="legal-doc page-width" aria-labelledby="termos-intro">
    <div class="legal-doc-meta">
        <p id="termos-intro">Este documento estabelece as regras de contratação e uso dos serviços de hospedagem e correlatos oferecidos sob a marca GeekHost. Ao navegar no site, solicitar orçamento, criar conta, contratar plano ou utilizar qualquer serviço, você declara ter lido e concordado com estes Termos.</p>
        <aside class="legal-doc-card" aria-label="Dados do prestador">
            <span>Prestador dos serviços</span>
            <strong>FBS Tecnologia</strong>
            <p>CNPJ 61.171.635/0001-46<br>Rua Pais Leme, 215, Conj. 1713<br>Pinheiros, São Paulo/SP — CEP 05424-150<br>E-mail: <a href="mailto:<?= e($companyEmail !== '' ? $companyEmail : 'contato@geekhost.com.br') ?>"><?= e($companyEmail !== '' ? $companyEmail : 'contato@geekhost.com.br') ?></a></p>
        </aside>
    </div>

    <article class="legal-section">
        <h2>1. Aceitação</h2>
        <p>O uso do site geekhost.com.br e dos serviços GeekHost implica a aceitação integral destes Termos e da <a href="politica-de-privacidade.php">Política de Privacidade</a>. Se você não concordar, não utilize o site nem contrate os serviços.</p>
        <p>Caso a contratação seja feita em nome de pessoa jurídica, você declara ter poderes para vincular a empresa às obrigações aqui previstas.</p>
    </article>

    <article class="legal-section">
        <h2>2. Serviços</h2>
        <p>A GeekHost oferece, entre outros, hospedagem de sites, e-mail profissional vinculado ao plano, painel de controle (cPanel), SSL, orientação de domínio, migração assistida quando prevista na oferta e suporte técnico humano pelos canais oficiais.</p>
        <p>Detalhes de recursos, limites, preços, ciclos de cobrança, período de teste e condições comerciais específicas constam nas páginas de planos, no pedido de contratação e/ou em proposta comercial. Em caso de divergência, prevalecem as condições do plano efetivamente contratado.</p>
        <p>Os serviços são prestados de forma remota, com operação no Brasil e atendimento a clientes no Brasil e em Portugal, conforme disponibilidade comunicada no site.</p>
    </article>

    <article class="legal-section">
        <h2>3. Cadastro e conta</h2>
        <p>Para contratar, você deve fornecer dados verdadeiros, completos e atualizados. É sua responsabilidade manter a confidencialidade de senhas, chaves de acesso e painéis, bem como notificar a GeekHost sobre uso não autorizado.</p>
        <p>Você é responsável por toda atividade realizada a partir de suas credenciais, salvo comprovação de falha de segurança imputável à GeekHost.</p>
    </article>

    <article class="legal-section">
        <h2>4. Obrigações do cliente</h2>
        <ul>
            <li>Utilizar os serviços de forma lícita, ética e em conformidade com a legislação brasileira aplicável, inclusive LGPD, Marco Civil da Internet e normas de propriedade intelectual.</li>
            <li>Não hospedar, transmitir ou promover conteúdo ilegal, difamatório, discriminatório, que viole direitos de terceiros, malware, phishing, spam, mineração abusiva, ataques ou qualquer atividade que comprometa a estabilidade da rede.</li>
            <li>Manter cópias de segurança próprias dos conteúdos críticos, ainda que a GeekHost ofereça rotinas de backup como diferencial do serviço.</li>
            <li>Respeitar limites de recursos do plano (armazenamento, processamento, tráfego, contas de e-mail e demais cotas).</li>
            <li>Efetuar pagamentos nos prazos e meios indicados na contratação.</li>
        </ul>
    </article>

    <article class="legal-section">
        <h2>5. Obrigações da GeekHost</h2>
        <ul>
            <li>Empregar esforços razoáveis para manter a disponibilidade e o desempenho da infraestrutura, com meta operacional de alta disponibilidade, sem garantia absoluta de uptime ininterrupto.</li>
            <li>Fornecer suporte técnico pelos canais oficiais (como WhatsApp e e-mail), em horário e condições comunicados comercialmente.</li>
            <li>Adotar medidas técnicas e administrativas adequadas à proteção do ambiente de hospedagem, sem eliminar riscos inerentes à internet.</li>
            <li>Informar, quando possível e pertinente, manutenções programadas ou incidentes relevantes que afetem o serviço.</li>
        </ul>
    </article>

    <article class="legal-section">
        <h2>6. Pagamentos, renovação e cancelamento</h2>
        <p>Os valores, formas de pagamento e periodicidade são os indicados no momento da contratação. A falta de pagamento pode resultar em suspensão ou cancelamento do serviço, após comunicação pelos canais cadastrados.</p>
        <p>Quando a oferta incluir período de teste gratuito, as condições desse período (duração, limites e conversão em plano pago) serão as divulgadas na contratação. Planos sem fidelidade permitem cancelamento conforme regras comerciais vigentes; valores já pagos podem seguir política de reembolso específica informada no pedido ou no suporte.</p>
        <p>Após o cancelamento, a GeekHost poderá excluir dados e arquivos do ambiente do cliente, observando prazos técnicos razoáveis e eventuais obrigações legais de retenção.</p>
    </article>

    <article class="legal-section">
        <h2>7. Propriedade intelectual</h2>
        <p>Marcas, logotipos, layout, textos, código e demais elementos do site GeekHost pertencem à FBS Tecnologia ou a licenciadores e não podem ser copiados ou explorados sem autorização.</p>
        <p>O conteúdo hospedado pelo cliente permanece de sua titularidade ou de seus licenciadores. Ao utilizar o serviço, o cliente concede à GeekHost apenas a licença necessária para armazenar, transmitir e processar esse conteúdo com a finalidade de prestar a hospedagem.</p>
    </article>

    <article class="legal-section">
        <h2>8. Limitação de responsabilidade</h2>
        <p>Na máxima extensão permitida pela lei, a GeekHost não se responsabiliza por: (a) lucros cessantes, perda de chance ou danos indiretos; (b) falhas de terceiros (registradores de domínio, operadoras, provedores de DNS, CDNs, gateways de pagamento); (c) conteúdo publicado pelo cliente; (d) configurações incorretas realizadas pelo cliente; (e) indisponibilidades decorrentes de caso fortuito, força maior ou ataques externos.</p>
        <p>A responsabilidade total da GeekHost, quando cabível, limita-se ao valor efetivamente pago pelo cliente pelo serviço afetado no ciclo de cobrança em que ocorreu o evento, salvo disposição legal imperativa em contrário (incluindo relações de consumo).</p>
    </article>

    <article class="legal-section">
        <h2>9. Suspensão e rescisão</h2>
        <p>A GeekHost poderá suspender ou encerrar o serviço, com ou sem aviso prévio conforme a gravidade, em caso de violação destes Termos, uso abusivo de recursos, risco à segurança da infraestrutura, ordem de autoridade competente ou inadimplência.</p>
    </article>

    <article class="legal-section">
        <h2>10. Alterações</h2>
        <p>Estes Termos podem ser atualizados para refletir mudanças legais, técnicas ou comerciais. A versão vigente será publicada nesta página, com indicação da data de atualização. O uso continuado dos serviços após a publicação constitui aceitação das alterações, quando a lei permitir essa forma de aceite.</p>
    </article>

    <article class="legal-section">
        <h2>11. Lei aplicável e foro</h2>
        <p>Estes Termos são regidos pelas leis da República Federativa do Brasil. Fica eleito o foro da comarca de São Paulo/SP, com renúncia a qualquer outro, por mais privilegiado que seja, ressalvados os direitos do consumidor quanto ao foro de seu domicílio quando aplicável o Código de Defesa do Consumidor.</p>
    </article>

    <article class="legal-section">
        <h2>12. Contato</h2>
        <p>Dúvidas sobre estes Termos: <a href="mailto:<?= e($companyEmail !== '' ? $companyEmail : 'contato@geekhost.com.br') ?>"><?= e($companyEmail !== '' ? $companyEmail : 'contato@geekhost.com.br') ?></a><?php if ($companyWhatsapp !== ''): ?> ou WhatsApp <a href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer">+<?= e($companyWhatsapp) ?></a><?php endif; ?>. Também é possível usar a página de <a href="contato.php">Contato</a>.</p>
        <p class="legal-disclaimer">Texto de caráter informativo e contratual padrão para orientação dos usuários. Para situações específicas, recomenda-se revisão por advogado do contratante.</p>
    </article>

    <nav class="legal-crosslinks" aria-label="Documentos relacionados">
        <a href="politica-de-privacidade.php">Política de Privacidade <span aria-hidden="true">↗</span></a>
        <a href="contato.php">Falar com a GeekHost <span aria-hidden="true">↗</span></a>
    </nav>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
