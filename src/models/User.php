<?php

namespace UtiCensor\Models;

use UtiCensor\Utils\Database;

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);
        
        return $this->db->insert('users', $data);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE username = ?', [$username]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public function authenticate(string $username, string $password): ?array
    {
        $user = $this->findByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Update last login
        $this->db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        unset($user['password_hash']);
        return $user;
    }

    public function getAll(int $offset = 0, int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT id, username, email, role, is_active, created_at, last_login_at 
             FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        return $this->db->update('users', $data, ['id' => $id]) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('users', ['id' => $id]) > 0;
    }

    public function count(): int
    {
        $result = $this->db->fetchOne('SELECT COUNT(*) as count FROM users');
        return (int) $result['count'];
    }

    public function isRegistrationEnabled(): bool
    {
        $setting = $this->db->fetchOne(
            'SELECT setting_value FROM system_settings WHERE setting_key = ?',
            ['user_registration_enabled']
        );
        
        return $setting && $setting['setting_value'] === '1';
    }
}

