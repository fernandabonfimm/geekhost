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

    public function __construct()
    {
        $this->db = geekhost_db();
        geekhost_boot_session();
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
        $token = $this->bearerOrCookieToken();
        if ($token === null) {
            return null;
        }
        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'SELECT u.*, s.expires_at AS session_expires
             FROM user_sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = :t AND s.revoked_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':t' => $hash]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (strtotime((string) $row['session_expires']) < time()) {
            $this->logout();
            return null;
        }
        if (!(int) ($row['password_is_set'] ?? 1)) {
            return null;
        }
        return $this->present($row);
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
        $user = $this->user();
        if (!$user) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Não autenticado. Faça login no painel.']);
            exit;
        }
        return $user;
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
            $stmt = $this->db->prepare('UPDATE users SET email = :e, updated_at = :u WHERE id = :id');
            $stmt->execute([':e' => strtolower(trim($profile['email'])), ':u' => gmdate('c'), ':id' => $userId]);
        }
        return $this->present($this->findById($userId) ?? []);
    }

    public function startSession(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = gmdate('c', time() + self::SESSION_HOURS * 3600);
        $stmt = $this->db->prepare(
            'INSERT INTO user_sessions (user_id, token_hash, expires_at, created_at) VALUES (:u, :t, :e, :c)'
        );
        $stmt->execute([
            ':u' => $userId,
            ':t' => $hash,
            ':e' => $expires,
            ':c' => gmdate('c'),
        ]);
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
            'cardLast4' => $user['card_last4'] ?? null,
            'suspendedAt' => $user['suspended_at'] ?? null,
            'suspendReason' => $user['suspend_reason'] ?? null,
            'passwordIsSet' => (bool) ((int) ($user['password_is_set'] ?? 1)),
        ];
    }

    private function bearerOrCookieToken(): ?string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Bearer\s+(\S+)/i', $header, $m) === 1) {
            return $m[1];
        }
        $cookie = $_COOKIE[self::COOKIE] ?? '';
        if (is_string($cookie) && $cookie !== '') {
            return $cookie;
        }
        return null;
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

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
