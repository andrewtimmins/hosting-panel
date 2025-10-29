<?php
namespace App\Services;

use App\Database\Connection;
use PDO;
use RuntimeException;
use InvalidArgumentException;

class AuthService
{
    private PDO $db;

    public function __construct(array $config)
    {
        $this->db = Connection::get($config);
    }

    public function login(string $username, string $password): array
    {
        $stmt = $this->db->prepare('
            SELECT id, username, email, password_hash, full_name, role, is_active 
            FROM users 
            WHERE (username = ? OR email = ?) AND is_active = 1
        ');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new InvalidArgumentException('Invalid username or password');
        }

        // Update last login
        $this->updateLastLogin($user['id']);

        // Create session
        $sessionId = $this->createSession($user['id']);

        return [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ],
            'session_id' => $sessionId
        ];
    }

    public function logout(string $sessionId): void
    {
        $stmt = $this->db->prepare('DELETE FROM user_sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
    }

    public function validateSession(string $sessionId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT u.id, u.username, u.email, u.full_name, u.role, s.expires_at
            FROM user_sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ? AND s.expires_at > NOW() AND u.is_active = 1
        ');
        $stmt->execute([$sessionId]);
        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        return [
            'id' => $result['id'],
            'username' => $result['username'],
            'email' => $result['email'],
            'full_name' => $result['full_name'],
            'role' => $result['role']
        ];
    }

    public function getCurrentUser(): ?array
    {
        $sessionId = $_COOKIE['admin_session'] ?? null;
        if (!$sessionId) {
            return null;
        }

        return $this->validateSession($sessionId);
    }

    private function createSession(int $userId): string
    {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days

        $stmt = $this->db->prepare('
            INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $sessionId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiresAt
        ]);

        // Set cookie
        setcookie('admin_session', $sessionId, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']),
            'samesite' => 'Strict'
        ]);

        return $sessionId;
    }

    private function updateLastLogin(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    }

    public function cleanupExpiredSessions(): void
    {
        $stmt = $this->db->prepare('DELETE FROM user_sessions WHERE expires_at < NOW()');
        $stmt->execute();
    }
}