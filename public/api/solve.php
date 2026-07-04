<?php
/**
 * api/solve.php — AJAX endpoint
 * ទទួល Google Form URL, Scrape + AI Solve, ត្រឡប់ JSON
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../../config/ai_config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/FormScraper.php';
require_once __DIR__ . '/../../src/DeepSeekSolver.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/CreditManager.php';

// Auth check
$auth = new Auth();
$user = $auth->requireAuthForApi();
$cm = new CreditManager($user['user_id']);

// Credit check
if (!$cm->canSolve()) {
    http_response_code(402);
    echo json_encode(['error' => 'អស់ Credit! សូម Topup', 'credits' => $cm->getBalance()], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$formUrl = trim($input['form_url'] ?? '');
$context = trim($input['context']  ?? '');

if (empty($formUrl)) {
    http_response_code(400);
    echo json_encode(['error' => 'form_url is required']);
    exit;
}

try {
    $startTime = microtime(true);

    // 1. Scrape
    $scraper   = new FormScraper($formUrl);
    $scraper->fetchForm();
    $formTitle = $scraper->getFormTitle();
    $formDesc  = $scraper->getFormDescription();
    $questions = $scraper->parseQuestions();
    $embedUrl  = $scraper->getEmbedUrl();
    $submitUrl = $scraper->getFormResponseUrl();
    $fbzx      = $scraper->getFbzxToken();
    $hiddenFields = $scraper->getFormHiddenFields();
    $cookies    = $scraper->getCookiesString();
    $cookieJar  = $scraper->getCookieJarPath();

    if (empty($questions)) {
        throw new RuntimeException('រកមិនឃើញសំណួរនៅក្នុង Form នេះទេ។ សូមពិនិត្យ URL ម្តងទៀត។');
    }

    // 2. Solve with AI
    $solver    = new DeepSeekSolver();
    $questions = $solver->solveAll($questions, $context);

    // 3. Deduct credits
    $cm->deductForSolve($formUrl, count($questions));

    $timeTaken = round(microtime(true) - $startTime, 2);

    echo json_encode([
        'success'      => true,
        'formTitle'    => $formTitle,
        'formDesc'     => $formDesc,
        'embedUrl'     => $embedUrl,
        'submitUrl'    => $submitUrl,
        'fbzx'         => $fbzx,
        'hiddenFields' => $hiddenFields,
        'cookies'      => $cookies,
        'cookieJar'    => $cookieJar,
        'questions'    => $questions,
        'timeTaken'    => $timeTaken,
        'credits'      => $cm->getBalance(),
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'URL មិនត្រឹមត្រូវ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    $msg = DEBUG_MODE ? $e->getMessage() : 'មានបញ្ហា! សូមសាកល្បងម្តងទៀត។';
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
}
