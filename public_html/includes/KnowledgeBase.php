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
        $articles = [
            ['painel', 'entrar-no-painel', 'Como entrar no painel do cliente', 'Login em /painel/login com o e-mail da compra.', "1. Abra /painel/login.\n2. Use o e-mail cadastrado no checkout e a senha criada depois do pagamento.\n3. Se ainda não definiu senha, use o link enviado por e-mail em Criar senha.\n4. A sessão dura 7 horas neste aparelho."],
            ['painel', 'ver-faturas', 'Como ver e pagar faturas', 'A lista de cobranças fica em Faturas.', "1. Entre no painel.\n2. Abra Faturas.\n3. Filtre Em aberto.\n4. Use Pagar para abrir PIX, boleto ou cartão daquela cobrança.\n5. Minha Conta no site também leva para Faturas depois do login."],
            ['painel', 'abrir-ticket', 'Como abrir um ticket', 'Suporte com assunto, departamento e número GH.', "1. Abra Suporte no menu.\n2. Clique em Abrir novo ticket.\n3. Preencha Assunto, Departamento (Técnico, Financeiro ou Relacionamento), serviço se houver, prioridade e mensagem.\n4. Anexe arquivos permitidos se precisar.\n5. Depois do envio o chamado aparece com o código #GH- e o assunto."],
            ['pagamentos', 'segunda-via', 'Segunda via de PIX e boleto', 'Consulta sem login, com e-mail e documento.', "1. Abra /painel/segunda-via.\n2. Informe o e-mail principal da conta.\n3. Confirme com CPF/CNPJ cadastrado ou peça um link por e-mail.\n4. Se houver cobrança em aberto, copie o PIX, abra o boleto ou gere o pagamento."],
            ['pagamentos', 'pagamento-confirmado', 'Quando o pagamento libera a hospedagem', 'A confirmação chega pelo webhook do Asaas.', "PIX e boleto liberam a conta quando o Asaas confirma o pagamento. Cartão pode ter período de teste conforme o plano. Enquanto o status estiver aguardando pagamento, a senha do painel e o DirectAdmin não são criados."],
            ['dominios', 'cadastrar-dominio', 'Como cadastrar um domínio', 'O domínio entra no painel depois da hospedagem ativa.', "1. Abra Domínios no painel.\n2. Informe o domínio que você já registrou (Registro.br, GoDaddy ou outro).\n3. Aponte os nameservers ou o registro A para a hospedagem GeekHost quando o painel mostrar os dados.\n4. A propagação de DNS pode levar algumas horas."],
            ['sites', 'criar-site', 'Como criar um site', 'Site em branco ou WordPress a partir do painel.', "1. Confirme que a hospedagem está ativa.\n2. Abra Meus sites.\n3. Escolha domínio já cadastrado.\n4. Selecione site em branco ou WordPress.\n5. Aguarde o status sair de pendente. O acesso ao DirectAdmin aparece na mesma área quando a conta existir."],
            ['directadmin', 'acessar-directadmin', 'Como acessar o DirectAdmin', 'O painel da hospedagem é separado do painel do cliente.', "O painel do cliente (/painel) mostra faturas, domínios e tickets. O DirectAdmin é o painel técnico da conta de hospedagem (arquivos, e-mail, banco, PHP). O usuário e a senha são enviados quando o provisionamento termina. Não use a senha do /painel no DirectAdmin."],
            ['banco', 'criar-banco', 'Como criar um banco MySQL', 'Bancos ficam no DirectAdmin da conta.', "1. Entre no DirectAdmin da hospedagem.\n2. Abra MySQL Management ou Bancos de dados.\n3. Crie o banco e um usuário com senha.\n4. Associe o usuário ao banco com todos os privilégios.\n5. No site, use o nome completo (prefixo da conta + nome), host localhost, usuário e senha criados ali."],
            ['banco', 'importar-sql', 'Como importar um SQL', 'Use o phpMyAdmin da conta, sem comentários -- no início.', "No phpMyAdmin da hospedagem, escolha o banco e use Importar com um arquivo .sql. Evite colar comentários que começam com -- na aba SQL, porque alguns painéis descartam o restante do comando. Prefira um instalador PHP do projeto quando o SQL for grande."],
        ];
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
}
