<?php
/**
 * src/Auth.php — User Authentication & Session Management
 */

require_once __DIR__ . '/../config/database.php';

class Auth
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = getDB();
        initDatabase();
    }
    
    // ──── Login / Register ────────────────────────────
    
    /**
     * Login with username + key. 
     * First time: key must match a Telegram key → auto-register & link.
     * Returning: username-only login (no key needed).
     */
    public function login(string $username, string $key = ''): array
    {
        $username = trim($username);
        $key = trim($key);
        
        if (empty($username)) {
            throw new RuntimeException('សូមបញ្ចូល Username');
        }
        
        // ── If key provided: try Telegram key login or first-time registration ──
        if (!empty($key)) {
            return $this->loginWithKey($username, $key);
        }
        
        // ── Username-only: returning user ──
        return $this->loginWithUsername($username);
    }
    
    /**
     * Login with Telegram key (first time or key-based re-login)
     */
    private function loginWithKey(string $username, string $key): array
    {
        // 1) Check if this Telegram key exists and is valid
        $stmt = $this->db->prepare('SELECT * FROM telegram_keys WHERE access_key = ?');
        $stmt->execute([$key]);
        $telegramKey = $stmt->fetch();
        
        if (!$telegramKey) {
            throw new RuntimeException('Key មិនត្រឹមត្រូវ! សូមទទួល Key ពី Telegram Bot។');
        }
        
        // 2) Check if user already exists with this username
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Existing user — verify key matches or update
            if ($user['access_key'] !== $key) {
                $this->db->prepare('UPDATE users SET access_key = ? WHERE id = ?')
                         ->execute([$key, $user['id']]);
            }
            $this->db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')
                     ->execute([$user['id']]);
        } else {
            // 3) New user — register with the Telegram key
            $stmt = $this->db->prepare('INSERT INTO users (username, access_key, credits) VALUES (?, ?, 25)');
            $stmt->execute([$username, $key]);
            $user = [
                'id' => $this->db->lastInsertId(),
                'username' => $username,
                'access_key' => $key,
                'credits' => 25,
            ];
        }
        
        // Mark Telegram key as used
        $this->db->prepare('UPDATE telegram_keys SET is_used = 1 WHERE id = ?')
                 ->execute([$telegramKey['id']]);
        
        return $this->createSession($user);
    }
    
    /**
     * Login with just username (returning user who already registered)
     */
    private function loginWithUsername(string $username): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new RuntimeException('រកមិនឃើញគណនីនេះទេ! សូមចុះឈ្មោះដោយប្រើ Key ពី Telegram Bot ជាមុនសិន។');
        }
        
        $this->db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')
                 ->execute([$user['id']]);
        
        return $this->createSession($user);
    }
    
    /**
     * Create session token
     */
    private function createSession(array $user): array
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $this->db->prepare('INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)')
                 ->execute([$user['id'], $token, $expires]);
        
        return [
            'user_id'  => $user['id'],
            'username' => $user['username'],
            'credits'  => (int) $user['credits'],
            'token'    => $token,
        ];
    }
    
    /**
     * Validate session token
     */
    public function validateSession(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.credits, u.is_active 
             FROM sessions s JOIN users u ON s.user_id = u.id 
             WHERE s.token = ? AND s.expires_at > datetime("now")'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        
        if (!$row || !$row['is_active']) {
            return null;
        }
        
        return [
            'user_id'  => $row['id'],
            'username' => $row['username'],
            'credits'  => (int) $row['credits'],
        ];
    }
    
    /**
     * Get current user from cookie or header
     */
    public function getCurrentUser(): ?array
    {
        $token = $_COOKIE['auth_token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        
        if (empty($token)) return null;
        return $this->validateSession($token);
    }
    
    /**
     * Require authentication — redirect to login if not logged in
     */
    public function requireAuth(): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            http_response_code(401);
            header('Location: login.php');
            exit;
        }
        return $user;
    }
    
    /**
     * Require authentication for API — returns user or sends 401 JSON
     */
    public function requireAuthForApi(): array
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized. Please login.']);
            exit;
        }
        return $user;
    }
    
    /**
     * Logout — destroy session
     */
    public function logout(string $token): void
    {
        $this->db->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
        setcookie('auth_token', '', time() - 3600, '/');
    }
}
