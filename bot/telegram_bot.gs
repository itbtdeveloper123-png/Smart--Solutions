/**
 * ============================================
 * Google Apps Script — Telegram Bot Webhook
 * Form Solver Key Distribution Bot
 * ============================================
 * 
 * SETUP:
 * 1. Go to https://script.google.com → New Project
 * 2. Paste this entire code
 * 3. Set BOT_TOKEN below (from @BotFather)
 * 4. Deploy → New Deployment → Web App
 *    - Execute as: Me
 *    - Access: Anyone
 * 5. Copy the deployment URL
 * 6. Set webhook: https://api.telegram.org/bot<TOKEN>/setWebhook?url=<DEPLOYMENT_URL>
 * 
 * ============================================
 */

// ═══════════════ CONFIG ═══════════════
var BOT_TOKEN = 'YOUR_BOT_TOKEN_HERE';        // From @BotFather
var BOT_USERNAME = 'SmartSolutionsSupport_bot'; // Without @
var WEB_APP_URL = 'https://yourdomain.com/login.php'; // Your login page

// ═══════════════ WEBHOOK HANDLER ═══════════════

function doPost(e) {
  // Guard: when running manually in editor, e is undefined
  if (!e || !e.postData) {
    console.log('⚠️ doPost() called without webhook data. Deploy as Web App and use Telegram to test.');
    return ContentService.createTextOutput('Bot is running! Send /start on Telegram to test.');
  }
  
  try {
    var update = JSON.parse(e.postData.contents);
    handleUpdate(update);
  } catch (err) {
    console.error('Error:', err.message);
  }
  return ContentService.createTextOutput('OK');
}

function doGet(e) {
  return ContentService.createTextOutput('Form Solver Bot is running! 🤖');
}

/**
 * Test function — run this directly from editor to verify setup
 * Click "Run" → select "testBot" → check execution log
 */
function testBot() {
  console.log('🔍 Testing Bot Configuration...');
  console.log('BOT_TOKEN: ' + (BOT_TOKEN !== 'YOUR_BOT_TOKEN_HERE' ? '✅ Set' : '❌ Not set!'));
  console.log('BOT_USERNAME: ' + BOT_USERNAME);
  console.log('WEB_APP_URL: ' + WEB_APP_URL);
  
  // Test Telegram API connection
  try {
    var url = 'https://api.telegram.org/bot' + BOT_TOKEN + '/getMe';
    var resp = UrlFetchApp.fetch(url, { muteHttpExceptions: true });
    var data = JSON.parse(resp.getContentText());
    if (data.ok) {
      console.log('✅ Connected to Telegram! Bot: @' + data.result.username);
    } else {
      console.log('❌ Telegram API Error: ' + data.description);
    }
  } catch (err) {
    console.log('❌ Cannot connect to Telegram: ' + err.message);
  }
  
  console.log('✅ Test complete. Deploy as Web App to activate.');
}

// ═══════════════ UPDATE HANDLER ═══════════════

function handleUpdate(update) {
  if (update.message) {
    var msg = update.message;
    var chatId = msg.chat.id;
    var text = msg.text || '';
    var firstName = msg.chat.first_name || 'User';
    var username = msg.chat.username || '';
    var telegramId = String(msg.chat.id);
    
    if (text === '/start') {
      handleStart(chatId, telegramId, firstName, username);
    } else if (text === '/key' || text === '/getkey') {
      handleGetKey(chatId, telegramId, firstName);
    } else if (text === '/help') {
      sendMessage(chatId, 
        '🤖 *Form Solver Bot*\n\n' +
        '/start — ទទួល Key ថ្មីសម្រាប់ចូលប្រើ\n' +
        '/key — មើល Key របស់អ្នកឡើងវិញ\n' +
        '/help — ជំនួយ\n\n' +
        'បន្ទាប់ពីទទួល Key សូមចូលទៅកាន់ Web App ដើម្បី Login។'
      );
    }
  }
  
  // Handle callback queries (button clicks)
  if (update.callback_query) {
    var cb = update.callback_query;
    var chatId = cb.message.chat.id;
    var data = cb.data;
    
    if (data === 'get_new_key') {
      var telegramId = String(cb.from.id);
      var firstName = cb.from.first_name || 'User';
      handleStart(chatId, telegramId, firstName, cb.from.username || '');
    }
    
    answerCallback(cb.id);
  }
}

// ═══════════════ /START — GENERATE KEY ═══════════════

