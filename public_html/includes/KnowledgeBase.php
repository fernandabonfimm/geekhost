<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class KnowledgeBase
{
    private PDO $db;

    public function __construct()
    {
        $this->db = geekhost_db();
        $this->ensure();
        $this->seedIfEmpty();
        $this->upgradeDefaultBodies();
    }

    /**
     * @return array{html:string, toc:list<array{id:string,title:string}>, minutes:int}
     */
    public function presentBody(string $body): array
    {
        $words = preg_split('/\s+/u', trim($body)) ?: [];
        $minutes = max(1, (int) ceil(count(array_filter($words, static fn ($w) => $w !== '')) / 180));
        $parts = preg_split('/^##\s+(.+)\s*$/m', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            $parts = [$body];
        }
        $intro = $this->blocksToHtml((string) array_shift($parts));
        $toc = [];
        $html = $intro;
        for ($i = 0; $i < count($parts); $i += 2) {
            $title = trim((string) ($parts[$i] ?? ''));
            $content = (string) ($parts[$i + 1] ?? '');
            if ($title === '') {
                continue;
            }
            $id = $this->slug($title);
            if ($id === '') {
                $id = 'secao-' . (count($toc) + 1);
            }
            $toc[] = ['id' => $id, 'title' => $title];
            $html .= '<h2 id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
            $html .= $this->blocksToHtml($content);
        }
        return ['html' => $html, 'toc' => $toc, 'minutes' => $minutes];
    }

    private function blocksToHtml(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $blocks = preg_split("/\n{2,}/", $text) ?: [];
        $html = '';
        foreach ($blocks as $block) {
            $lines = preg_split("/\n/", trim($block)) ?: [];
            $lines = array_values(array_filter($lines, static fn ($line) => trim($line) !== ''));
            if ($lines === []) {
                continue;
            }
            $ordered = true;
            $unordered = true;
            foreach ($lines as $line) {
                if (preg_match('/^\d+\.\s+/', $line) !== 1) {
                    $ordered = false;
                }
                if (preg_match('/^[-*]\s+/', $line) !== 1) {
                    $unordered = false;
                }
            }
            if ($ordered && count($lines) > 0) {
                $html .= '<ol>';
                foreach ($lines as $line) {
                    $html .= '<li>' . htmlspecialchars((string) preg_replace('/^\d+\.\s+/', '', $line), ENT_QUOTES, 'UTF-8') . '</li>';
                }
                $html .= '</ol>';
                continue;
            }
            if ($unordered) {
                $html .= '<ul>';
                foreach ($lines as $line) {
                    $html .= '<li>' . htmlspecialchars((string) preg_replace('/^[-*]\s+/', '', $line), ENT_QUOTES, 'UTF-8') . '</li>';
                }
                $html .= '</ul>';
                continue;
            }
            $html .= '<p>' . htmlspecialchars(implode(' ', array_map('trim', $lines)), ENT_QUOTES, 'UTF-8') . '</p>';
        }
        return $html;
    }

    /** @return list<array<string,mixed>> */
    public function categories(): array
    {
        $rows = $this->db->query(
            'SELECT c.*, (SELECT COUNT(*) FROM kb_articles a WHERE a.category_id = c.id AND a.published = 1) AS article_count
             FROM kb_categories c ORDER BY c.sort_order, c.title'
        )->fetchAll() ?: [];
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    public function articles(?string $categorySlug = null, string $q = ''): array
    {
        $sql = 'SELECT a.*, c.title AS category_title, c.slug AS category_slug
                FROM kb_articles a INNER JOIN kb_categories c ON c.id = a.category_id
                WHERE a.published = 1';
        $params = [];
        if ($categorySlug) {
            $sql .= ' AND c.slug = :s';
            $params[':s'] = $categorySlug;
        }
        if ($q !== '') {
            $sql .= ' AND (a.title LIKE :q OR a.summary LIKE :q OR a.body LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY a.sort_order, a.title';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public function article(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, c.title AS category_title, c.slug AS category_slug
             FROM kb_articles a INNER JOIN kb_categories c ON c.id = a.category_id
             WHERE a.slug = :s AND a.published = 1 LIMIT 1'
        );
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function adminArticles(): array
    {
        return $this->db->query(
            'SELECT a.*, c.title AS category_title FROM kb_articles a
             INNER JOIN kb_categories c ON c.id = a.category_id
             ORDER BY c.sort_order, a.sort_order, a.title'
        )->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM kb_articles WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?int $id = null): int
    {
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $summary = trim((string) ($data['summary'] ?? ''));
        $categoryId = (int) ($data['category_id'] ?? 0);
        $slug = $this->slug((string) ($data['slug'] ?? $title));
        if ($title === '' || $body === '' || $categoryId <= 0 || $slug === '') {
            throw new InvalidArgumentException('Título, categoria e conteúdo são obrigatórios.');
        }
        $now = gmdate('c');
        $published = !empty($data['published']) ? 1 : 0;
        if ($id) {
            $this->db->prepare(
                'UPDATE kb_articles SET category_id=:c, slug=:s, title=:t, summary=:u, body=:b, published=:p, updated_at=:up WHERE id=:id'
            )->execute([
                ':c' => $categoryId, ':s' => $slug, ':t' => $title, ':u' => $summary, ':b' => $body,
                ':p' => $published, ':up' => $now, ':id' => $id,
            ]);
            return $id;
        }
        $this->db->prepare(
            'INSERT INTO kb_articles (category_id, slug, title, summary, body, published, sort_order, created_at, updated_at)
             VALUES (:c,:s,:t,:u,:b,:p,50,:cr,:up)'
        )->execute([
            ':c' => $categoryId, ':s' => $slug, ':t' => $title, ':u' => $summary, ':b' => $body,
            ':p' => $published, ':cr' => $now, ':up' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM kb_articles WHERE id = :id')->execute([':id' => $id]);
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($ascii) && $ascii !== '') {
                $value = $ascii;
            }
        }
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function ensure(): void
    {
        $mysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS kb_categories (
                id {$id},
                slug VARCHAR(80) NOT NULL UNIQUE,
                title VARCHAR(160) NOT NULL,
                summary VARCHAR(255) NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 0
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS kb_articles (
                id {$id},
                category_id INTEGER NOT NULL,
                slug VARCHAR(120) NOT NULL UNIQUE,
                title VARCHAR(190) NOT NULL,
                summary VARCHAR(255) NOT NULL DEFAULT '',
                body TEXT NOT NULL,
                published INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at VARCHAR(40) NOT NULL,
                updated_at VARCHAR(40) NOT NULL
            )"
        );
    }

    private function seedIfEmpty(): void
    {
        $n = (int) $this->db->query('SELECT COUNT(*) FROM kb_articles')->fetchColumn();
        if ($n > 0) {
            return;
        }
        $cats = [
            ['painel', 'Área do cliente', 'Login, faturas, perfil e tickets no painel GeekHost.', 1],
            ['pagamentos', 'Pagamentos', 'PIX, boleto, segunda via e confirmação de pagamento.', 2],
            ['dominios', 'Domínios e DNS', 'Cadastro, apontamento e DNS no painel.', 3],
            ['sites', 'Sites', 'Criar site, WordPress e arquivos.', 4],
            ['directadmin', 'DirectAdmin', 'Acesso ao painel da hospedagem.', 5],
            ['banco', 'Banco de dados', 'MySQL no DirectAdmin.', 6],
        ];
        $insC = $this->db->prepare('INSERT INTO kb_categories (slug, title, summary, sort_order) VALUES (?,?,?,?)');
        foreach ($cats as $c) {
            $insC->execute($c);
        }
        $ids = [];
        foreach ($this->db->query('SELECT id, slug FROM kb_categories')->fetchAll() as $row) {
            $ids[(string) $row['slug']] = (int) $row['id'];
        }
        $now = gmdate('c');
        $articles = $this->defaultArticles();
        $ins = $this->db->prepare(
            'INSERT INTO kb_articles (category_id, slug, title, summary, body, published, sort_order, created_at, updated_at)
             VALUES (:c,:s,:t,:u,:b,1,:o,:cr,:up)'
        );
        $i = 1;
        foreach ($articles as [$cat, $slug, $title, $summary, $body]) {
            $ins->execute([
                ':c' => $ids[$cat], ':s' => $slug, ':t' => $title, ':u' => $summary, ':b' => $body,
                ':o' => $i++, ':cr' => $now, ':up' => $now,
            ]);
        }
    }

    private function upgradeDefaultBodies(): void
    {
        try {
            $flag = $this->db->prepare('SELECT setting_key FROM app_settings WHERE setting_key = :k LIMIT 1');
            $flag->execute([':k' => 'kb.layout_v2']);
            if ($flag->fetchColumn()) {
                return;
            }
        } catch (Throwable $e) {
            return;
        }
        $now = gmdate('c');
        $upd = $this->db->prepare('UPDATE kb_articles SET summary = :u, body = :b, updated_at = :up WHERE slug = :s');
        foreach ($this->defaultArticles() as [$cat, $slug, $title, $summary, $body]) {
            unset($cat, $title);
            $upd->execute([':u' => $summary, ':b' => $body, ':up' => $now, ':s' => $slug]);
        }
        $this->db->prepare('INSERT INTO app_settings (setting_key, value_json, updated_at) VALUES (:k, :v, :u)')
            ->execute([':k' => 'kb.layout_v2', ':v' => '{"upgraded":true}', ':u' => $now]);
    }

    /** @return list<array{0:string,1:string,2:string,3:string,4:string}> */
    private function defaultArticles(): array
    {
        return [
            ['painel', 'entrar-no-painel', 'Como entrar no painel do cliente', 'Login em /painel/login com o e-mail da compra e a senha da conta.', <<<'TXT'
O painel do cliente é o lugar em que você vê faturas, domínios, sites e tickets. O endereço é /painel/login. Ele não é o DirectAdmin: a senha da hospedagem técnica não entra aqui.

## Antes de entrar
Use o e-mail informado na compra. A senha é a que você definiu na conta ou a senha temporária enviada por e-mail quando o cartão foi aprovado ou ficou pendente. A sessão dura 7 horas neste aparelho e depois pede a senha de novo.

Confira também se está no painel do cliente, e não no login do admin. O admin é só da equipe GeekHost.

## Como entrar
1. No site, abra Minha Conta ou acesse direto /painel/login.
2. Digite o e-mail da compra no campo Endereço de e-mail.
3. Digite a senha. O ícone do olho mostra o que foi digitado.
4. Clique em Entrar.
5. Se você veio de Faturas ou de outra página do painel, o login devolve você para essa página.

## Se a senha não funcionar
A mensagem E-mail ou senha inválidos aparece quando o e-mail não é o da conta ou a senha não confere. Clique em Esqueceu a senha, peça o código no e-mail e crie outra senha. Olhe também o spam.

Se o pagamento no cartão acabou de ser aceito, abra o e-mail "Cartão confirmado" e use a senha temporária. Depois, em Perfil, troque por uma senha sua.

## Depois de entrar
O início mostra o resumo da conta. Faturas lista o que está em aberto e o que já foi pago. Domínios e Meus sites só liberam o cadastro quando existe um plano pago. Suporte é onde o ticket fica com o número #GH-. Sair encerra só a sessão deste aparelho.

## Se não resolver
Se o login continua falhando, use o botão no fim desta página para abrir um ticket. Informe o e-mail da conta e o texto exato que aparece na tela, sem enviar a senha.
TXT],
            ['painel', 'ver-faturas', 'Como ver e pagar faturas', 'A lista de cobranças fica em Faturas, no painel do cliente.', <<<'TXT'
Cada cobrança da hospedagem vira uma fatura no painel do cliente. PIX, boleto e cartão aparecem ali com o status e o valor.

## Onde abrir
1. Entre em /painel/login.
2. No menu, abra Faturas.
3. O atalho Minha Conta no site também cai em Faturas depois do login.

## Como pagar uma fatura em aberto
1. Filtre por Em aberto.
2. Abra a cobrança.
3. Use Pagar para gerar o PIX, o boleto ou seguir com o cartão daquela fatura.
4. Guarde o número do pedido se precisar falar com o suporte.

## O que cada status significa
Em aberto ainda não compensou. Pago libera a hospedagem. Cartão confirmado pode estar nos 7 dias grátis, com a primeira cobrança numa data futura. Cancelado ou reembolsado não gera nova liberação.

## Se não resolver
Se o pagamento já saiu da conta e a fatura continua em aberto, abra um ticket com o e-mail da conta, o valor e a data.
TXT],
            ['painel', 'abrir-ticket', 'Como abrir um ticket', 'Chamado no painel do cliente, com departamento e número #GH-.', <<<'TXT'
O ticket é o canal de suporte da conta. Ele fica no painel do cliente, não no DirectAdmin.

## Antes de abrir
Entre em /painel/login. Tenha em mãos o e-mail da conta, o domínio e, se for financeiro, o número da fatura. Anexos aceitos são os tipos comuns de imagem, PDF e texto, até 8 MB cada, no máximo 5 arquivos.

## Como abrir
1. Abra Suporte no menu do painel.
2. Clique em abrir um novo ticket.
3. Preencha nome, e-mail, assunto e departamento: Suporte Técnico e Dúvidas, Assuntos Financeiros ou Solução e Relacionamento.
4. Em serviço relacionado, escolha a hospedagem ou deixe Nenhum.
5. A prioridade padrão é Média. Escreva a mensagem com o passo em que parou.
6. Envie. O chamado aparece na lista com o código #GH- e o assunto.

## Como acompanhar
A lista mostra departamento, assunto, status e a última atualização. Abra o chamado para responder. Quando a equipe escrever, o aviso chega se as preferências de e-mail estiverem ligadas em Perfil.

## Se não resolver
Se o formulário não envia, anote a mensagem de erro e tente de novo depois de entrar outra vez. Um segundo ticket com o mesmo assunto ajuda a equipe a achar o anterior pelo e-mail.
TXT],
            ['pagamentos', 'segunda-via', 'Segunda via de PIX e boleto', 'Consulta da cobrança em aberto com e-mail e documento, ou com um link enviado ao e-mail.', <<<'TXT'
A segunda via serve para pagar uma cobrança em aberto sem decorar a senha do painel. O documento só confirma que a pessoa é a dona da conta. Ele não vira um novo cadastro.

## Como consultar
1. Abra /painel/segunda-via.
2. Informe o e-mail principal da conta.
3. Confirme com CPF ou CNPJ, ou peça um link de acesso por e-mail.
4. O link vale por 1 hora.

## O que aparece
Se houver cobrança em aberto, a página lista as faturas. Dá para copiar o PIX, abrir o boleto ou seguir para o pagamento. Se não houver nada em aberto, a página diz isso com clareza. Não inventa cobrança.

## Quem já está logado
Com a sessão do painel ativa, a segunda via mostra as faturas da própria conta, sem pedir o documento de novo.

## Se não resolver
Se o documento confere e a fatura que você espera não aparece, abra um ticket com o e-mail da compra e o valor.
TXT],
            ['pagamentos', 'pagamento-confirmado', 'Quando o pagamento libera a hospedagem', 'PIX e boleto liberam quando o Asaas confirma. No cartão, a conta entra no painel na hora.', <<<'TXT'
A hospedagem só fica disponível depois que o pagamento é reconhecido. O painel do cliente e o DirectAdmin não são criados enquanto a cobrança estiver só aguardando.

## PIX e boleto
O PIX libera quando o pagamento é confirmado. O boleto espera a compensação bancária. A página do checkout mostra o QR Code ou o boleto e atualiza sozinha no PIX.

## Cartão
No cartão há 7 dias grátis. O cartão é salvo na confirmação e a primeira cobrança sai depois desse prazo. Se o cartão for recusado, o checkout abre o WhatsApp com o plano, o valor e o método. Se ficar pendente ou for aprovado, você recebe um e-mail com os dados da compra e uma senha temporária, e entra direto no painel.

## Se não resolver
Guarde o e-mail da compra e o número do pedido. Se o dinheiro saiu e o painel não abre, abra um ticket em Assuntos Financeiros.
TXT],
            ['dominios', 'cadastrar-dominio', 'Como cadastrar um domínio', 'O domínio entra no painel do cliente depois que o plano está pago.', <<<'TXT'
Cadastrar o domínio no painel avisa a GeekHost qual endereço o site vai usar. O registro do domínio continua no Registro.br ou em outro registrador. A GeekHost não substitui esse registro.

## Antes de cadastrar
O plano precisa estar pago. Sem pagamento, Domínios mostra o aviso e não deixa incluir endereço. O limite depende do plano. O Light aceita um domínio.

## Como cadastrar
1. Entre no painel do cliente e abra Domínios.
2. Clique em Cadastrar domínio.
3. Informe o domínio que você já registrou, sem https e sem www.
4. Salve. A página confere se os nameservers já apontam para ns1.geekhost.com.br e ns2.geekhost.com.br.

## DNS e propagação
Se os nameservers ainda não forem os da GeekHost, o domínio fica aguardando DNS. Ajuste no registrador e use atualizar na lista. A propagação leva de 2 a 72 horas. Enquanto isso, criar o site fica bloqueado de propósito.

## Se não resolver
Se o registrador já mostra os nameservers certos e o painel continua em aguardando, espere a propagação e atualize de novo. Se passar de 72 horas, abra um ticket com o domínio e um print da zona DNS.
TXT],
            ['sites', 'criar-site', 'Como criar um site', 'O site nasce de um domínio já cadastrado e com DNS apontado.', <<<'TXT'
O site no painel é a hospedagem daquele domínio. Sem domínio cadastrado, Meus sites pede para começar por Domínios.

## Antes de criar
1. Tenha o plano pago.
2. Cadastre o domínio.
3. Espere os nameservers ns1 e ns2 da GeekHost aparecerem como propagados.

## Como criar
1. Em Domínios, no domínio propagado, use Criar meu site.
2. Ou abra Meus sites e escolha Novo site no domínio que já está ok.
3. O site entra na lista. O WordPress, se você pedir, fica na fila até a hospedagem técnica estar ativa.

## O que o status quer dizer
Cadastrado significa que o painel já conhece o site. Ativo aparece quando a hospedagem no servidor está pronta. Provisionando WP é a instalação do WordPress na fila.

## Se não resolver
Se o botão não cria o site, leia o aviso na própria página. O caso mais comum é o DNS ainda não propagado. Se o DNS está ok e o erro continua, abra um ticket com o domínio.
TXT],
            ['directadmin', 'acessar-directadmin', 'Como acessar o DirectAdmin', 'O DirectAdmin é o painel técnico da hospedagem. A senha dele não é a do /painel.', <<<'TXT'
O painel do cliente mostra faturas, domínios e tickets. O DirectAdmin é onde ficam arquivos, e-mails da hospedagem, PHP e bancos. São dois logins diferentes.

## Quando o acesso existe
O usuário e a senha do DirectAdmin são enviados quando o provisionamento da conta termina. Antes disso, Meus sites pode mostrar aguardando provisionamento. Não tente a senha do /painel no DirectAdmin.

## Como entrar
1. Use o endereço do DirectAdmin que veio no e-mail da hospedagem.
2. Informe o usuário da conta, não o e-mail do painel do cliente, salvo se a mensagem disser que são iguais.
3. Use a senha enviada para essa conta técnica.
4. Troque essa senha no próprio DirectAdmin se quiser.

## O que fazer lá
Arquivos do site, contas de e-mail do domínio, versão do PHP e bancos MySQL ficam nesse painel. O painel do cliente não edita arquivo do site.

## Se não resolver
Se o e-mail com o usuário não chegou e a hospedagem já está ativa, abra um ticket em Suporte Técnico. Informe o domínio e o e-mail da conta GeekHost. Não envie senha antiga no ticket.
TXT],
            ['banco', 'criar-banco', 'Como criar um banco MySQL', 'O banco é criado no DirectAdmin da hospedagem, não no painel do cliente.', <<<'TXT'
Sites em PHP, como WordPress, precisam de um banco MySQL. Esse banco nasce na conta de hospedagem, no DirectAdmin.

## Antes de criar
Tenha o acesso do DirectAdmin da sua hospedagem. O nome do banco e do usuário ganham um prefixo da conta. Anote o nome completo, não só o sufixo que você digitou.

## Como criar
1. Entre no DirectAdmin.
2. Abra MySQL Management ou Bancos de dados.
3. Crie o banco.
4. Crie um usuário com senha própria, diferente da senha do painel do cliente.
5. Associe o usuário ao banco com todos os privilégios.
6. No site, use host localhost, o nome completo do banco, o usuário completo e a senha criada ali.

## Se não resolver
Se o DirectAdmin recusar a criação, anote a mensagem e abra um ticket com o domínio da hospedagem. Não cole a senha do banco no ticket.
TXT],
            ['banco', 'importar-sql', 'Como importar um arquivo SQL', 'A importação é no phpMyAdmin da conta de hospedagem.', <<<'TXT'
Um arquivo .sql recria tabelas e dados num banco que já existe. A importação acontece no phpMyAdmin da sua hospedagem, depois que o banco foi criado.

## Como importar
1. Entre no DirectAdmin e abra o phpMyAdmin.
2. Selecione o banco certo na lista da esquerda.
3. Use a aba Importar e envie o arquivo .sql.
4. Espere a mensagem de sucesso antes de apontar o site para esse banco.

## Cuidado com a aba SQL
Alguns painéis ignoram o restante do comando quando a primeira linha é um comentário que começa com --. Prefira Importar o arquivo. Se o SQL for grande, use o instalador do próprio projeto em vez de colar o conteúdo na aba SQL.

## Se não resolver
Se a importação parar no meio, anote o erro do phpMyAdmin e o tamanho do arquivo. Abra um ticket com o domínio e esse texto. Não anexe um dump com senhas de produção se puder evitar.
TXT],
        ];
    }
}
