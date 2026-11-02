<?php

declare(strict_types=1);

namespace App\Identity;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Session;
use PDO;
use RuntimeException;

final class GoogleIdentityService
{
    private const SESSION_KEY = 'google_oauth';
    private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const CERTIFICATES_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/certs';

    public function __construct(private PDO $pdo)
    {
    }

    public static function isConfigured(): bool
    {
        return (string) Config::get('google.client_id') !== ''
            && (string) Config::get('google.client_secret') !== ''
            && (string) Config::get('app.url') !== ''
            && function_exists('openssl_verify');
    }

    public function start(string $mode, int $userId = 0): never
    {
        Session::start();
        if (!self::isConfigured()) throw new RuntimeException('Google sign-in is not configured for this installation.');
        if (!in_array($mode, ['login', 'register', 'link'], true)) throw new RuntimeException('The Google sign-in request is invalid.');
        if ($mode === 'link') {
            $user = Auth::user($this->pdo);
            if (!$user || (int) $user['id'] !== $userId) throw new RuntimeException('Sign in before connecting a Google account.');
        }
        $state = self::randomValue(32);
        $nonce = self::randomValue(32);
        $verifier = self::randomValue(64);
        $_SESSION[self::SESSION_KEY] = [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'mode' => $mode,
            'user_id' => $userId,
            'created_at' => time(),
        ];
        $query = http_build_query([
            'client_id' => (string) Config::get('google.client_id'),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::base64UrlEncode(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
        header('Location: ' . self::AUTHORIZATION_ENDPOINT . '?' . $query, true, 303);
        exit;
    }

    public function complete(string $code, string $state): array
    {
        Session::start();
        $pending = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);
        if (!is_array($pending)
            || !isset($pending['state'], $pending['nonce'], $pending['verifier'], $pending['mode'], $pending['created_at'])
            || !is_string($pending['state'])
            || !is_string($pending['nonce'])
            || !is_string($pending['verifier'])
            || !is_string($pending['mode'])
            || !is_int($pending['created_at'])
            || time() - $pending['created_at'] > 600
            || time() < $pending['created_at']
            || $state === ''
            || !hash_equals($pending['state'], $state)
            || $code === ''
            || strlen($code) > 4096) {
            throw new RuntimeException('The Google sign-in session expired or could not be verified. Please try again.');
        }
        $tokens = $this->exchangeCode($code, $pending['verifier']);
        $claims = $this->verifyIdToken((string) ($tokens['id_token'] ?? ''), $pending['nonce']);
        $userId = $this->resolveAccount($claims, $pending['mode'], (int) ($pending['user_id'] ?? 0));
        return ['user_id' => $userId, 'mode' => $pending['mode']];
    }

    private function exchangeCode(string $code, string $verifier): array
    {
        $response = self::requestJson(self::TOKEN_ENDPOINT, 'POST', [
            'code' => $code,
            'client_id' => (string) Config::get('google.client_id'),
            'client_secret' => (string) Config::get('google.client_secret'),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]);
        if (!is_string($response['id_token'] ?? null) || $response['id_token'] === '') {
            throw new RuntimeException('Google did not return a verifiable identity token.');
        }
        return $response;
    }

    private function verifyIdToken(string $token, string $nonce): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new RuntimeException('Google returned an invalid identity token.');
        $header = self::decodeJsonPart($parts[0]);
        $claims = self::decodeJsonPart($parts[1]);
        $signature = self::base64UrlDecode($parts[2]);
        if (($header['alg'] ?? '') !== 'RS256' || !is_string($header['kid'] ?? null) || $header['kid'] === '' || $signature === '') {
            throw new RuntimeException('Google returned an unsupported identity token.');
        }
        $keys = self::requestJson(self::CERTIFICATES_ENDPOINT, 'GET');
        $key = null;
        foreach (($keys['keys'] ?? []) as $candidate) {
            if (is_array($candidate) && ($candidate['kid'] ?? null) === $header['kid'] && ($candidate['kty'] ?? null) === 'RSA' && ($candidate['alg'] ?? 'RS256') === 'RS256') {
                $key = $candidate;
                break;
            }
        }
        if (!is_array($key) || !is_string($key['n'] ?? null) || !is_string($key['e'] ?? null)) {
            throw new RuntimeException('Google signing keys could not verify this identity token.');
        }
        $publicKey = self::rsaPublicKey($key['n'], $key['e']);
        $verified = openssl_verify($parts[0] . '.' . $parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) throw new RuntimeException('Google identity token signature verification failed.');

        $audience = $claims['aud'] ?? null;
        $clientId = (string) Config::get('google.client_id');
        $audienceMatches = is_string($audience) ? hash_equals($clientId, $audience) : (is_array($audience) && in_array($clientId, $audience, true));
        $issuer = $claims['iss'] ?? '';
        $issuedAt = filter_var($claims['iat'] ?? null, FILTER_VALIDATE_INT);
        $expiresAt = filter_var($claims['exp'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true)
            || !$audienceMatches
            || (is_array($audience) && (!is_string($claims['azp'] ?? null) || !hash_equals($clientId, $claims['azp'])))
            || !$issuedAt
            || !$expiresAt
            || $issuedAt > time() + 60
            || $expiresAt <= time()
            || $expiresAt - $issuedAt > 7200
            || !is_string($claims['nonce'] ?? null)
            || !hash_equals($nonce, $claims['nonce'])
            || !is_string($claims['sub'] ?? null)
            || $claims['sub'] === ''
            || !is_string($claims['email'] ?? null)
            || !filter_var($claims['email'], FILTER_VALIDATE_EMAIL)
            || !in_array($claims['email_verified'] ?? false, [true, 'true', 1, '1'], true)) {
            throw new RuntimeException('Google could not verify the account details required for sign-in.');
        }
        return $claims;
    }

    private function resolveAccount(array $claims, string $mode, int $linkUserId): int
    {
        $subject = (string) $claims['sub'];
        $email = strtolower(trim((string) $claims['email']));
        $name = trim((string) ($claims['name'] ?? ''));
        if ($name === '') $name = strstr($email, '@', true) ?: 'Google user';
        $picture = is_string($claims['picture'] ?? null) && str_starts_with($claims['picture'], 'https://') ? $claims['picture'] : null;
        $now = gmdate('Y-m-d H:i:s');

        if ($mode === 'link') {
            $user = Auth::user($this->pdo);
            if (!$user || (int) $user['id'] !== $linkUserId) throw new RuntimeException('Your account session expired. Sign in and try connecting Google again.');
            $this->pdo->beginTransaction();
            try {
                $existing = $this->pdo->prepare('SELECT id FROM users WHERE google_subject = :subject LIMIT 1');
                $existing->execute(['subject' => $subject]);
                $owner = $existing->fetchColumn();
                if ($owner !== false && (int) $owner !== $linkUserId) throw new RuntimeException('This Google account is already connected to another SmartStudyPro account.');
                $update = $this->pdo->prepare('UPDATE users SET google_subject = :subject, avatar_url = COALESCE(:avatar, avatar_url), email_verified_at = COALESCE(email_verified_at, :verified_at), updated_at = :updated_at WHERE id = :id AND status = :status');
                $update->execute(['subject' => $subject, 'avatar' => $picture, 'verified_at' => $now, 'updated_at' => $now, 'id' => $linkUserId, 'status' => 'active']);
                if ($update->rowCount() !== 1) throw new RuntimeException('The Google account could not be connected.');
                $this->pdo->commit();
                return $linkUserId;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                if ($exception instanceof \PDOException && str_contains(strtolower($exception->getMessage()), 'unique')) throw new RuntimeException('This Google account is already connected to another SmartStudyPro account.');
                throw $exception;
            }
        }

        $bySubject = $this->pdo->prepare('SELECT id, status FROM users WHERE google_subject = :subject LIMIT 1');
        $bySubject->execute(['subject' => $subject]);
        $linked = $bySubject->fetch();
        if ($linked) {
            if ($linked['status'] !== 'active') throw new RuntimeException('This SmartStudyPro account is not currently active.');
            return (int) $linked['id'];
        }

        $byEmail = $this->pdo->prepare('SELECT id FROM users WHERE email = :email COLLATE NOCASE LIMIT 1');
        $byEmail->execute(['email' => $email]);
        if ($byEmail->fetchColumn() !== false) {
            throw new RuntimeException('An account already uses this email. Sign in with your password first, then connect Google from your profile.');
        }
        if ($mode !== 'register') throw new RuntimeException('No SmartStudyPro account is connected to this Google identity. Create an account first.');

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare("INSERT INTO users (name, email, password_hash, google_subject, avatar_url, status, email_verified_at, created_at, updated_at) VALUES (:name, :email, NULL, :subject, :avatar, 'active', :verified_at, :created_at, :updated_at)");
            $insert->execute(['name' => substr($name, 0, 120), 'email' => $email, 'subject' => $subject, 'avatar' => $picture, 'verified_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $userId = (int) $this->pdo->lastInsertId();
            $role = $this->pdo->prepare("INSERT INTO user_roles (user_id, role_id, assigned_at) SELECT :user_id, id, :assigned_at FROM roles WHERE slug = 'student'");
            $role->execute(['user_id' => $userId, 'assigned_at' => $now]);
            if ($role->rowCount() !== 1) throw new RuntimeException('The student role is not available.');
            $profile = $this->pdo->prepare('INSERT INTO student_profiles (user_id, created_at, updated_at) VALUES (:user_id, :created_at, :updated_at)');
            $profile->execute(['user_id' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            $acceptance = $this->pdo->prepare('INSERT INTO user_legal_acceptances (user_id, document, version, accepted_at, ip_hash, user_agent_hash) VALUES (:user_id, :document, :version, :accepted_at, :ip_hash, :user_agent_hash)');
            foreach (LegalAcceptanceService::VERSIONS as $document => $version) {
                $acceptance->execute(['user_id' => $userId, 'document' => $document, 'version' => $version, 'accepted_at' => $now, 'ip_hash' => empty($_SERVER['REMOTE_ADDR']) ? null : hash('sha256', (string) $_SERVER['REMOTE_ADDR']), 'user_agent_hash' => empty($_SERVER['HTTP_USER_AGENT']) ? null : hash('sha256', (string) $_SERVER['HTTP_USER_AGENT'])]);
            }
            $this->pdo->commit();
            return $userId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($exception instanceof \PDOException && str_contains(strtolower($exception->getMessage()), 'unique')) throw new RuntimeException('An account already uses this Google identity or email. Sign in and connect Google from your profile.');
            throw $exception;
        }
    }

    private static function redirectUri(): string
    {
        return rtrim((string) Config::get('app.url'), '/') . '/google-auth.php?action=callback';
    }

    private static function requestJson(string $url, string $method, array $data = []): array
    {
        $headers = ['Accept: application/json'];
        $content = null;
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $content = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
        }
        $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $content ?? '', 'timeout' => 10, 'ignore_errors' => true], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('/^HTTP\\/\\S+\\s+(\\d{3})/', $line, $matches)) $status = (int) $matches[1];
        }
        if (!is_string($body) || $status < 200 || $status >= 300) throw new RuntimeException('Google sign-in could not be completed. Check the server connection and try again.');
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) throw new RuntimeException('Google returned an unreadable sign-in response.');
        return $decoded;
    }

    private static function decodeJsonPart(string $part): array
    {
        $decoded = json_decode(self::base64UrlDecode($part), true);
        if (!is_array($decoded)) throw new RuntimeException('Google returned a malformed identity token.');
        return $decoded;
    }

    private static function rsaPublicKey(string $modulus, string $exponent): string
    {
        $integer = static function (string $value): string {
            $bytes = self::base64UrlDecode($value);
            $bytes = ltrim($bytes, "\0");
            if ($bytes === '') $bytes = "\0";
            if ((ord($bytes[0]) & 0x80) !== 0) $bytes = "\0" . $bytes;
            return "\x02" . self::derLength(strlen($bytes)) . $bytes;
        };
        $rsa = $integer($modulus) . $integer($exponent);
        $rsa = "\x30" . self::derLength(strlen($rsa)) . $rsa;
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $bitString = "\x03" . self::derLength(strlen($rsa) + 1) . "\0" . $rsa;
        $sequence = $algorithm . $bitString;
        $der = "\x30" . self::derLength(strlen($sequence)) . $sequence;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derLength(int $length): string
    {
        if ($length < 128) return chr($length);
        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xff) . $encoded;
            $length >>= 8;
        }
        return chr(0x80 | strlen($encoded)) . $encoded;
    }

    private static function randomValue(int $bytes): string
    {
        return self::base64UrlEncode(random_bytes($bytes));
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value)) return '';
        $decoded = base64_decode(strtr($value . str_repeat('=', (4 - strlen($value) % 4) % 4), '-_', '+/'), true);
        return is_string($decoded) ? $decoded : '';
    }
}
