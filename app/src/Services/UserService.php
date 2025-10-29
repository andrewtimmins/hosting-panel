<?php
namespace App\Services;

use App\Database\Connection;
use PDO;
use RuntimeException;
use InvalidArgumentException;

class UserService
{
    private PDO $db;

    public function __construct(array $config)
    {
        $this->db = Connection::get($config);
    }

    public function listUsers(): array
    {
        $stmt = $this->db->prepare('
            SELECT id, username, email, full_name, role, is_active, last_login, created_at 
            FROM users 
            ORDER BY created_at DESC
        ');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createUser(array $data): array
    {
        $this->validateUserData($data);

        if ($this->userExists($data['username'], $data['email'])) {
            throw new InvalidArgumentException('Username or email already exists');
        }

        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $this->db->prepare('
            INSERT INTO users (username, email, password_hash, full_name, role, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $data['username'],
            $data['email'],
            $passwordHash,
            $data['full_name'] ?? null,
            $data['role'] ?? 'user',
            $data['is_active'] ?? true
        ]);

        $userId = $this->db->lastInsertId();

        return $this->getUser($userId);
    }

    public function updateUser(int $userId, array $data): array
    {
        $user = $this->getUser($userId);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }

        $updates = [];
        $params = [];

        if (isset($data['username'])) {
            if ($this->userExists($data['username'], null, $userId)) {
                throw new InvalidArgumentException('Username already exists');
            }
            $updates[] = 'username = ?';
            $params[] = $data['username'];
        }

        if (isset($data['email'])) {
            if ($this->userExists(null, $data['email'], $userId)) {
                throw new InvalidArgumentException('Email already exists');
            }
            $updates[] = 'email = ?';
            $params[] = $data['email'];
        }

        if (isset($data['full_name'])) {
            $updates[] = 'full_name = ?';
            $params[] = $data['full_name'];
        }

        if (isset($data['role'])) {
            $updates[] = 'role = ?';
            $params[] = $data['role'];
        }

        if (isset($data['is_active'])) {
            $updates[] = 'is_active = ?';
            $params[] = $data['is_active'];
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $updates[] = 'password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($updates)) {
            return $user;
        }

        $params[] = $userId;
        
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->getUser($userId);
    }

    public function deleteUser(int $userId): void
    {
        // Don't allow deleting the last admin user
        $adminCount = $this->countAdmins();
        $user = $this->getUser($userId);
        
        if ($user && $user['role'] === 'admin' && $adminCount <= 1) {
            throw new RuntimeException('Cannot delete the last admin user');
        }

        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);

        if ($stmt->rowCount() === 0) {
            throw new InvalidArgumentException('User not found');
        }
    }

    public function getUser(int $userId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, username, email, full_name, role, is_active, last_login, created_at, updated_at 
            FROM users 
            WHERE id = ?
        ');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            throw new InvalidArgumentException('Current password is incorrect');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $userId]);
    }

    private function validateUserData(array $data): void
    {
        if (empty($data['username']) || strlen($data['username']) < 3) {
            throw new InvalidArgumentException('Username must be at least 3 characters long');
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Valid email is required');
        }

        if (empty($data['password']) || strlen($data['password']) < 6) {
            throw new InvalidArgumentException('Password must be at least 6 characters long');
        }

        if (isset($data['role']) && !in_array($data['role'], ['admin', 'user'])) {
            throw new InvalidArgumentException('Role must be admin or user');
        }
    }

    private function userExists(string $username = null, string $email = null, int $excludeUserId = null): bool
    {
        $conditions = [];
        $params = [];

        if ($username !== null) {
            $conditions[] = 'username = ?';
            $params[] = $username;
        }

        if ($email !== null) {
            $conditions[] = 'email = ?';
            $params[] = $email;
        }

        if ($excludeUserId !== null) {
            $conditions[] = 'id != ?';
            $params[] = $excludeUserId;
        }

        if (empty($conditions)) {
            return false;
        }

        $sql = 'SELECT COUNT(*) FROM users WHERE ' . implode(' OR ', array_slice($conditions, 0, 2));
        if ($excludeUserId !== null) {
            $sql .= ' AND ' . end($conditions);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }

    private function countAdmins(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE role = ? AND is_active = 1');
        $stmt->execute(['admin']);
        return (int) $stmt->fetchColumn();
    }
}