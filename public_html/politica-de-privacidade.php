<?php

$pageTitle = 'Política de Privacidade';
$pageDocumentTitle = 'Política de Privacidade | GeekHost';
$pageDescription = 'Política de Privacidade da GeekHost (FBS Tecnologia): dados coletados, finalidades, bases legais LGPD, cookies, compartilhamento, retenção e direitos do titular.';
$currentPage = 'politica-de-privacidade';
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero page-width legal-hero">
    <p class="eyebrow">Documentos legais</p>
    <h1>Política de Privacidade</h1>
    <p class="page-hero-copy">Como a GeekHost trata dados pessoais em conformidade com a LGPD (Lei nº 13.709/2018). Última atualização: 5 de novembro de 2026.</p>
</section>

<section class="legal-doc page-width" aria-labelledby="privacidade-intro">
    <div class="legal-doc-meta">
        <p id="privacidade-intro">Esta Política descreve quais dados pessoais são tratados no site e nos serviços GeekHost, para quais finalidades, com quem podem ser compartilhados e quais são os seus direitos como titular. Ao utilizar o site ou contratar serviços, você declara ciência deste documento.</p>
        <aside class="legal-doc-card" aria-label="Controlador dos dados">
            <span>Controlador</span>
            <strong>FBS Tecnologia</strong>
            <p>CNPJ 61.171.635/0001-46<br>Rua Pais Leme, 215, Conj. 1713<br>Pinheiros, São Paulo/SP — CEP 05424-150<br>Canal de privacidade: <a href="mailto:<?= e($companyEmail !== '' ? $companyEmail : 'contato@geekhost.com.br') ?>"><?= e($companyEmail !== '' ? $companyEmail : 'contato@geekhost.com.br') ?></a></p>
        </aside>
    </div>

    <article class="legal-section">
        <h2>1. Quem somos</h2>
        <p>A marca GeekHost é operada pela FBS Tecnologia, empresa brasileira que presta serviços de hospedagem de sites e soluções correlatas. Para fins da LGPD, a FBS Tecnologia atua como controladora dos dados pessoais tratados no site institucional e na relação comercial com clientes e leads.</p>
        <p>Quando o cliente hospeda sites ou aplicações que contenham dados de terceiros, o cliente é, em regra, o controlador desses dados; a GeekHost atua como operadora na medida necessária à prestação da hospedagem, conforme instruções e limites do serviço.</p>
    </article>

    <article class="legal-section">
        <h2>2. Dados que coletamos</h2>
        <p>Dependendo da interação, podemos tratar:</p>
        <ul>
            <li><strong>Dados de identificação e contato:</strong> nome, e-mail, telefone/WhatsApp, empresa e demais informações enviadas em formulários ou mensagens.</li>
            <li><strong>Dados de contratação e faturamento:</strong> dados cadastrais, documentos quando exigidos para emissão fiscal, endereço, histórico de planos e pagamentos (processados também por intermediários de pagamento).</li>
            <li><strong>Dados técnicos de navegação:</strong> endereço IP, data/hora de acesso, páginas visitadas, tipo de dispositivo/navegador e registros necessários à segurança e ao funcionamento do site.</li>
            <li><strong>Preferências de cookies:</strong> escolhas de consentimento armazenadas localmente e/ou em cookie técnico, conforme o banner de privacidade.</li>
            <li><strong>Dados de suporte:</strong> conteúdo das conversas e anexos enviados voluntariamente para atendimento.</li>
            <li><strong>Dados do gerador de WhatsApp:</strong> número e mensagem informados para criação de link temporário, sem gravação em banco de dados pela ferramenta, conforme aviso na própria página.</li>
        </ul>
        <p>Não solicitamos dados sensíveis para navegação no site. Se forem enviados espontaneamente em mensagens, serão tratados apenas na medida necessária ao atendimento e excluídos quando não forem mais úteis, salvo obrigação legal.</p>
    </article>

    <article class="legal-section">
        <h2>3. Finalidades do tratamento</h2>
        <ul>
            <li>Disponibilizar e melhorar o site, a navegação e os canais de atendimento.</li>
            <li>Responder contatos, orçamentos e solicitações de suporte.</li>
            <li>Executar contratos de hospedagem, cobrança, renovação, migração e comunicação operacional.</li>
            <li>Cumprir obrigações legais, regulatórias e fiscais.</li>
            <li>Prevenir fraudes, abusos e incidentes de segurança.</li>
            <li>Enviar comunicações comerciais quando houver base legal adequada (por exemplo, legítimo interesse razoável ou consentimento), sempre com opção de opt-out quando aplicável.</li>
            <li>Lembrar preferências de cookies não essenciais, mediante consentimento.</li>
        </ul>
    </article>

    <article class="legal-section">
        <h2>4. Bases legais (LGPD)</h2>
        <p>Tratamos dados pessoais com fundamento, conforme o caso, em:</p>
        <ul>
            <li><strong>Execução de contrato</strong> ou de procedimentos preliminares (art. 7º, V).</li>
            <li><strong>Cumprimento de obrigação legal ou regulatória</strong> (art. 7º, II).</li>
            <li><strong>Legítimo interesse</strong>, observados direitos e expectativas do titular (art. 7º, IX), por exemplo para segurança do site e melhoria de serviços.</li>
            <li><strong>Consentimento</strong>, quando exigido — especialmente para cookies não essenciais e determinadas comunicações (art. 7º, I).</li>
            <li><strong>Exercício regular de direitos</strong> em processos judiciais, administrativos ou arbitrais (art. 7º, VI).</li>
        </ul>
    </article>

    <article class="legal-section">
        <h2>5. Cookies e tecnologias similares</h2>
        <p>Utilizamos cookies essenciais para o funcionamento seguro do site (por exemplo, registro de consentimento e preferências técnicas). Com o seu consentimento, também podemos usar cookies de preferências para lembrar escolhas de navegação.</p>
        <p>Você pode aceitar, recusar cookies não essenciais ou ajustar preferências pelo banner exibido no site. O navegador também permite bloquear cookies; a limitação de cookies essenciais pode afetar funcionalidades.</p>
        <p>Para mais detalhes sobre o uso do site, consulte também os <a href="termos-de-uso.php">Termos de Uso</a>.</p>
    </article>

    <article class="legal-section">
        <h2>6. Compartilhamento de dados</h2>
        <p>Podemos compartilhar dados pessoais com:</p>
        <ul>
            <li>Prestadores de infraestrutura, e-mail, atendimento, analytics (se adotados) e meios de pagamento, na medida necessária à operação.</li>
            <li>Autoridades públicas, mediante obrigação legal ou ordem válida.</li>
            <li>Assessores jurídicos, contábeis ou de segurança, sob dever de confidencialidade.</li>
        </ul>
        <p>Não vendemos dados pessoais. Exigimos de operadores contratuais padrões compatíveis com a LGPD e com a finalidade do serviço.</p>
    </article>

    <article class="legal-section">
        <h2>7. Transferências internacionais</h2>
        <p>Alguns provedores de tecnologia podem processar dados fora do Brasil. Nesses casos, adotamos salvaguardas adequadas previstas na LGPD e nas normas da ANPD, tais como cláusulas contratuais e avaliação do nível de proteção do destino, quando aplicável.</p>
    </article>

    <article class="legal-section">
        <h2>8. Retenção</h2>
        <p>Mantemos dados pelo tempo necessário às finalidades informadas, à vigência da relação contratual e aos prazos legais de guarda (fiscais, consumeristas, etc.). Após esse período, os dados são eliminados ou anonimizados, salvo hipótese legal de conservação.</p>
        <p>Logs de segurança e registros técnicos podem ser mantidos por prazos compatíveis com prevenção a incidentes e auditoria.</p>
    </article>

    <article class="legal-section">
        <h2>9. Segurança</h2>
        <p>Adotamos medidas técnicas e administrativas razoáveis para proteger dados pessoais contra acessos não autorizados, perda, alteração ou divulgação indevida. Nenhum método de transmissão ou armazenamento eletrônicos é absolutamente seguro; trabalhamos para reduzir riscos e responder a incidentes conforme a legislação.</p>
    </article>

    <article class="legal-section">
        <h2>10. Direitos do titular</h2>
        <p>Nos termos da LGPD, você pode solicitar:</p>
        <ul>
            <li>Confirmação da existência de tratamento e acesso aos dados.</li>
            <li>Correção de dados incompletos, inexatos ou desatualizados.</li>
            <li>Anonimização, bloqueio ou eliminação de dados desnecessários ou excessivos.</li>
            <li>Portabilidade, quando aplicável e observando segredo comercial/industrial.</li>
            <li>Informação sobre compartilhamentos e sobre a possibilidade de não consentir.</li>
            <li>Revogação do consentimento, quando o tratamento se basear nele.</li>
            <li>Oposição a tratamento realizado com base em legítimo interesse, na forma da lei.</li>
        </ul>
        <p>Para exercer direitos, envie solicitação ao canal de privacidade indicado abaixo, com informações suficientes para validar a identidade e atender o pedido. Responderemos nos prazos legais.</p>
        <p>Você também pode apresentar reclamação à Autoridade Nacional de Proteção de Dados (ANPD).</p>
    </article>

    <article class="legal-section">
        <h2>11. Menores de idade</h2>
        <p>Os serviços GeekHost destinam-se a pessoas capazes para contratar. Não coletamos intencionalmente dados de crianças ou adolescentes sem a devida base legal. Se identificar tratamento indevido, contate-nos para avaliação e exclusão quando cabível.</p>
    </article>

    <article class="legal-section">
        <h2>12. Alterações desta Política</h2>
        <p>Podemos atualizar esta Política para refletir mudanças legais, técnicas ou operacionais. A versão vigente será publicada nesta página, com a data de atualização. Recomendamos a revisão periódica.</p>
    </article>

    <article class="legal-section">
        <h2>13. Contato de privacidade / DPO</h2>
        <p>Para questões sobre privacidade, exercício de direitos LGPD ou comunicações ao encarregado (DPO) / responsável pelo tratamento de dados da FBS Tecnologia / GeekHost:</p>
        <ul>
            <li>E-mail: <a href="mailto:<?= e($companyEmail !== '' ? $companyEmail : 'contato@geekhost.com.br') ?>"><?= e($companyEmail !== '' ? $companyEmail : 'contato@geekhost.com.br') ?></a></li>
            <?php if ($companyWhatsapp !== ''): ?>
                <li>WhatsApp: <a href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer">+<?= e($companyWhatsapp) ?></a></li>
            <?php endif; ?>
            <li>Endereço: Rua Pais Leme, 215, Conj. 1713, Pinheiros, São Paulo/SP, CEP 05424-150</li>
            <li>Formulário: <a href="contato.php">página de Contato</a></li>
        </ul>
        <p class="legal-disclaimer">Documento de caráter informativo alinhado à LGPD. Trata-se de texto-base operacional; recomenda-se revisão jurídica específica conforme a operação e os fornecedores efetivamente utilizados.</p>
    </article>

    <nav class="legal-crosslinks" aria-label="Documentos relacionados">
        <a href="termos-de-uso.php">Termos de Uso <span aria-hidden="true">↗</span></a>
        <a href="contato.php">Falar com a GeekHost <span aria-hidden="true">↗</span></a>
    </nav>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
