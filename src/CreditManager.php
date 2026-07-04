<?php
/**
 * src/CreditManager.php — Credit System Management
 */

require_once __DIR__ . '/../config/database.php';

class CreditManager
{
    private PDO $db;
    private int $userId;
    
    public const COST_PER_SOLVE = 5;
    public const TOPUP_AMOUNT = 2.12;
    public const TOPUP_CREDITS = 1000;
    
    public function __construct(int $userId)
    {
        $this->db = getDB();
        $this->userId = $userId;
    }
    
    /**
     * Get current credit balance
     */
    public function getBalance(): int
    {
        $stmt = $this->db->prepare('SELECT credits FROM users WHERE id = ?');
        $stmt->execute([$this->userId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }
    
    /**
     * Check if user has enough credits for a solve
     */
    public function canSolve(): bool
    {
        return $this->getBalance() >= self::COST_PER_SOLVE;
    }
    
    /**
     * Deduct credits for a solve attempt
     * @throws RuntimeException if insufficient credits
     */
    public function deductForSolve(string $formUrl, int $questionCount): void
    {
        if (!$this->canSolve()) {
            throw new RuntimeException('អស់ Credit ហើយ! សូម Topup ដើម្បីបន្តប្រើប្រាស់។');
        }
        
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?')
                     ->execute([self::COST_PER_SOLVE, $this->userId, self::COST_PER_SOLVE]);
            
            $this->db->prepare(
                'INSERT INTO usage_logs (user_id, form_url, questions_count, credits_used) VALUES (?, ?, ?, ?)'
            )->execute([$this->userId, $formUrl, $questionCount, self::COST_PER_SOLVE]);
            
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Create a topup order and return QR/payment info
     */
    public function createTopupOrder(): array
    {
        $ref = 'TOPUP-' . strtoupper(bin2hex(random_bytes(4)));
        
        $this->db->prepare(
            'INSERT INTO topup_orders (user_id, amount, credits, qr_reference, status) VALUES (?, ?, ?, ?, "pending")'
        )->execute([$this->userId, self::TOPUP_AMOUNT, self::TOPUP_CREDITS, $ref]);
        
        $orderId = $this->db->lastInsertId();
        
        return [
            'order_id'      => $orderId,
            'amount'        => self::TOPUP_AMOUNT,
            'credits'       => self::TOPUP_CREDITS,
            'qr_reference'  => $ref,
            'qr_url'        => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($ref),
        ];
    }
    
    /**
     * Verify a topup payment (called when Telegram bot verification link is clicked)
     */
    public function verifyTopup(int $orderId): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM topup_orders WHERE id = ? AND user_id = ? AND status = "pending"');
        $stmt->execute([$orderId, $this->userId]);
        $order = $stmt->fetch();
        
        if (!$order) return false;
        
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE topup_orders SET status = "completed", verified_at = CURRENT_TIMESTAMP WHERE id = ?')
                     ->execute([$orderId]);
            
            $this->db->prepare('UPDATE users SET credits = credits + ?, total_topups = total_topups + ? WHERE id = ?')
                     ->execute([(int)$order['credits'], 1, $this->userId]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * Get usage statistics for a user
     */
    public function getStats(): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as total_solves, COALESCE(SUM(credits_used),0) as total_credits_used 
             FROM usage_logs WHERE user_id = ?'
        );
        $stmt->execute([$this->userId]);
        return $stmt->fetch();
    }
}
