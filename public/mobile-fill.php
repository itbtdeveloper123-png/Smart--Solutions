<?php
/**
 * mobile-fill.php — Mobile-Friendly Google Form Auto-Fill
 * Shows the Google Form with a floating "🤖 Fill Answers" button.
 * Works on mobile — no F12, no Console, no bookmarklets needed!
 * 
 * POST: url + questions (JSON) → renders form with embedded answers + fill button
 */

// ═══════════════════════════════════════════
// Handle POST: receive form URL and answers
// ═══════════════════════════════════════════
$formUrl = '';
$questionsJson = '[]';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($isPost) {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Handle form-encoded POST with 'payload' field
    if (!$input && isset($_POST['payload'])) {
        $input = json_decode($_POST['payload'], true);
    }
    
    $formUrl = trim($input['url'] ?? '');
    $questionsJson = json_encode($input['questions'] ?? []);
} else {
    $formUrl = trim($_GET['url'] ?? '');
    // For GET, read from temp file if token provided
    $token = trim($_GET['token'] ?? '');
    if ($token) {
        $tmpFile = sys_get_temp_dir() . '/gfs_' . preg_replace('/[^a-f0-9]/', '', $token) . '.json';
        if (file_exists($tmpFile)) {
            $data = json_decode(file_get_contents($tmpFile), true);
            $formUrl = $data['url'] ?? $formUrl;
            $questionsJson = json_encode($data['questions'] ?? []);
            @unlink($tmpFile);
        }
    }
}

if (empty($formUrl) || !preg_match('/docs\.google\.com\/forms/i', $formUrl)) {
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0b0f1a;color:#e2e8f0;text-align:center;padding:1rem;}</style>';
    echo '</head><body><div><h2>⚠️ Missing Google Form URL</h2><p style="color:#718096;">Please go back and enter a valid Google Form link.</p></div></body></html>';
    exit;
}

if (!preg_match('/^https?:\/\//', $formUrl)) {
    $formUrl = 'https://' . $formUrl;
}

// ═══════════════════════════════════════════
// Fetch Google Form HTML
// ═══════════════════════════════════════════
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $formUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) '
                             . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                             . 'Chrome/120.0.0.0 Mobile Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HEADER         => false,
]);

$html = curl_exec($ch);
$error = curl_error($ch);
$code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($error || $code !== 200 || empty($html)) {
    http_response_code(502);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0b0f1a;color:#e2e8f0;text-align:center;padding:1rem;}</style>';
    echo '</head><body><div><h2>⚠️ Cannot Load Form</h2><p style="color:#718096;">' . htmlspecialchars($error ?: "HTTP $code") . '</p></div></body></html>';
    exit;
}

// ═══════════════════════════════════════════
// Inject <base> tag
// ═══════════════════════════════════════════
$baseTag = '<base href="https://docs.google.com/">';
if (stripos($html, '<head>') !== false) {
    $html = preg_replace('/(<head[^>]*>)/i', '$1' . "\n" . $baseTag . "\n" . '<meta name="viewport" content="width=device-width,initial-scale=1">', $html, 1);
} else {
    $html = $baseTag . $html;
}

// ═══════════════════════════════════════════
// Build injected script — mobile-optimized
// ═══════════════════════════════════════════
$answersScript = '<script>window.__FORM_ANSWERS = ' . $questionsJson . ';</script>';

$injectedScript = $answersScript . <<<'SCRIPT'