function handleStart(chatId, telegramId, firstName, username) {
  var props = PropertiesService.getScriptProperties();
  var existingKey = props.getProperty('USER_' + telegramId);
  
  var key;
  if (existingKey) {
    // Return existing key
    key = existingKey;
  } else {
    // Generate new unique key
    key = 'FS-' + generateRandomHex(16).toUpperCase();
    
    // Ensure uniqueness
    var allKeys = props.getProperties();
    while (objectHasValue(allKeys, key)) {
      key = 'FS-' + generateRandomHex(16).toUpperCase();
    }
    
    // Save key
    props.setProperty('USER_' + telegramId, key);
    props.setProperty('KEY_' + key, JSON.stringify({
      telegram_id: telegramId,
      first_name: firstName,
      username: username,
      created_at: new Date().toISOString(),
      is_used: false
    }));
  }
  
  // Send welcome message
  var message = 
    '👋 សួស្តី *' + escapeMarkdown(firstName) + '*!\n\n' +
    '🔑 *Key របស់អ្នក៖*\n`' + key + '`\n\n' +
    '📋 *របៀបប្រើ៖*\n' +
    '1\\. ចម្លង Key ខាងលើ\n' +
    '2\\. ចូលទៅកាន់ [Web App](' + WEB_APP_URL + ')\n' +
    '3\\. បញ្ចូលឈ្មោះ \\+ Key ដើម្បី Login\n\n' +
    '🎁 ទទួលបាន *25 Credits* ឥតគិតថ្លៃ!\n' +
    '💡 ប្រើ /key ដើម្បីមើល Key ឡើងវិញ';
  
  // Inline keyboard with quick actions
  var keyboard = {
    inline_keyboard: [
      [{ text: '🔑 ចម្លង Key', callback_data: 'copy_key' }],
      [{ text: '🌐 បើក Web App', url: WEB_APP_URL }]
    ]
  };
  
  sendMessageWithKeyboard(chatId, message, keyboard);
}

// ═══════════════ /KEY — RETRIEVE KEY ═══════════════

function handleGetKey(chatId, telegramId, firstName) {
  var props = PropertiesService.getScriptProperties();
  var key = props.getProperty('USER_' + telegramId);
  
  if (key) {
    var message = '🔑 *Key របស់អ្នក៖*\n`' + key + '`\n\nចម្លង Key នេះទៅប្រើក្នុង Web App ។';
    var keyboard = {
      inline_keyboard: [
        [{ text: '🌐 បើក Web App', url: WEB_APP_URL }]
      ]
    };
    sendMessageWithKeyboard(chatId, message, keyboard);
  } else {
    sendMessage(chatId, '⚠️ អ្នកមិនទាន់មាន Key នៅឡើយទេ។\nសូមប្រើ /start ដើម្បីទទួល Key ថ្មី។');
  }
}

// ═══════════════ TELEGRAM API HELPERS ═══════════════

function sendMessage(chatId, text) {
  var url = 'https://api.telegram.org/bot' + BOT_TOKEN + '/sendMessage';
  var payload = {
    chat_id: chatId,
    text: text,
    parse_mode: 'MarkdownV2',
    disable_web_page_preview: true
  };
  
  var options = {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  };
  
  UrlFetchApp.fetch(url, options);
}

function sendMessageWithKeyboard(chatId, text, keyboard) {
  var url = 'https://api.telegram.org/bot' + BOT_TOKEN + '/sendMessage';
  var payload = {
    chat_id: chatId,
    text: text,
    parse_mode: 'MarkdownV2',
    disable_web_page_preview: true,
    reply_markup: JSON.stringify(keyboard)
  };
  
  var options = {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  };
  
  UrlFetchApp.fetch(url, options);
}

function answerCallback(callbackId) {
  var url = 'https://api.telegram.org/bot' + BOT_TOKEN + '/answerCallbackQuery';
  var payload = { callback_query_id: callbackId };
  
  var options = {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  };
  
  UrlFetchApp.fetch(url, options);
}

// ═══════════════ VALIDATE KEY (called by PHP backend) ═══════════════

/**
 * Public API: Validate a key and return user info
 * Called by the PHP backend via HTTP GET
 * URL: .../exec?action=validate&key=FS-XXXX
 */
function validateKey(key) {
  var props = PropertiesService.getScriptProperties();
  var data = props.getProperty('KEY_' + key);
  
  if (!data) return null;
  
  var userData = JSON.parse(data);
  userData.key = key;
  return userData;
}

// ═══════════════ UTILITY FUNCTIONS ═══════════════

function generateRandomHex(length) {
  var result = '';
  var chars = '0123456789abcdef';
  for (var i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return result;
}

function escapeMarkdown(text) {
  return text.replace(/[_*[\]()~`>#+\-=|{}.!]/g, '\\$&');
}

function objectHasValue(obj, value) {
  var keys = Object.keys(obj);
  for (var i = 0; i < keys.length; i++) {
    if (obj[keys[i]] === value) return true;
  }
  return false;
}

/**
 * ============================================
 * DEPLOYMENT STEPS:
 * ============================================
 * 
 * 1. Replace BOT_TOKEN with your actual bot token
 * 2. Click "Deploy" → "New Deployment"
 * 3. Choose "Web App"
 * 4. Set "Execute as" = Me
 * 5. Set "Who has access" = Anyone
 * 6. Click "Deploy" → Copy the URL
 * 7. Set Telegram webhook:
 *    https://api.telegram.org/bot<TOKEN>/setWebhook?url=<YOUR_DEPLOY_URL>
 * 
 * 8. Test: Send /start to your bot on Telegram
 * 
 * ============================================
 * INTEGRATION WITH PHP BACKEND:
 * ============================================
 * 
 * The PHP Auth system can validate keys by calling:
 * GET https://script.google.com/macros/s/<SCRIPT_ID>/exec?action=validate&key=FS-XXXX
 * 
 * This returns JSON: { telegram_id, first_name, username, key, created_at, is_used }
 * 
 * ============================================
 */
