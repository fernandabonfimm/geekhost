<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/logger.php';

/**
 * DirectAdmin HTTP client (modern REST + classic CMD_API).
 * Credentials stay server-side; never log passwords or login keys.
 *
 * @phpstan-type DaResponse array{status:int, body:array<string,mixed>|null, raw:string, headers:string}
 */
final class DirectAdminClient
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private bool $verifySsl;
    private bool $dryRun;
    private int $timeout;
    /** @var callable|null fn(string $method, string $url, array $opts): DaResponse */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        geekhost_load_env();
        $this->baseUrl = rtrim((string) env('DIRECTADMIN_URL', ''), '/');
        $this->username = (string) env('DIRECTADMIN_USERNAME', '');
        // Login keys authenticate as username + login_key (password field).
        $loginKey = (string) env('DIRECTADMIN_LOGIN_KEY', '');
        $accountPass = (string) env('DIRECTADMIN_PASSWORD', '');
        $this->password = $loginKey !== '' ? $loginKey : $accountPass;
        $this->verifySsl = env('DIRECTADMIN_VERIFY_SSL', '1') !== '0';
        $this->dryRun = env('DIRECTADMIN_DRY_RUN', '0') === '1';
        $this->timeout = max(5, (int) env('DIRECTADMIN_TIMEOUT', '30'));
        $this->transport = $transport;
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->username !== '' && $this->password !== '';
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return list<string>
     */
    public function listPackages(): array
    {
        $rest = $this->rest('GET', '/api/reseller-packages');
        if ($rest['status'] >= 200 && $rest['status'] < 300 && is_array($rest['body'])) {
            $names = [];
            $payload = $rest['body'];
            $items = $payload['packages'] ?? $payload['data'] ?? $payload;
            if (is_array($items)) {
                foreach ($items as $key => $item) {
                    if (is_string($key) && !is_numeric($key)) {
                        $names[] = $key;
                    } elseif (is_array($item)) {
                        $name = (string) ($item['id'] ?? $item['name'] ?? $item['package'] ?? '');
                        if ($name !== '') {
                            $names[] = $name;
                        }
                    } elseif (is_string($item)) {
                        $names[] = $item;
                    }
                }
            }
            if ($names !== []) {
                return array_values(array_unique($names));
            }
        }

        $cmd = $this->cmdApi('CMD_API_PACKAGES_USER', []);
        if (($cmd['error'] ?? '1') === '0' || isset($cmd['list'])) {
            $list = $cmd['list'] ?? [];
            if (is_string($list)) {
                return array_values(array_filter(explode(',', $list)));
            }
            if (is_array($list)) {
                return array_values(array_map('strval', $list));
            }
            $keys = [];
            foreach ($cmd as $k => $v) {
                if (!in_array($k, ['error', 'text', 'details', 'list'], true)) {
                    $keys[] = (string) $k;
                }
            }
            return $keys;
        }

        throw new RuntimeException('Não foi possível listar pacotes DirectAdmin.');
    }

    /**
     * @return array<string, mixed>
     */
    public function listUsers(): array
    {
        $rest = $this->rest('GET', '/api/users/list');
        if ($rest['status'] >= 200 && $rest['status'] < 300 && is_array($rest['body'])) {
            return $rest['body'];
        }
        $cmd = $this->cmdApi('CMD_API_SHOW_USERS', []);
        return $cmd;
    }

    public function userExists(string $username): bool
    {
        $username = strtolower(trim($username));
        if ($username === '') {
            return false;
        }
        $rest = $this->rest('GET', '/api/users/' . rawurlencode($username) . '/config');
        if ($rest['status'] === 200) {
            return true;
        }
        if ($rest['status'] === 404) {
            return false;
        }
        $cmd = $this->cmdApi('CMD_API_SHOW_USER_CONFIG', ['user' => $username]);
        return (($cmd['error'] ?? '1') === '0') || isset($cmd['username']);
    }

    /**
     * Create a hosting user via classic CMD_API (not exposed in modern REST swagger).
     *
     * @param array{username:string,email:string,password:string,domain:string,package:string,ip?:string,notify?:bool} $params
     * @return array<string, mixed>
     */
    public function createUser(array $params): array
    {
        if ($this->dryRun) {
            geekhost_log('da_dry_run', ['op' => 'createUser', 'username' => $params['username'] ?? null]);
            return ['error' => '0', 'text' => 'dry-run', 'dry_run' => true];
        }
        $payload = [
            'action' => 'create',
            'add' => 'Submit',
            'username' => $params['username'],
            'email' => $params['email'],
            'passwd' => $params['password'],
            'passwd2' => $params['password'],
            'domain' => $params['domain'],
            'package' => $params['package'],
            'ip' => $params['ip'] ?? 'shared',
            'notify' => !empty($params['notify']) ? 'yes' : 'no',
        ];
        return $this->cmdApi('CMD_API_ACCOUNT_USER', $payload, true);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Reseller backup of a client account, stored on the reseller server.
     *
     * @return array<string, mixed>
     */
    public function backupUser(string $username, string $domain = ''): array
    {
        if ($this->dryRun) {
            geekhost_log('da_dry_run', ['op' => 'backupUser', 'username' => $username, 'domain' => $domain]);
            return ['error' => '0', 'text' => 'dry-run', 'dry_run' => true];
        }
        $params = [
            'action' => 'backup',
            'select0' => $username,
            'who' => 'selected',
            'where' => 'local',
            'when' => 'now',
        ];
        if ($domain !== '') {
            $params['domain'] = $domain;
        }
        return $this->cmdApi('CMD_API_SITE_BACKUP', $params, true);
    }

    public function suspendUser(string $username): array
    {
        if ($this->dryRun) {
            geekhost_log('da_dry_run', ['op' => 'suspendUser', 'username' => $username]);
            return ['error' => '0', 'text' => 'dry-run', 'dry_run' => true];
        }
        return $this->cmdApi('CMD_API_SELECT_USERS', [
            'location' => 'CMD_SELECT_USERS',
            'suspend' => 'Suspend',
            'select0' => $username,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function unsuspendUser(string $username): array
    {
        if ($this->dryRun) {
            geekhost_log('da_dry_run', ['op' => 'unsuspendUser', 'username' => $username]);
            return ['error' => '0', 'text' => 'dry-run', 'dry_run' => true];
        }
        return $this->cmdApi('CMD_API_SELECT_USERS', [
            'location' => 'CMD_SELECT_USERS',
            'dosuspend' => 'Unsuspend',
            'select0' => $username,
        ], true);
    }

    /**
     * Add an additional domain to an existing user (classic API).
     *
     * @return array<string, mixed>
     */
    public function addDomain(string $username, string $domain): array
    {
        if ($this->dryRun) {
            geekhost_log('da_dry_run', ['op' => 'addDomain', 'username' => $username, 'domain' => $domain]);
            return ['error' => '0', 'text' => 'dry-run', 'dry_run' => true];
        }
        return $this->cmdApi('CMD_API_DOMAIN', [
            'action' => 'create',
            'domain' => $domain,
            'username' => $username,
            'user' => $username,
        ], true);
    }

    /**
     * Trigger Let's Encrypt / ACME cert provisioning for a domain (REST).
     *
     * @return array{status:int, body:array<string,mixed>|null, raw:string}
     */
    public function provisionSsl(string $domain, bool $dryRun = false): array
    {
        $path = '/api/domain-tls/domains/' . rawurlencode($domain) . '/provision-certs'
            . ($dryRun || $this->dryRun ? '-dry-run' : '');
        if ($this->dryRun && !$dryRun) {
            geekhost_log('da_dry_run', ['op' => 'provisionSsl', 'domain' => $domain]);
            return ['status' => 200, 'body' => ['dry_run' => true], 'raw' => '{}', 'headers' => ''];
        }
        return $this->rest('POST', $path);
    }

    /**
     * @return array{status:int, body:array<string,mixed>|null, raw:string}
     */
    public function sslDomainStatus(string $domain): array
    {
        return $this->rest('GET', '/api/domain-tls/domains/' . rawurlencode($domain) . '/certs');
    }

    /**
     * Softaculous/WordPress quick install — requires user session context (login-as).
     *
     * @param array{filePath:string,title:string,adminEmail?:string,adminName?:string,adminPass?:string} $payload
     * @return array{status:int, body:array<string,mixed>|null, raw:string}
     */
    public function installWordpressQuick(string $username, array $payload): array
    {
        if ($this->dryRun) {
            geekhost_log('da_dry_run', ['op' => 'installWordpressQuick', 'username' => $username]);
            return ['status' => 200, 'body' => ['dry_run' => true], 'raw' => '{}', 'headers' => ''];
        }
        $this->loginAs($username);
        try {
            return $this->rest('POST', '/api/wordpress/install-quick', $payload);
        } finally {
            $this->loginAsReturn();
        }
    }

    public function loginAs(string $username): void
    {
        if ($this->dryRun) {
            return;
        }
        $res = $this->rest('POST', '/api/session/login-as/switch', ['username' => $username]);
        if ($res['status'] >= 400) {
            throw new RuntimeException('DirectAdmin login-as falhou para ' . $username . ' (HTTP ' . $res['status'] . ').');
        }
    }

    public function loginAsReturn(): void
    {
        if ($this->dryRun) {
            return;
        }
        $this->rest('POST', '/api/session/login-as/return');
    }

    /**
     * One-time browser login for a client account. The reseller password never leaves the server.
     * Returns null while DIRECTADMIN_DRY_RUN=1.
     */
    public function oneTimeLoginUrl(string $username, string $redirect = '/'): ?string
    {
        $username = strtolower(trim($username));
        if ($username === '' || !$this->isConfigured() || $this->dryRun) {
            return null;
        }
        $key = bin2hex(random_bytes(16));
        $this->cmdApi('CMD_API_LOGIN_KEYS', [
            'action' => 'create',
            'type' => 'key',
            'keyname' => 'gh' . substr($key, 0, 8),
            'key' => $key,
            'key2' => $key,
            'expiry' => (string) (time() + 600),
            'max_uses' => '1',
            'clear_key' => 'no',
            'allow_htm' => 'yes',
        ], true, $username);
        $path = $redirect !== '' ? $redirect : '/';
        return $this->baseUrl . '/CMD_LOGIN?username=' . rawurlencode($username)
            . '&password=' . rawurlencode($key)
            . '&referer=' . rawurlencode($path);
    }

    /** @return list<string> */
    public function listDatabases(string $username): array
    {
        if ($username === '' || !$this->isConfigured() || $this->dryRun) {
            return [];
        }
        $parsed = $this->cmdApi('CMD_API_DATABASES', ['action' => 'list'], false, $username);
        $list = $parsed['list'] ?? [];
        if (is_string($list) && $list !== '') {
            return [$list];
        }
        if (!is_array($list)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $list)));
    }

    /**
     * Read-only connectivity probe.
     *
     * @return array{ok:bool, http_status:int, message:string, packages?:list<string>}
     */
    public function probe(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'http_status' => 0, 'message' => 'DirectAdmin não configurado (URL/username/credencial).'];
        }
        try {
            $res = $this->rest('GET', '/api/users/list');
            if ($res['status'] === 403 && str_contains($res['raw'], 'Imunify360')) {
                return [
                    'ok' => false,
                    'http_status' => 403,
                    'message' => 'Imunify360 bloqueou o IP de automação — liberar whitelist no ValueServer.',
                ];
            }
            if ($res['status'] === 401) {
                return [
                    'ok' => false,
                    'http_status' => 401,
                    'message' => 'Unauthorized — confira DIRECTADMIN_USERNAME + LOGIN_KEY (senha = login key).',
                ];
            }
            if ($res['status'] >= 200 && $res['status'] < 300) {
                $packages = [];
                try {
                    $packages = $this->listPackages();
                } catch (Throwable $e) {
                    // ignore package list failure after successful auth
                }
                return [
                    'ok' => true,
                    'http_status' => $res['status'],
                    'message' => 'Conectado ao DirectAdmin.',
                    'packages' => $packages,
                ];
            }
            return [
                'ok' => false,
                'http_status' => $res['status'],
                'message' => 'Resposta inesperada do DirectAdmin (HTTP ' . $res['status'] . ').',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'http_status' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed>|null $jsonBody
     * @return array{status:int, body:array<string,mixed>|null, raw:string, headers:string}
     */
    public function rest(string $method, string $path, ?array $jsonBody = null): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $headers = [
            'Accept: application/json',
            'User-Agent: GeekHost-DA/1.0',
        ];
        $opts = [
            'headers' => $headers,
            'userpwd' => $this->username . ':' . $this->password,
        ];
        if ($jsonBody !== null) {
            $opts['headers'][] = 'Content-Type: application/json';
            $opts['body'] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $this->request($method, $url, $opts, true);
    }

    /**
     * Classic DirectAdmin CMD_API_* endpoint (form body, query-string response).
     *
     * @param array<string, scalar|null> $params
     * @return array<string, mixed>
     */
    public function cmdApi(string $command, array $params = [], bool $mutating = false, ?string $asUser = null): array
    {
        $command = strtoupper(ltrim($command, '/'));
        if (!str_starts_with($command, 'CMD_API_')) {
            $command = 'CMD_API_' . $command;
        }
        if ($mutating && $this->dryRun) {
            geekhost_log('da_dry_run', ['op' => $command]);
            return ['error' => '0', 'text' => 'dry-run', 'dry_run' => true];
        }
        $url = $this->baseUrl . '/' . $command;
        $loginUser = $this->username;
        $asUser = $asUser !== null ? strtolower(trim($asUser)) : '';
        if ($asUser !== '' && $asUser !== strtolower($this->username)) {
            $loginUser .= '|' . $asUser;
        }
        $res = $this->request('POST', $url, [
            'headers' => [
                'Accept: text/plain',
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: GeekHost-DA/1.0',
            ],
            'userpwd' => $loginUser . ':' . $this->password,
            'body' => http_build_query($params),
        ], false);

        if ($res['status'] === 403 && str_contains($res['raw'], 'Imunify360')) {
            throw new RuntimeException('Imunify360 bloqueou o IP de automação DirectAdmin.');
        }
        if ($res['status'] === 401) {
            throw new RuntimeException('DirectAdmin unauthorized (CMD_API).');
        }

        $parsed = [];
        parse_str($res['raw'], $parsed);
        if ($parsed === [] && $res['raw'] !== '') {
            $json = json_decode($res['raw'], true);
            if (is_array($json)) {
                $parsed = $json;
            } else {
                $parsed = ['raw' => $res['raw'], 'http_status' => $res['status']];
            }
        }
        $parsed['_http_status'] = $res['status'];
        if (($parsed['error'] ?? '0') === '1' || (isset($parsed['error']) && $parsed['error'] !== '0' && $parsed['error'] !== 0)) {
            $detail = (string) ($parsed['details'] ?? $parsed['text'] ?? 'CMD_API error');
            geekhost_log('da_cmd_error', ['command' => $command, 'detail' => $detail, 'status' => $res['status']]);
            throw new RuntimeException('DirectAdmin: ' . $detail);
        }
        return $parsed;
    }

    /**
     * @param array{headers?:list<string>, userpwd?:string, body?:string, cookie?:string} $opts
     * @return array{status:int, body:array<string,mixed>|null, raw:string, headers:string}
     */
    private function request(string $method, string $url, array $opts, bool $expectJson): array
    {
        if ($this->transport) {
            return ($this->transport)($method, $url, $opts);
        }

        if (!$this->isConfigured()) {
            throw new RuntimeException('DirectAdmin credentials missing.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to init cURL for DirectAdmin.');
        }

        $headers = $opts['headers'] ?? [];
        $curl = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
        ];
        if (!empty($opts['userpwd'])) {
            $curl[CURLOPT_USERPWD] = $opts['userpwd'];
            $curl[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        }
        if (isset($opts['body'])) {
            $curl[CURLOPT_POSTFIELDS] = $opts['body'];
        }
        if (!empty($opts['cookie'])) {
            $curl[CURLOPT_COOKIE] = $opts['cookie'];
        }
        curl_setopt_array($ch, $curl);

        $rawFull = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if ($errno !== 0) {
            geekhost_log('da_curl_error', ['errno' => $errno, 'error' => $error, 'path' => parse_url($url, PHP_URL_PATH)]);
            throw new RuntimeException('Falha de comunicação com DirectAdmin.');
        }

        $headerStr = is_string($rawFull) ? substr($rawFull, 0, $headerSize) : '';
        $raw = is_string($rawFull) ? substr($rawFull, $headerSize) : '';
        $body = null;
        if ($expectJson && $raw !== '') {
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : null;
        }

        if ($status >= 400) {
            geekhost_log('da_http_error', [
                'status' => $status,
                'path' => parse_url($url, PHP_URL_PATH),
                'type' => is_array($body) ? ($body['type'] ?? null) : null,
            ]);
        }

        return [
            'status' => $status,
            'body' => $body,
            'raw' => $raw,
            'headers' => $headerStr,
        ];
    }
}