<!-- ══════════ MOBILE FORM FILLER ══════════ -->
<style>
  /* Floating Fill Button — Mobile Optimized */
  .fs-fab {
    position: fixed; bottom: 24px; right: 20px; left: 20px;
    z-index: 99999;
    display: flex; align-items: center; justify-content: center;
    gap: 10px;
    padding: 16px 24px;
    background: linear-gradient(135deg, #63b3ed, #9f7aea);
    color: #fff;
    border: none; border-radius: 50px;
    font-size: 16px; font-weight: 700;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    box-shadow: 0 8px 32px rgba(99,179,237,0.5);
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
    -webkit-user-select: none;
    max-width: 500px;
    margin: 0 auto;
  }
  .fs-fab:active { transform: scale(0.96); }
  .fs-fab.fs-done { background: linear-gradient(135deg, #48bb78, #38a169); box-shadow: 0 8px 32px rgba(72,187,120,0.5); }
  .fs-fab.fs-busy { opacity: .7; }
  .fs-toast {
    position: fixed; top: 20px; left: 20px; right: 20px;
    z-index: 99998;
    background: #111827; color: #68d391;
    padding: 12px 18px; border-radius: 12px;
    font-size: 14px; font-weight: 600;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    display: none;
    border: 1px solid rgba(104,211,145,0.2);
  }
  .fs-toast.show { display: block; animation: fsSlideIn .3s ease; }
  .fs-toast.error { color: #fc8181; border-color: rgba(252,129,129,0.2); }
  @keyframes fsSlideIn { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
  
  /* Ensure the page is scrollable on mobile */
  body { -webkit-overflow-scrolling: touch; }
</style>

<div class="fs-toast" id="fsToast"></div>
<button class="fs-fab" id="fsFab" onclick="mobileFill()">
  <span style="font-size:22px;">🤖</span> Fill Answers
</button>

<script>
(function() {
  'use strict';
  
  // Toast helper
  function toast(msg, isError) {
    var t = document.getElementById('fsToast');
    t.textContent = msg;
    t.className = 'fs-toast show' + (isError ? ' error' : '');
    clearTimeout(t._timeout);
    t._timeout = setTimeout(function() { t.className = 'fs-toast'; }, 3000);
  }
  
  // Main fill function
  window.mobileFill = function() {
    var questions = window.__FORM_ANSWERS || [];
    if (!questions.length) {
      toast('⚠️ No answers found. Please go back and solve the form first.', true);
      return;
    }
    
    var fab = document.getElementById('fsFab');
    fab.classList.add('fs-busy');
    fab.innerHTML = '<span style="font-size:22px;">⏳</span> Filling...';
    
    var filled = 0;
    var total = questions.length;
    
    function doFill() {
      // Find question containers
      var cs = document.querySelectorAll(
        '[data-params],.freebirdFormviewerViewItemsItemItem,' +
        '[role="listitem"]:not([data-automation-id]),' +
        '.freebirdFormviewerViewNumberedItemContainer'
      );
      
      if (cs.length === 0) {
        cs = document.querySelectorAll('.freebirdFormviewerViewItemsItem,[data-item-id]');
      }
      
      questions.forEach(function(q, i) {
        var answer = q.answer || '';
        var type = q.type || 'text';
        if (!answer || answer === 'N/A' || answer.startsWith('⚠') || answer.startsWith('❌')) return;
        
        var container = cs[i];
        
        // MCQ / Dropdown / Checkbox
        if (type === 'multiple_choice' || type === 'dropdown' || type === 'checkbox') {
          var ansClean = answer.replace(/^[A-Z]\.\s*/, '').trim();
          var allLabels = document.querySelectorAll('label');
          var clicked = false;
          
          allLabels.forEach(function(l) {
            if ((type !== 'checkbox' && clicked)) return;
            var txt = (l.textContent || '').trim();
            if (txt === ansClean || txt === answer || txt.includes(ansClean) || ansClean.includes(txt)) {
              l.click();
              clicked = true;
              filled++;
            }
          });
          
          if (!clicked) {
            document.querySelectorAll('[role="radio"],[role="checkbox"]').forEach(function(r) {
              if ((type !== 'checkbox' && clicked)) return;
              var lbl = r.getAttribute('aria-label') || r.textContent || '';
              if (lbl.includes(ansClean) || ansClean.includes(lbl)) {
                r.click();
                clicked = true;
                filled++;
              }
            });
          }
        }
        
        // Text / Paragraph
        else if (type === 'short_text' || type === 'paragraph' || type === 'text') {
          var inp = null;
          if (container) {
            inp = container.querySelector('input[type="text"],input:not([type]),textarea');
          }
          if (!inp) {
            var allInputs = document.querySelectorAll('input[type="text"],input:not([type]),textarea');
            if (i < allInputs.length) inp = allInputs[i];
          }
          if (inp) {
            inp.focus();
            try {
              var ns = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
              if (ns && ns.set) { ns.set.call(inp, answer); }
              else { inp.value = answer; }
            } catch(e) { inp.value = answer; }
            inp.dispatchEvent(new Event('input', {bubbles: true}));
            inp.dispatchEvent(new Event('change', {bubbles: true}));
            filled++;
          }
        }
        
        // Other types (scale, date, time)
        else {
          if (container) {
            var labels2 = container.querySelectorAll('label');
            labels2.forEach(function(l) {
              if ((l.textContent || '').trim() === answer) { l.click(); filled++; }
            });
            var inp2 = container.querySelector('input');
            if (inp2 && inp2.value === '') {
              inp2.value = answer;
              inp2.dispatchEvent(new Event('change', {bubbles: true}));
              filled++;
            }
          }
        }
      });
      
      return filled;
    }
    
    // Execute fill with retries
    var result = doFill();
    
    if (result < total) {
      setTimeout(function() {
        var r2 = doFill();
        if (r2 > result) result = r2;
      }, 1500);
      setTimeout(function() {
        var r3 = doFill();
        if (r3 > result) result = r3;
      }, 3000);
    }
    
    // Update button after fill
    setTimeout(function() {
      fab.classList.remove('fs-busy');
      fab.classList.add('fs-done');
      fab.innerHTML = '<span style="font-size:22px;">✅</span> Filled ' + result + '/' + total + ' — Tap Submit above';
      toast('✅ Filled ' + result + '/' + total + ' answers! Scroll up and tap Submit.');
    }, 1000);
  };
  
  // Auto-show hint on load
  if (window.__FORM_ANSWERS && window.__FORM_ANSWERS.length > 0) {
    setTimeout(function() {
      toast('🤖 ' + window.__FORM_ANSWERS.length + ' answers ready! Tap the button below to fill.');
    }, 2000);
  }
  
})();
</script>
<!-- ══════════ END MOBILE FILLER ══════════ -->

SCRIPT;

// Insert before </body>
if (stripos($html, '</body>') !== false) {
    $html = str_ireplace('</body>', $injectedScript . "\n</body>", $html);
} else {
    $html .= $injectedScript;
}

// ═══════════════════════════════════════════
// Output
// ═══════════════════════════════════════════
header('Content-Type: text/html; charset=utf-8');
header_remove('X-Frame-Options');
echo $html;
