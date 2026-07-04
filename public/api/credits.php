<?php
/**
 * api/credits.php — Credit Management API
 * GET: check balance
 * POST: deduct credits (called after successful solve)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/CreditManager.php';

$auth = new Auth();
$user = $auth->requireAuthForApi();
$cm = new CreditManager($user['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'credits' => $cm->getBalance(),
        'can_solve' => $cm->canSolve(),
        'cost_per_solve' => CreditManager::COST_PER_SOLVE,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'deduct') {
        $formUrl = $input['form_url'] ?? '';
        $qCount = (int)($input['questions_count'] ?? 0);
        try {
            $cm->deductForSolve($formUrl, $qCount);
            echo json_encode(['success' => true, 'credits' => $cm->getBalance()]);
        } catch (RuntimeException $e) {
            http_response_code(402);
            echo json_encode(['error' => $e->getMessage(), 'credits' => $cm->getBalance()]);
        }
        exit;
    }
    
    if ($action === 'topup') {
        $order = $cm->createTopupOrder();
        echo json_encode($order);
        exit;
    }
    
    if ($action === 'verify') {
        $orderId = (int)($input['order_id'] ?? 0);
        $result = $cm->verifyTopup($orderId);
        echo json_encode(['success' => $result, 'credits' => $cm->getBalance()]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
