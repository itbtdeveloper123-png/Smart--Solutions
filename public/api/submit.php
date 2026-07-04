<?php
/**
 * api/submit.php — Auto-submit answers to Google Form
 * POST answers directly to Google's formResponse endpoint
 * Includes fbzx (CSRF token) and all required hidden fields
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/ai_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$submitUrl    = trim($input['submit_url'] ?? '');
$answers      = $input['answers'] ?? [];
$fbzx         = trim($input['fbzx'] ?? '');
$hiddenFields = $input['hiddenFields'] ?? [];
$cookies      = trim($input['cookies'] ?? '');
$cookieJar    = trim($input['cookieJar'] ?? '');

if (empty($submitUrl) || empty($answers)) {
    http_response_code(400);
    echo json_encode(['error' => 'submit_url and answers are required']);
    exit;
}

// Build POST fields
$postFields = [];

// Add entry answers: entry.XXXX=answer
foreach ($answers as $entryId => $answer) {
    if (!empty($entryId) && $answer !== '' && !str_starts_with($answer, '⚠️') && !str_starts_with($answer, '❌') && $answer !== 'N/A') {
        $postFields['entry.' . $entryId] = $answer;
    }
}

if (empty($postFields)) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid answers to submit']);
    exit;
}

// Add fbzx (CSRF token) — critical for Google Form submission
$fbzxValue = $fbzx;
if (empty($fbzxValue) && !empty($hiddenFields['fbzx'])) {
    $fbzxValue = $hiddenFields['fbzx'];
}
if ($fbzxValue) {
    $postFields['fbzx'] = $fbzxValue;
    // draftResponse: Google expects this format
    $postFields['draftResponse'] = '[null,null,"' . $fbzxValue . '"]';
}

// Add standard Google Form hidden fields
$postFields['fvv']        = '1';
$postFields['pageHistory'] = '0';
$postFields['continue']    = '1';

// Add any additional hidden fields extracted from the form
foreach ($hiddenFields as $key => $val) {
    if ($key !== 'fbzx' && !isset($postFields[$key])) {
        $postFields[$key] = $val;
    }
}

try {
    $ch = curl_init();
    
    $curlOpts = [
        CURLOPT_URL            => $submitUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postFields),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                                 . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                                 . 'Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Referer: ' . $submitUrl,
            'Origin: https://docs.google.com',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    
    // Use cookie jar from form fetch (critical for Google to accept submission)
    if (!empty($cookieJar) && file_exists($cookieJar)) {
        $curlOpts[CURLOPT_COOKIEFILE] = $cookieJar;
        $curlOpts[CURLOPT_COOKIEJAR]  = $cookieJar;
    } elseif (!empty($cookies)) {
        // Fallback: use cookie string
        $curlOpts[CURLOPT_COOKIE] = $cookies;
    }
    
    curl_setopt_array($ch, $curlOpts);

    $result  = curl_exec($ch);
    $error   = curl_error($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if ($error) {
        throw new RuntimeException("cURL Error: $error");
    }

    // Determine success: Google redirects to a confirmation page on success
    $isSuccess = ($code === 200) && (
        str_contains($finalUrl, 'formResponse') === false ||
        str_contains($result, 'Your response has been recorded') ||
        str_contains($result, 'Thank you') ||
        str_contains($result, 'Thanks') ||
        str_contains($result, 'formConfirm') ||
        str_contains($result, 'freebirdFormviewerViewResponseConfirm')
    );

    if ($isSuccess) {
        echo json_encode([
            'success' => true,
            'message' => '✅ ចម្លើយបានបញ្ជូនដោយជោគជ័យ!',
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($code === 200 && str_contains($result, 'fbzx')) {
        // Form page returned (likely validation error or missing fields)
        echo json_encode([
            'success' => false,
            'message' => '⚠️ Google Form បដិសេធការបញ្ជូន — ប្រហែលខ្វះ fields ឬមាន validation errors។',
            'debug'   => DEBUG_MODE ? 'Response contains form page (possible validation error). Submitted fields: ' . json_encode(array_keys($postFields)) : null,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "⚠️ Google Form returned HTTP $code. សូមសាកល្បងម្តងទៀត។",
            'debug'   => DEBUG_MODE ? substr($result, 0, 500) : null,
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => DEBUG_MODE ? $e->getMessage() : 'មានបញ្ហាក្នុងការបញ្ជូន!',
    ], JSON_UNESCAPED_UNICODE);
}
