<?php

declare(strict_types=1);

/**
 * One-shot GeekHost DB installer for cPanel/DirectAdmin.
 * 1) Put .env next to public_html (domains/geekhost.com.br/.env)
 * 2) Open: https://geekhost.com.br/install-db.php?key=SUA_CHAVE
 * 3) Delete this file after success.
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/includes/env.php';
geekhost_load_env();

$expected = (string) env('INSTALL_DB_KEY', '');
if ($expected === '') {
    $expected = 'geekhost-install-' . substr(hash('sha256', (string) env('DB_PASS', 'x')), 0, 12);
}
$key = (string) ($_GET['key'] ?? '');

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>GeekHost DB Install</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:820px;margin:40px auto;padding:0 16px;line-height:1.45}';
echo 'code,pre{background:#f4f4f5;padding:2px 6px;border-radius:6px}pre{padding:12px;overflow:auto}';
echo '.ok{color:#067647}.err{color:#b42318}.box{border:1px solid #e4e4e7;border-radius:12px;padding:16px;margin:16px 0}</style></head><body>';
echo '<h1>GeekHost — instalar banco</h1>';
echo '<p>Não use o SQL do phpMyAdmin (ele costuma mostrar “Documentação” vazio e 0 changes). Este instalador aplica o schema via PDO.</p>';

if (!hash_equals($expected, $key)) {
    echo '<div class="box err"><strong>Chave inválida.</strong><p>Abra com <code>?key=...</code></p>';
    echo '<p>No .env (pasta pai do public_html) defina <code>INSTALL_DB_KEY=geekhost-install-tmp</code>.</p>';
    echo '<p>Chave esperada neste servidor: <code>' . h($expected) . '</code></p></div>';
    echo '<p>Depois de instalar, <strong>apague</strong> <code>public_html/install-db.php</code>.</p></body></html>';
    exit;
}

$driver = strtolower((string) env('DB_DRIVER', 'mysql'));
if ($driver !== 'mysql') {
    echo '<p class="err">DB_DRIVER precisa ser mysql no .env de produção.</p></body></html>';
    exit;
}

$host = (string) env('DB_HOST', '127.0.0.1');
$port = (string) env('DB_PORT', '3306');
$name = (string) env('DB_NAME', '');
$user = (string) env('DB_USER', '');
$pass = (string) env('DB_PASS', '');

if ($name === '' || $user === '') {
    echo '<p class="err">DB_NAME / DB_USER ausentes no .env</p></body></html>';
    exit;
}

echo '<div class="box"><strong>Conectando</strong><br>host=' . h($host) . ' db=' . h($name) . ' user=' . h($user) . '</div>';

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    echo '<p class="err">Falha PDO: ' . h($e->getMessage()) . '</p>';
    echo '<p>Confira se o banco existe no cPanel e se o usuário tem permissão nele.</p></body></html>';
    exit;
}

$sqlFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'geekhost-cpanel-import.sql';
if (!is_readable($sqlFile)) {
    // Fallback: SQL next to this script if only public_html was uploaded
    $sqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'geekhost-cpanel-import.sql';
}
if (!is_readable($sqlFile)) {
    echo '<p class="err">Arquivo SQL não encontrado. Envie também <code>database/geekhost-cpanel-import.sql</code> (pai do public_html) ou copie para <code>public_html/geekhost-cpanel-import.sql</code>.</p></body></html>';
    exit;
}

$raw = file_get_contents($sqlFile);
if ($raw === false || trim($raw) === '') {
    echo '<p class="err">SQL vazio.</p></body></html>';
    exit;
}

// Strip BOM
if (str_starts_with($raw, "\xEF\xBB\xBF")) {
    $raw = substr($raw, 3);
}

$statements = [];
$buffer = '';
$lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
foreach ($lines as $line) {
    $trim = trim($line);
    if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '#')) {
        continue;
    }
    $buffer .= $line . "\n";
    if (str_ends_with(rtrim($line), ';')) {
        $stmt = trim($buffer);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }
        $buffer = '';
    }
}
if (trim($buffer) !== '') {
    $statements[] = trim($buffer);
}

$ok = 0;
$fail = 0;
echo '<ol>';
foreach ($statements as $i => $stmt) {
    $preview = preg_replace('/\s+/', ' ', substr($stmt, 0, 90)) ?? substr($stmt, 0, 90);
    try {
        $pdo->exec($stmt);
        $ok++;
        echo '<li class="ok">#' . ($i + 1) . ' OK — <code>' . h($preview) . '</code></li>';
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        // Ignore benign "already exists" style noise if any host returns it oddly
        if (stripos($msg, 'already exists') !== false) {
            $ok++;
            echo '<li class="ok">#' . ($i + 1) . ' already exists — <code>' . h($preview) . '</code></li>';
            continue;
        }
        $fail++;
        echo '<li class="err">#' . ($i + 1) . ' ERRO: ' . h($msg) . '<br><pre>' . h($stmt) . '</pre></li>';
    }
}
echo '</ol>';

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$admin = $pdo->query("SELECT id, email FROM users WHERE email = 'admin@geekhost.com.br' LIMIT 1")->fetch();

echo '<div class="box">';
echo '<p class="ok"><strong>Concluído:</strong> ' . $ok . ' ok / ' . $fail . ' erro(s)</p>';
echo '<p>Tabelas no banco (' . count($tables) . '): <code>' . h(implode(', ', array_map('strval', $tables))) . '</code></p>';
if ($admin) {
    echo '<p class="ok">Admin seed OK: <code>admin@geekhost.com.br</code> / senha <code>GeekHost@Admin1</code></p>';
    echo '<p>Login: <a href="/admin/login">/admin/login</a></p>';
} else {
    echo '<p class="err">Admin seed não encontrado — veja erros acima.</p>';
}
echo '<p><strong>Apague agora</strong> este arquivo <code>install-db.php</code> do servidor.</p>';
echo '</div></body></html>';
