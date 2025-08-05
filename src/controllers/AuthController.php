<?php

namespace UtiCensor\Controllers;

use UtiCensor\Models\User;
use UtiCensor\Utils\JWT;
use UtiCensor\Utils\Logger;

class AuthController
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['username']) || empty($input['password'])) {
            Logger::warning("登录失败: 缺少用户名或密码", 'auth', ['username' => $input['username'] ?? 'missing']);
            $this->jsonResponse(['error' => 'Username and password are required'], 400);
            return;
        }

        $user = $this->userModel->authenticate($input['username'], $input['password']);
        
        if (!$user) {
            Logger::warning("登录失败: 无效凭据", 'auth', ['username' => $input['username']]);
            $this->jsonResponse(['error' => 'Invalid credentials'], 401);
            return;
        }

        if (!$user['is_active']) {
            Logger::warning("登录失败: 账户被禁用", 'auth', ['user_id' => $user['id'], 'username' => $user['username']]);
            $this->jsonResponse(['error' => 'Account is disabled'], 403);
            return;
        }

        $token = JWT::encode([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);

        Logger::info("用户登录成功", 'auth', [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);

        $this->jsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
    }

    public function register(): void
    {
        if (!$this->userModel->isRegistrationEnabled()) {
            Logger::warning("用户注册失败: 注册功能已禁用", 'auth');
            $this->jsonResponse(['error' => 'User registration is disabled'], 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['username']) || empty($input['email']) || empty($input['password'])) {
            Logger::warning("用户注册失败: 缺少必要字段", 'auth', ['username' => $input['username'] ?? 'missing']);
            $this->jsonResponse(['error' => 'Username, email and password are required'], 400);
            return;
        }

        // Validate input
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            Logger::warning("用户注册失败: 邮箱格式无效", 'auth', ['email' => $input['email']]);
            $this->jsonResponse(['error' => 'Invalid email format'], 400);
            return;
        }

        if (strlen($input['password']) < 6) {
            Logger::warning("用户注册失败: 密码长度不足", 'auth', ['username' => $input['username']]);
            $this->jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
            return;
        }

        // Check if user already exists
        if ($this->userModel->findByUsername($input['username'])) {
            Logger::warning("用户注册失败: 用户名已存在", 'auth', ['username' => $input['username']]);
            $this->jsonResponse(['error' => 'Username already exists'], 409);
            return;
        }

        if ($this->userModel->findByEmail($input['email'])) {
            Logger::warning("用户注册失败: 邮箱已存在", 'auth', ['email' => $input['email']]);
            $this->jsonResponse(['error' => 'Email already exists'], 409);
            return;
        }

        try {
            $userId = $this->userModel->create([
                'username' => $input['username'],
                'email' => $input['email'],
                'password' => $input['password'],
                'role' => 'user'
            ]);

            Logger::info("用户注册成功", 'auth', [
                'user_id' => $userId,
                'username' => $input['username'],
                'email' => $input['email']
            ]);

            $this->jsonResponse([
                'message' => 'User registered successfully',
                'user_id' => $userId
            ], 201);
        } catch (\Throwable $e) {
            Logger::error("用户注册失败: 数据库错误", 'auth', [
                'username' => $input['username'],
                'email' => $input['email'],
                'error' => $e->getMessage()
            ]);
            $this->jsonResponse(['error' => 'Registration failed'], 500);
        }
    }

    public function me(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $this->jsonResponse([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
                'created_at' => $user['created_at'],
                'last_login_at' => $user['last_login_at']
            ]
        ]);
    }

    public function changePassword(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['current_password']) || empty($input['new_password'])) {
            $this->jsonResponse(['error' => 'Current password and new password are required'], 400);
            return;
        }

        // Verify current password
        $userWithPassword = $this->userModel->findById($user['id']);
        if (!password_verify($input['current_password'], $userWithPassword['password_hash'])) {
            $this->jsonResponse(['error' => 'Current password is incorrect'], 400);
            return;
        }

        if (strlen($input['new_password']) < 6) {
            $this->jsonResponse(['error' => 'New password must be at least 6 characters'], 400);
            return;
        }

        try {
            $this->userModel->update($user['id'], ['password' => $input['new_password']]);
            $this->jsonResponse(['message' => 'Password changed successfully']);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Failed to change password'], 500);
        }
    }

    public function logout(): void
    {
        // For JWT, logout is handled client-side by removing the token
        $user = $this->getCurrentUser();
        if ($user) {
            Logger::info("用户登出", 'auth', [
                'user_id' => $user['id'],
                'username' => $user['username']
            ]);
        }
        $this->jsonResponse(['message' => 'Logged out successfully']);
    }

    public function refresh(): void
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        // Generate new token
        $token = JWT::encode([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);

        $this->jsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
    }

    private function getCurrentUser(): ?array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        $payload = JWT::decode($token);
        
        if (!$payload || !isset($payload['user_id'])) {
            return null;
        }

        return $this->userModel->findById($payload['user_id']);
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

