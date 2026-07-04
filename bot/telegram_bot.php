<?php
/**
 * bot/telegram_bot.php — Telegram Bot Webhook Handler
 * 
 * Setup:
 * 1. Create bot with @BotFather → get BOT_TOKEN
 * 2. Set webhook: https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://yourdomain.com/bot/telegram_bot.php
 * 3. Configure BOT_TOKEN and BOT_USERNAME below
 */

// ═══════════════════════════════════════
// CONFIGURATION
// ═══════════════════════════════════════
define('BOT_TOKEN', 'YOUR_BOT_TOK8746347891:AAFgWi5OY6Bdu-7POzF6hkbskCgcW2cH8sAEN_HERE');     // From @BotFather
define('BOT_USERNAME', '@SmartSolutionsSupport_bot');       // Without @

require_once __DIR__ . '/../config/database.php';

// ═══════════════════════════════════════
// MAIN
// ═══════════════════════════════════════
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(200);
    echo 'OK';
    exit;
}

try {
    handleUpdate($update);
} catch (Exception $e) {
    error_log('Telegram Bot Error: ' . $e->getMessage());
}

http_response_code(200);
echo 'OK';

// ═══════════════════════════════════════
// FUNCTIONS
// ═══════════════════════════════════════

function handleUpdate(array $update): void
{
    // Handle /start command
    if (isset($update['message'])) {
        $msg = $update['message'];
        $chatId = $msg['chat']['id'];
        $text = $msg['text'] ?? '';
        $firstName = $msg['chat']['first_name'] ?? 'User';
        $username = $msg['chat']['username'] ?? '';
        $telegramId = (string) $msg['chat']['id'];
        
        if ($text === '/start') {
            handleStart($chatId, $telegramId, $firstName, $username);
        } elseif ($text === '/key' || $text === '/getkey') {
            handleGetKey($chatId, $telegramId, $firstName);
        } elseif ($text === '/help') {
            sendMessage($chatId, "🤖 *Form Solver Bot*\n\n" .
                "/start — ទទួល Key ថ្មីសម្រាប់ចូលប្រើ\n" .
                "/key — មើល Key របស់អ្នកឡើងវិញ\n" .
                "/help — ជំនួយ\n\n" .
                "បន្ទាប់ពីទទួល Key សូមចូលទៅកាន់ Web App ដើម្បី Login។");
        }
    }
    
    // Handle callback queries (button clicks)
    if (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $chatId = $callback['message']['chat']['id'];
        $data = $callback['data'];
        
        if ($data === 'get_new_key') {
            $telegramId = (string) $callback['from']['id'];
            $firstName = $callback['from']['first_name'] ?? 'User';
            handleStart($chatId, $telegramId, $firstName, $callback['from']['username'] ?? '');
        }
        
        // Answer callback to remove loading
        answerCallback($callback['id']);
    }
}

function handleStart(int $chatId, string $telegramId, string $firstName, string $username): void
{
    $db = getDB();
    initDatabase();
    
    // Check if user already has a key
    $stmt = $db->prepare('SELECT access_key, is_used FROM telegram_keys WHERE telegram_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$telegramId]);
    $existing = $stmt->fetch();
    
    if ($existing && !$existing['is_used']) {
        // Return existing unused key
        $key = $existing['access_key'];
    } else {
        // Generate new unique key
        $key = 'FS-' . strtoupper(bin2hex(random_bytes(8)));
        
        // Ensure uniqueness
        $check = $db->prepare('SELECT id FROM telegram_keys WHERE access_key = ?');
        $check->execute([$key]);
        while ($check->fetch()) {
            $key = 'FS-' . strtoupper(bin2hex(random_bytes(8)));
            $check->execute([$key]);
        }
        
        // Save to database
        $db->prepare('INSERT INTO telegram_keys (telegram_id, access_key, username, first_name) VALUES (?, ?, ?, ?)')
           ->execute([$telegramId, $key, $username, $firstName]);
    }
    
    // Send welcome message with key
    $message = "👋 សួស្តី *{$firstName}*!\n\n" .
               "🔑 *Key របស់អ្នក៖*\n`{$key}`\n\n" .
               "📋 *របៀបប្រើ៖*\n" .
               "1. ចម្លង Key ខាងលើ\n" .
               "2. ចូលទៅកាន់ Web App\n" .
               "3. បញ្ចូលឈ្មោះ + Key ដើម្បី Login\n\n" .
               "🎁 ទទួលបាន *25 Credits* ឥតគិតថ្លៃ!\n" .
               "💡 ប្រើ /key ដើម្បីមើល Key ឡើងវិញ";
    
    sendMessage($chatId, $message);
}

function handleGetKey(int $chatId, string $telegramId, string $firstName): void
{
    $db = getDB();
    initDatabase();
    
    $stmt = $db->prepare('SELECT access_key FROM telegram_keys WHERE telegram_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$telegramId]);
    $row = $stmt->fetch();
    
    if ($row) {
        $message = "🔑 *Key របស់អ្នក៖*\n`{$row['access_key']}`\n\n" .
                   "ចម្លង Key នេះទៅប្រើក្នុង Web App ។";
    } else {
        $message = "⚠️ អ្នកមិនទាន់មាន Key នៅឡើយទេ។\nសូមប្រើ /start ដើម្បីទទួល Key ថ្មី។";
    }
    
    sendMessage($chatId, $message);
}

// ═══════════════════════════════════════
// TELEGRAM API HELPERS
// ═══════════════════════════════════════

function sendMessage(int $chatId, string $text): void
{
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true,
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function answerCallback(string $callbackId): void
{
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/answerCallbackQuery';
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['callback_query_id' => $callbackId],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
