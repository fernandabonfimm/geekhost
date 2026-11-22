<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/csrf.php';

final class Auth
{
    public const SESSION_HOURS = 7;
    public const COOKIE = 'gh_panel_session';
    public const SETUP_HOURS = 48;

    private PDO $db;
    private ?string $lastIssuedToken = null;
    private ?int $currentSessionId = null;

    public function __construct()
    {
        $this->db = geekhost_db();
        geekhost_boot_session();
        $this->ensureCodes();
    }

    /**
     * @param array<string, string> $profile
     * @return array{user: array<string,mixed>, setupToken: ?string}
     */
    public function registerFromCheckout(int $customerId, string $email, array $profile): array
    {
        $email = strtolower(trim($email));
        $existing = $this->findByEmail($email);
        if ($existing) {
            $this->updateEncryptedProfile((int) $existing['id'], $profile);
            if ($customerId > 0) {
                $stmt = $this->db->prepare('UPDATE users SET customer_id = COALESCE(customer_id, :c), updated_at = :u WHERE id = :id');
                $stmt->execute([':c' => $customerId, ':u' => gmdate('c'), ':id' => $existing['id']]);
            }
            $setupToken = null;
            if (!(int) ($existing['password_is_set'] ?? 1)) {
                $setupToken = $this->issuePasswordSetupToken((int) $existing['id']);
            }
            $user = $this->findById((int) $existing['id']) ?? $existing;
            return ['user' => $user, 'setupToken' => $setupToken];
        }

        $now = gmdate('c');
        $publicId = $this->uuid();
        $placeholderHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            'INSERT INTO users (
                public_id, customer_id, email, password_hash, password_is_set,
                name_enc, phone_enc, mobile_enc, cpf_cnpj_enc, postal_code_enc, address_enc,
                address_number_enc, complement_enc, province_enc, city_enc,
                hosting_status, created_at, updated_at
            ) VALUES (
                :pid, :cid, :email, :ph, 0,
                :name, :phone, :mobile, :cpf, :zip, :addr,
                :num, :comp, :uf, :city,
                :hs, :c, :u
            )'
        );
        $stmt->execute([
            ':pid' => $publicId,
            ':cid' => $customerId > 0 ? $customerId : null,
            ':email' => $email,
            ':ph' => $placeholderHash,
            ':name' => geekhost_encrypt($profile['name'] ?? '') ?? '',
            ':phone' => geekhost_encrypt($profile['phone'] ?? '') ?? '',
            ':mobile' => geekhost_encrypt($profile['mobile_phone'] ?? '') ?? '',
            ':cpf' => geekhost_encrypt($profile['cpf_cnpj'] ?? '') ?? '',
            ':zip' => geekhost_encrypt($profile['postal_code'] ?? '') ?? '',
            ':addr' => geekhost_encrypt($profile['address'] ?? '') ?? '',
            ':num' => geekhost_encrypt($profile['address_number'] ?? '') ?? '',
            ':comp' => geekhost_encrypt($profile['complement'] ?? '') ?? '',
            ':uf' => geekhost_encrypt($profile['province'] ?? '') ?? '',
            ':city' => geekhost_encrypt($profile['city'] ?? '') ?? '',
            ':hs' => 'pending',
            ':c' => $now,
            ':u' => $now,
        ]);

        $userId = (int) $this->db->lastInsertId();
        $setupToken = $this->issuePasswordSetupToken($userId);
        return ['user' => $this->findById($userId) ?? [], 'setupToken' => $setupToken];
    }

    public function issuePasswordSetupToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare(
            'UPDATE users SET password_setup_token_hash = :h, password_setup_expires = :e, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            ':h' => hash('sha256', $token),
            ':e' => gmdate('c', time() + self::SETUP_HOURS * 3600),
            ':u' => gmdate('c'),
            ':id' => $userId,
        ]);
        return $token;
    }

    public function findUserBySetupToken(string $token): ?array
    {
        $token = trim($token);
        if (strlen($token) < 32) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE password_setup_token_hash = :h AND password_is_set = 0 LIMIT 1'
        );
        $stmt->execute([':h' => hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $expires = strtotime((string) ($row['password_setup_expires'] ?? ''));
        if ($expires === false || $expires < time()) {
            return null;
        }
        return $row;
    }

    public function userHasPaidOrder(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM orders WHERE user_id = :u AND status = 'paid_pending_provisioning' LIMIT 1"
        );
        $stmt->execute([':u' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function completePasswordSetup(string $token, string $password, string $confirm): array
    {
        if ($password !== $confirm) {
            throw new InvalidArgumentException('A confirmação de senha não confere.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }
        $user = $this->findUserBySetupToken($token);
        if (!$user) {
            throw new InvalidArgumentException('Link de criação de senha inválido ou expirado.');
        }
        $userId = (int) $user['id'];
        if (!$this->userHasPaidOrder($userId)) {
            throw new InvalidArgumentException('Aguarde a confirmação do pagamento para criar sua senha.');
        }

        $stmt = $this->db->prepare(
            'UPDATE users SET
                password_hash = :p,
                password_is_set = 1,
                password_setup_token_hash = NULL,
                password_setup_expires = NULL,
                updated_at = :u
             WHERE id = :id'
        );
        $stmt->execute([
            ':p' => password_hash($password, PASSWORD_DEFAULT),
            ':u' => gmdate('c'),
            ':id' => $userId,
        ]);

        $this->startSession($userId);
        return $this->present($this->findById($userId) ?? []);
    }

    public function attempt(string $email, string $password): ?array
    {
        $user = $this->findByEmail(strtolower(trim($email)));
        if (!$user) {
            return null;
        }
        if (!(int) ($user['password_is_set'] ?? 1)) {
            return null;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }
        if ((int) ($user['email_verified'] ?? 1) !== 1) {
            try {
                $code = $this->issueCode((int) $user['id'], 'signup', (string) $user['email']);
                $this->sendCodeEmail(
                    (string) $user['email'],
                    'Confirme seu e-mail na GeekHost',
                    'Seu cadastro ainda não foi confirmado. Use o código abaixo para ativar o e-mail. Ele vale por 20 minutos.',
                    $code
                );
            } catch (InvalidArgumentException $e) {
                // Throttle: a code was sent less than a minute ago.
            }
            return ['unverified' => true, 'email' => (string) $user['email']];
        }
        $this->startSession((int) $user['id']);
        return $this->present($user);
    }

    public function logout(): void
    {
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (is_string($token) && $token !== '') {
            $hash = hash('sha256', $token);
            $stmt = $this->db->prepare('UPDATE user_sessions SET revoked_at = :r WHERE token_hash = :t AND revoked_at IS NULL');
            $stmt->execute([':r' => gmdate('c'), ':t' => $hash]);
        }
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_SESSION['panel_user_id']);
    }

    public function user(): ?array
    {
        return $this->userFromToken($this->bearerOrCookieToken(), true);
    }

    public function requireUser(): array
    {
        $user = $this->user();
        if (!$user) {
            header('Location: ' . (function_exists('url') ? url('/painel/login') : '/painel/login'));
            exit;
        }
        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    public function requireApiUser(): array
    {
        $token = $this->readBearerHeader();
        if ($token === null || $token === '') {
            $this->denyApi('Envie o cabeçalho Authorization: Bearer com o token da sessão.');
        }
        $user = $this->userFromToken($token, false);
        if (!$user) {
            $this->denyApi('Token inválido ou expirado. Entre de novo.');
        }
        return $user;
    }

    public function currentSessionId(): ?int
    {
        return $this->currentSessionId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sessionsFor(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, device_label, ip_address, created_at, expires_at, last_seen_at
             FROM user_sessions
             WHERE user_id = :u AND revoked_at IS NULL
             ORDER BY id DESC'
        );
        $stmt->execute([':u' => $userId]);
        $rows = $stmt->fetchAll() ?: [];
        $now = time();
        $out = [];
        foreach ($rows as $row) {
            if (strtotime((string) ($row['expires_at'] ?? '')) < $now) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'device' => (string) ($row['device_label'] ?: 'Dispositivo'),
                'ip' => (string) ($row['ip_address'] ?: '—'),
                'createdAt' => (string) ($row['created_at'] ?? ''),
                'expiresAt' => (string) ($row['expires_at'] ?? ''),
                'lastSeenAt' => (string) ($row['last_seen_at'] ?? ''),
                'current' => $this->currentSessionId === (int) $row['id'],
            ];
        }
        return $out;
    }

    public function revokeSession(int $userId, int $sessionId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT token_hash FROM user_sessions WHERE id = :id AND user_id = :u AND revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $sessionId, ':u' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $this->db->prepare('UPDATE user_sessions SET revoked_at = :r WHERE id = :id')
            ->execute([':r' => gmdate('c'), ':id' => $sessionId]);
        $current = $this->bearerOrCookieToken();
        $isCurrent = is_string($current) && $current !== '' && hash('sha256', $current) === (string) $row['token_hash'];
        if ($isCurrent) {
            $this->logout();
        }
        return $isCurrent;
    }

    public function changePassword(int $userId, string $current, string $newPassword): void
    {
        $user = $this->findById($userId);
        if (!$user || !(int) ($user['password_is_set'] ?? 1) || !password_verify($current, (string) $user['password_hash'])) {
            throw new InvalidArgumentException('Senha atual incorreta.');
        }
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('A nova senha deve ter pelo menos 8 caracteres.');
        }
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :p, updated_at = :u WHERE id = :id');
        $stmt->execute([
            ':p' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':u' => gmdate('c'),
            ':id' => $userId,
        ]);
    }

    /**
     * @param array<string, string> $profile
     */
    public function updateProfile(int $userId, array $profile, ?string $photoPath = null): array
    {
        $this->updateEncryptedProfile($userId, $profile);
        if ($photoPath !== null) {
            $stmt = $this->db->prepare('UPDATE users SET photo_path = :p, updated_at = :u WHERE id = :id');
            $stmt->execute([':p' => $photoPath, ':u' => gmdate('c'), ':id' => $userId]);
        }
        if (!empty($profile['email'])) {
            $newEmail = strtolower(trim((string) $profile['email']));
            $current = $this->findById($userId);
            $currentEmail = strtolower((string) ($current['email'] ?? ''));
            if ($newEmail !== $currentEmail) {
                throw new InvalidArgumentException('Confirme o novo e-mail com o código que enviamos.');
            }
        }
        return $this->present($this->findById($userId) ?? []);
    }

    public function startSession(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $now = gmdate('c');
        $expires = gmdate('c', time() + self::SESSION_HOURS * 3600);
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
        $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $stmt = $this->db->prepare(
            'INSERT INTO user_sessions (user_id, token_hash, expires_at, created_at, ip_address, user_agent, device_label, last_seen_at)
             VALUES (:u, :t, :e, :c, :ip, :ua, :d, :s)'
        );
        $stmt->execute([
            ':u' => $userId,
            ':t' => $hash,
            ':e' => $expires,
            ':c' => $now,
            ':ip' => $ip !== '' ? $ip : null,
            ':ua' => $agent !== '' ? $agent : null,
            ':d' => $this->deviceLabel($agent),
            ':s' => $now,
        ]);
        $this->currentSessionId = (int) $this->db->lastInsertId();
        setcookie(self::COOKIE, $token, [
            'expires' => time() + self::SESSION_HOURS * 3600,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_SESSION['panel_user_id'] = $userId;
        $this->lastIssuedToken = $token;
        return $token;
    }

    public function currentSessionToken(): ?string
    {
        return $this->bearerOrCookieToken() ?? $this->lastIssuedToken;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function present(array $user): array
    {
        return [
            'id' => (int) ($user['id'] ?? 0),
            'publicId' => $user['public_id'] ?? '',
            'email' => $user['email'] ?? '',
            'name' => geekhost_decrypt($user['name_enc'] ?? '') ?? '',
            'phone' => geekhost_decrypt($user['phone_enc'] ?? '') ?? '',
            'mobilePhone' => geekhost_decrypt($user['mobile_enc'] ?? '') ?? '',
            'cpfCnpj' => geekhost_decrypt($user['cpf_cnpj_enc'] ?? '') ?? '',
            'postalCode' => geekhost_decrypt($user['postal_code_enc'] ?? '') ?? '',
            'address' => geekhost_decrypt($user['address_enc'] ?? '') ?? '',
            'addressNumber' => geekhost_decrypt($user['address_number_enc'] ?? '') ?? '',
            'complement' => geekhost_decrypt($user['complement_enc'] ?? '') ?? '',
            'province' => geekhost_decrypt($user['province_enc'] ?? '') ?? '',
            'city' => geekhost_decrypt($user['city_enc'] ?? '') ?? '',
            'photoPath' => $user['photo_path'] ?? null,
            'hostingStatus' => $user['hosting_status'] ?? 'pending',
            'cardBrand' => $user['card_brand'] ?? null,
            'cardLast4' => geekhost_open(isset($user['card_last4']) ? (string) $user['card_last4'] : null),
            'suspendedAt' => $user['suspended_at'] ?? null,
            'suspendReason' => $user['suspend_reason'] ?? null,
            'passwordIsSet' => (bool) ((int) ($user['password_is_set'] ?? 1)),
        ];
    }

    private function readBearerHeader(): ?string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0) {
                        $header = (string) $value;
                        break;
                    }
                }
            }
        }
        if (preg_match('/^Bearer\s+(\S+)/i', $header, $m) === 1) {
            return $m[1];
        }
        return null;
    }

    private function bearerOrCookieToken(): ?string
    {
        $bearer = $this->readBearerHeader();
        if ($bearer !== null && $bearer !== '') {
            return $bearer;
        }
        $cookie = $_COOKIE[self::COOKIE] ?? '';
        if (is_string($cookie) && $cookie !== '') {
            return $cookie;
        }
        return null;
    }

    private function userFromToken(?string $token, bool $clearCookie): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }
        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'SELECT id, user_id, expires_at, created_at, last_seen_at
             FROM user_sessions
             WHERE token_hash = :t AND revoked_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':t' => $hash]);
        $session = $stmt->fetch();
        if (!$session) {
            return null;
        }
        $created = strtotime((string) ($session['created_at'] ?? ''));
        $expires = strtotime((string) ($session['expires_at'] ?? ''));
        $absolute = $created > 0 ? $created + (self::SESSION_HOURS * 3600) : 0;
        if ($expires < time() || ($absolute > 0 && $absolute < time())) {
            $this->db->prepare('UPDATE user_sessions SET revoked_at = :r WHERE id = :id')
                ->execute([':r' => gmdate('c'), ':id' => (int) $session['id']]);
            if ($clearCookie && (($_COOKIE[self::COOKIE] ?? '') === $token)) {
                $this->logout();
            }
            return null;
        }
        $user = $this->findById((int) $session['user_id']);
        if (!$user || !(int) ($user['password_is_set'] ?? 1)) {
            return null;
        }
        $this->currentSessionId = (int) $session['id'];
        $seen = strtotime((string) ($session['last_seen_at'] ?? ''));
        if ($seen < time() - 60) {
            $this->db->prepare('UPDATE user_sessions SET last_seen_at = :s WHERE id = :id')
                ->execute([':s' => gmdate('c'), ':id' => $this->currentSessionId]);
        }
        return $this->present($user);
    }

    private function denyApi(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function deviceLabel(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        $os = 'Dispositivo';
        if (str_contains($ua, 'windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) {
            $os = 'iPhone/iPad';
        } elseif (str_contains($ua, 'mac os')) {
            $os = 'Mac';
        } elseif (str_contains($ua, 'linux')) {
            $os = 'Linux';
        }
        $browser = 'Navegador';
        if (str_contains($ua, 'edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($ua, 'chrome/')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'safari/')) {
            $browser = 'Safari';
        }
        return $browser . ' · ' . $os;
    }

    /**
     * @param array<string, string> $profile
     */
    private function updateEncryptedProfile(int $userId, array $profile): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET
                name_enc = COALESCE(:name, name_enc),
                phone_enc = COALESCE(:phone, phone_enc),
                mobile_enc = COALESCE(:mobile, mobile_enc),
                cpf_cnpj_enc = COALESCE(:cpf, cpf_cnpj_enc),
                postal_code_enc = COALESCE(:zip, postal_code_enc),
                address_enc = COALESCE(:addr, address_enc),
                address_number_enc = COALESCE(:num, address_number_enc),
                complement_enc = COALESCE(:comp, complement_enc),
                province_enc = COALESCE(:uf, province_enc),
                city_enc = COALESCE(:city, city_enc),
                updated_at = :u
             WHERE id = :id'
        );
        $enc = static fn (?string $v) => $v === null || $v === '' ? null : geekhost_encrypt($v);
        $stmt->execute([
            ':name' => $enc($profile['name'] ?? null),
            ':phone' => $enc($profile['phone'] ?? null),
            ':mobile' => $enc($profile['mobile_phone'] ?? $profile['mobilePhone'] ?? null),
            ':cpf' => $enc($profile['cpf_cnpj'] ?? $profile['cpfCnpj'] ?? null),
            ':zip' => $enc($profile['postal_code'] ?? $profile['postalCode'] ?? null),
            ':addr' => $enc($profile['address'] ?? null),
            ':num' => $enc($profile['address_number'] ?? $profile['addressNumber'] ?? null),
            ':comp' => $enc($profile['complement'] ?? null),
            ':uf' => $enc($profile['province'] ?? null),
            ':city' => $enc($profile['city'] ?? null),
            ':u' => gmdate('c'),
            ':id' => $userId,
        ]);
    }

    /**
     * @param array<string, string> $profile
     */
    public function registerAccount(string $name, string $email, string $password, array $profile = []): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Informe nome e e-mail válidos.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }
        if ($this->findByEmail($email)) {
            throw new InvalidArgumentException('Já existe uma conta com este e-mail. Entre ou recupere a senha.');
        }

        $now = gmdate('c');
        $stmt = $this->db->prepare(
            'INSERT INTO users (
                public_id, customer_id, email, password_hash, password_is_set,
                name_enc, hosting_status, created_at, updated_at
            ) VALUES (
                :pid, NULL, :email, :ph, 1, :name, :hs, :c, :u
            )'
        );
        $stmt->execute([
            ':pid' => $this->uuid(),
            ':email' => $email,
            ':ph' => password_hash($password, PASSWORD_DEFAULT),
            ':name' => geekhost_encrypt($name) ?? '',
            ':hs' => 'pending',
            ':c' => $now,
            ':u' => $now,
        ]);
        $userId = (int) $this->db->lastInsertId();
        $this->saveSignupProfile($userId, $profile, $now);
        $this->db->prepare('UPDATE users SET email_verified = 0, updated_at = :u WHERE id = :id')
            ->execute([':u' => $now, ':id' => $userId]);
        $code = $this->issueCode($userId, 'signup', $email, null, true);
        $this->sendCodeEmail(
            $email,
            'Confirme seu cadastro na GeekHost',
            'Use este código para confirmar o e-mail e concluir o cadastro. Ele vale por 20 minutos.',
            $code
        );
        return ['email' => $email, 'needsConfirmation' => true];
    }

    /** @param array<string, string> $profile */
    private function saveSignupProfile(int $userId, array $profile, string $now): void
    {
        $map = [
            'phone' => 'phone_enc',
            'mobilePhone' => 'mobile_enc',
            'postalCode' => 'postal_code_enc',
            'address' => 'address_enc',
            'addressNumber' => 'address_number_enc',
            'complement' => 'complement_enc',
            'province' => 'province_enc',
            'city' => 'city_enc',
        ];
        $sets = [];
        $params = [':id' => $userId, ':u' => $now];
        foreach ($map as $key => $column) {
            $value = trim((string) ($profile[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $sets[] = $column . ' = :' . $key;
            $params[':' . $key] = geekhost_encrypt($value) ?? '';
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ', updated_at = :u WHERE id = :id';
        $this->db->prepare($sql)->execute($params);
    }

    public function requestPasswordReset(string $email, bool $adminOnly = false): void
    {
        $user = $this->findByEmail($email);
        if (!$user || !(int) ($user['password_is_set'] ?? 0)) {
            return;
        }
        if ($adminOnly && !$this->isActiveAdmin((int) $user['id'])) {
            return;
        }
        try {
            $code = $this->issueCode((int) $user['id'], 'reset', (string) $user['email']);
        } catch (InvalidArgumentException $e) {
            return;
        }
        $this->sendCodeEmail(
            (string) $user['email'],
            $adminOnly ? 'Código para redefinir a senha do admin' : 'Código para redefinir sua senha',
            'Use este código para criar uma nova senha. Ele vale por 20 minutos. Se não foi você, ignore este e-mail.',
            $code
        );
    }

    public function resetPasswordWithCode(string $email, string $code, string $password, string $confirm, bool $adminOnly = false, bool $login = true): void
    {
        if ($password !== $confirm) {
            throw new InvalidArgumentException('A confirmação de senha não confere.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }
        $user = $this->findByEmail($email);
        if (!$user || ($adminOnly && !$this->isActiveAdmin((int) $user['id']))) {
            throw new InvalidArgumentException('Código inválido ou expirado.');
        }
        $this->matchCode((int) $user['id'], 'reset', $code);
        $this->db->prepare(
            'UPDATE users SET password_hash = :p, password_is_set = 1, email_verified = 1, password_reset_token_hash = NULL, password_reset_expires = NULL, updated_at = :u WHERE id = :id'
        )->execute([
            ':p' => password_hash($password, PASSWORD_DEFAULT),
            ':u' => gmdate('c'),
            ':id' => (int) $user['id'],
        ]);
        $this->db->prepare('DELETE FROM auth_codes WHERE user_id = :u AND purpose = :p')
            ->execute([':u' => (int) $user['id'], ':p' => 'reset']);
        if ($login) {
            $this->startSession((int) $user['id']);
        }
    }

    public function resendEmailCode(string $email): void
    {
        $email = strtolower(trim($email));
        $user = $this->findByEmail($email);
        if ($user && (int) ($user['email_verified'] ?? 1) !== 1) {
            $code = $this->issueCode((int) $user['id'], 'signup', $email);
            $this->sendCodeEmail(
                $email,
                'Confirme seu cadastro na GeekHost',
                'Use este código para confirmar o e-mail e concluir o cadastro. Ele vale por 20 minutos.',
                $code
            );
            return;
        }
        $pending = $this->db->prepare(
            "SELECT user_id FROM auth_codes WHERE target_email = :e AND purpose = 'email_change' ORDER BY id DESC LIMIT 1"
        );
        $pending->execute([':e' => $email]);
        $userId = (int) ($pending->fetchColumn() ?: 0);
        if ($userId <= 0) {
            return;
        }
        $code = $this->issueCode($userId, 'email_change', $email);
        $this->sendCodeEmail(
            $email,
            'Confirme seu novo e-mail na GeekHost',
            'Use este código para confirmar a troca de e-mail. Ele vale por 20 minutos.',
            $code
        );
    }

    public function confirmEmailCode(string $email, string $code): string
    {
        $email = strtolower(trim($email));
        $digits = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($digits) !== 6) {
            throw new InvalidArgumentException('Informe o código de 6 dígitos enviado por e-mail.');
        }
        $hash = hash('sha256', $digits);
        $stmt = $this->db->prepare(
            "SELECT * FROM auth_codes
             WHERE target_email = :e AND purpose IN ('signup', 'email_change') AND expires_at >= :now
             ORDER BY id DESC LIMIT 5"
        );
        $stmt->execute([':e' => $email, ':now' => gmdate('c')]);
        $match = null;
        foreach ($stmt->fetchAll() ?: [] as $row) {
            if (hash_equals((string) $row['code_hash'], $hash)) {
                $match = $row;
                break;
            }
        }
        if (!$match) {
            throw new InvalidArgumentException('Código inválido ou expirado.');
        }
        $userId = (int) $match['user_id'];
        $purpose = (string) $match['purpose'];
        if ($purpose === 'email_change') {
            $taken = $this->findByEmail($email);
            if ($taken && (int) $taken['id'] !== $userId) {
                throw new InvalidArgumentException('Este e-mail já está em outra conta.');
            }
            $this->db->prepare('UPDATE users SET email = :e, email_verified = 1, updated_at = :u WHERE id = :id')
                ->execute([':e' => $email, ':u' => gmdate('c'), ':id' => $userId]);
        } else {
            $this->db->prepare('UPDATE users SET email_verified = 1, updated_at = :u WHERE id = :id')
                ->execute([':u' => gmdate('c'), ':id' => $userId]);
        }
        $this->db->prepare('DELETE FROM auth_codes WHERE user_id = :u AND purpose = :p')
            ->execute([':u' => $userId, ':p' => $purpose]);
        $this->startSession($userId);
        return $purpose;
    }

    public function requestEmailChange(int $userId, string $newEmail): void
    {
        $newEmail = strtolower(trim($newEmail));
        if (filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }
        $current = $this->findById($userId);
        if (!$current) {
            throw new InvalidArgumentException('Conta não encontrada.');
        }
        if ($newEmail === strtolower((string) $current['email'])) {
            return;
        }
        $taken = $this->findByEmail($newEmail);
        if ($taken && (int) $taken['id'] !== $userId) {
            throw new InvalidArgumentException('Já existe uma conta com este e-mail.');
        }
        $code = $this->issueCode($userId, 'email_change', $newEmail);
        $this->sendCodeEmail(
            $newEmail,
            'Confirme seu novo e-mail na GeekHost',
            'Use este código para confirmar a troca de e-mail da sua conta. Ele vale por 20 minutos.',
            $code
        );
    }

    public function requestPasswordChangeCode(int $userId, string $current): void
    {
        $user = $this->findById($userId);
        if (!$user || !(int) ($user['password_is_set'] ?? 1) || !password_verify($current, (string) $user['password_hash'])) {
            throw new InvalidArgumentException('Senha atual incorreta.');
        }
        $code = $this->issueCode($userId, 'password_change', (string) $user['email']);
        $this->sendCodeEmail(
            (string) $user['email'],
            'Código para alterar sua senha',
            'Use este código para confirmar a nova senha da sua conta GeekHost. Ele vale por 20 minutos.',
            $code
        );
    }

    public function confirmPasswordChange(int $userId, string $current, string $newPassword, string $code): void
    {
        $user = $this->findById($userId);
        if (!$user || !password_verify($current, (string) $user['password_hash'])) {
            throw new InvalidArgumentException('Senha atual incorreta.');
        }
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('A nova senha deve ter pelo menos 8 caracteres.');
        }
        $this->matchCode($userId, 'password_change', $code);
        $this->db->prepare('UPDATE users SET password_hash = :p, password_is_set = 1, updated_at = :u WHERE id = :id')
            ->execute([
                ':p' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':u' => gmdate('c'),
                ':id' => $userId,
            ]);
        $this->db->prepare('DELETE FROM auth_codes WHERE user_id = :u AND purpose = :p')
            ->execute([':u' => $userId, ':p' => 'password_change']);
    }

    public function resetPassword(string $token, string $password, string $confirm): array
    {
        if ($password !== $confirm) {
            throw new InvalidArgumentException('A confirmação de senha não confere.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }
        $token = trim($token);
        if (strlen($token) < 32) {
            throw new InvalidArgumentException('Link inválido ou expirado.');
        }
        $stmt = $this->db->prepare('SELECT * FROM users WHERE password_reset_token_hash = :h LIMIT 1');
        $stmt->execute([':h' => hash('sha256', $token)]);
        $user = $stmt->fetch();
        $expires = $user ? strtotime((string) ($user['password_reset_expires'] ?? '')) : false;
        if (!$user || $expires === false || $expires < time()) {
            throw new InvalidArgumentException('Link inválido ou expirado.');
        }
        $this->db->prepare(
            'UPDATE users SET password_hash = :p, password_is_set = 1, password_reset_token_hash = NULL, password_reset_expires = NULL, updated_at = :u WHERE id = :id'
        )->execute([
            ':p' => password_hash($password, PASSWORD_DEFAULT),
            ':u' => gmdate('c'),
            ':id' => (int) $user['id'],
        ]);
        $this->startSession((int) $user['id']);
        return $this->present($this->findById((int) $user['id']) ?? []);
    }

    private function ensureCodes(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $int = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS auth_codes (
                id {$id},
                user_id {$int} NOT NULL,
                purpose VARCHAR(40) NOT NULL,
                target_email VARCHAR(190) NOT NULL,
                code_hash VARCHAR(64) NOT NULL,
                payload TEXT DEFAULT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            )"
        );
        try {
            $this->db->exec('CREATE INDEX idx_auth_codes_user ON auth_codes (user_id, purpose)');
        } catch (Throwable $e) {
            // exists
        }
    }

    private function issueCode(int $userId, string $purpose, string $targetEmail, ?string $payload = null, bool $force = false): string
    {
        $targetEmail = strtolower(trim($targetEmail));
        $recent = $this->db->prepare(
            'SELECT created_at FROM auth_codes WHERE user_id = :u AND purpose = :p ORDER BY id DESC LIMIT 1'
        );
        $recent->execute([':u' => $userId, ':p' => $purpose]);
        $last = $recent->fetchColumn();
        if (!$force && is_string($last)) {
            $sentAt = strtotime($last);
            if ($sentAt !== false && time() - $sentAt < 60) {
                throw new InvalidArgumentException('Aguarde um minuto para pedir outro código.');
            }
        }
        $this->db->prepare('DELETE FROM auth_codes WHERE user_id = :u AND purpose = :p')
            ->execute([':u' => $userId, ':p' => $purpose]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->db->prepare(
            'INSERT INTO auth_codes (user_id, purpose, target_email, code_hash, payload, expires_at, created_at)
             VALUES (:u, :p, :e, :h, :payload, :x, :c)'
        )->execute([
            ':u' => $userId,
            ':p' => $purpose,
            ':e' => $targetEmail,
            ':h' => hash('sha256', $code),
            ':payload' => $payload,
            ':x' => gmdate('c', time() + 20 * 60),
            ':c' => gmdate('c'),
        ]);
        return $code;
    }

    /** @return array<string, mixed> */
    private function matchCode(int $userId, string $purpose, string $code): array
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($digits) !== 6) {
            throw new InvalidArgumentException('Informe o código de 6 dígitos enviado por e-mail.');
        }
        $hash = hash('sha256', $digits);
        $stmt = $this->db->prepare(
            'SELECT * FROM auth_codes WHERE user_id = :u AND purpose = :p AND expires_at >= :now ORDER BY id DESC LIMIT 3'
        );
        $stmt->execute([':u' => $userId, ':p' => $purpose, ':now' => gmdate('c')]);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            if (hash_equals((string) $row['code_hash'], $hash)) {
                return $row;
            }
        }
        throw new InvalidArgumentException('Código inválido ou expirado.');
    }

    private function sendCodeEmail(string $to, string $subject, string $intro, string $code): void
    {
        require_once __DIR__ . '/Mailer.php';
        $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html><html lang="pt-BR"><body style="margin:0;background:#f3f4f5;font-family:Arial,sans-serif;color:#1a1e24;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f5;padding:28px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" style="max-width:560px;background:#fff;border-radius:18px;overflow:hidden;">'
            . '<tr><td style="background:#1a1e24;padding:28px 32px;color:#fff;font-size:22px;font-weight:800;">GeekHost<span style="color:#01e152;">.</span></td></tr>'
            . '<tr><td style="padding:32px;">'
            . '<p style="margin:0 0 18px;font-size:15px;line-height:1.6;">' . $safeIntro . '</p>'
            . '<div style="font-size:32px;font-weight:800;letter-spacing:8px;background:#f6f7f8;border-radius:12px;padding:16px;text-align:center;">' . $safeCode . '</div>'
            . '<p style="margin:18px 0 0;font-size:12px;color:#697078;">O login continua só com e-mail e senha. Este código não substitui a senha.</p>'
            . '</td></tr></table></td></tr></table></body></html>';
        Mailer::send($to, $subject, $html);
    }

    private function isActiveAdmin(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM admin_users WHERE user_id = :u AND status = 'active' LIMIT 1");
            $stmt->execute([':u' => $userId]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
