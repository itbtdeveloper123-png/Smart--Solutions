<?php
/**
 * proxy-form.php — Proxy Google Form through our server
 * This makes the iframe same-origin, allowing JavaScript auto-fill.
 * 
 * Usage: proxy-form.php?url=https://docs.google.com/forms/d/e/.../viewform
 */

// ═══════════════════════════════════════════
// Fetch Google Form HTML
// ═══════════════════════════════════════════
$formUrl = trim($_GET['url'] ?? '');

if (empty($formUrl) || !preg_match('/docs\.google\.com\/forms/i', $formUrl)) {
    http_response_code(400);
    echo '<html><body><h2>Error: Invalid or missing Google Form URL</h2></body></html>';
    exit;
}

// Ensure URL has proper scheme
if (!preg_match('/^https?:\/\//', $formUrl)) {
    $formUrl = 'https://' . $formUrl;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $formUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                             . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                             . 'Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HEADER         => false,
]);

$html = curl_exec($ch);
$error = curl_error($ch);
$code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($error || $code !== 200 || empty($html)) {
    http_response_code(502);
    echo '<html><body><h2>Error: Could not load Google Form</h2><p>' . htmlspecialchars($error ?: "HTTP $code") . '</p></body></html>';
    exit;
}

// ═══════════════════════════════════════════
// Inject into HTML
// ═══════════════════════════════════════════

// 1. Add <base> tag so relative resources load from Google
$baseTag = '<base href="https://docs.google.com/">';

// Insert after <head> or at beginning
if (stripos($html, '<head>') !== false) {
    $html = preg_replace('/(<head[^>]*>)/i', '$1' . "\n" . $baseTag, $html, 1);
} else {
    $html = $baseTag . $html;
}

// 2. Remove X-Frame-Options / CSP that might block iframe (these are HTTP headers, not in HTML, so this is fine)

// 3. Inject our auto-fill listener script before </body>
$injectedScript = <<<'SCRIPT'

<!-- ══════════ FORM SOLVER AUTO-FILL (injected by proxy) ══════════ -->
<script>
(function() {
  'use strict';
  
  // Expose a global function that the parent page can call
  window.FormSolver = {
    /**
     * Auto-fill the form with given answers
     * @param {Array} questions - Array of {entryId, answer, type, choices}
     */
    fillAnswers: function(questions) {
      console.log('[FormSolver] 📋 Auto-filling ' + questions.length + ' answers...');
      
      if (!questions || questions.length === 0) {
        console.warn('[FormSolver] ⚠️ No answers provided');
        return { success: false, filled: 0, total: questions ? questions.length : 0 };
      }
      
      var filled = 0;
      var total = questions.length;
      
      // Helper: wait for element to appear
      function waitForElement(selector, timeout) {
        return new Promise(function(resolve) {
          var el = document.querySelector(selector);
          if (el) return resolve(el);
          var observer = new MutationObserver(function(mutations, obs) {
            var el = document.querySelector(selector);
            if (el) { obs.disconnect(); resolve(el); }
          });
          observer.observe(document.body, { childList: true, subtree: true });
          setTimeout(function() { observer.disconnect(); resolve(null); }, timeout || 3000);
        });
      }
      
      // Helper: fill a text input
      function fillTextInput(input, value) {
        if (!input) return false;
        // Focus and set value using native setter to trigger React/Angular bindings
        var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
        if (nativeSetter && nativeSetter.set) {
          nativeSetter.set.call(input, value);
        } else {
          input.value = value;
        }
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
        input.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
        return true;
      }
      
      function fillTextArea(textarea, value) {
        if (!textarea) return false;
        var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value');
        if (nativeSetter && nativeSetter.set) {
          nativeSetter.set.call(textarea, value);
        } else {
          textarea.value = value;
        }
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
      }
      
      // Main fill logic
      function doFill() {
        // Try multiple strategies to find and fill form elements
        
        // Strategy 1: Use Google Form's own data model
        // Google Forms store everything in FB_PUBLIC_LOAD_DATA_ and render dynamically
        
        // Find all question containers
        var containers = document.querySelectorAll('[data-params]');
        if (containers.length === 0) {
          containers = document.querySelectorAll('.freebirdFormviewerViewItemsItemItem');
        }
        if (containers.length === 0) {
          containers = document.querySelectorAll('[role="listitem"]');
        }
        
        console.log('[FormSolver] Found ' + containers.length + ' question containers');
        
        // Strategy 2: Find all text inputs and textareas
        var allInputs = document.querySelectorAll('input[type="text"], input:not([type]), textarea');
        var inputIndex = 0;
        
        // Strategy 3: Find radio groups and checkboxes
        var allRadioGroups = {};
        document.querySelectorAll('[role="radiogroup"]').forEach(function(group) {
          var ariaLabel = group.getAttribute('aria-label') || group.getAttribute('aria-labelledby') || '';
          allRadioGroups[ariaLabel] = group;
        });
        
        // Process each question
        questions.forEach(function(q, i) {
          var answer = q.answer || '';
          var type = q.type || 'text';
          var choices = q.choices || [];
          
          if (!answer || answer === 'N/A' || answer.startsWith('⚠️') || answer.startsWith('❌')) return;
          
          // For MCQ / Dropdown / Checkbox: click the matching option
          if (type === 'multiple_choice' || type === 'dropdown' || type === 'checkbox') {
            // Try clicking labels that contain the answer text
            var allLabels = document.querySelectorAll('label');
            var clicked = false;
            
            // First pass: exact match
            allLabels.forEach(function(label) {
              if (clicked && type !== 'checkbox') return;
              var labelText = (label.textContent || '').trim();
              if (labelText === answer || labelText === answer.replace(/^[A-Z]\.\s*/, '')) {
                label.click();
                clicked = true;
                filled++;
                console.log('[FormSolver] ✓ Q' + (i+1) + ' → clicked "' + labelText + '"');
              }
            });
            
            // Second pass: contains match
            if (!clicked) {
              allLabels.forEach(function(label) {
                if (clicked && type !== 'checkbox') return;
                var labelText = (label.textContent || '').trim();
                if (labelText.includes(answer) || answer.includes(labelText)) {
                  label.click();
                  clicked = true;
                  filled++;
                  console.log('[FormSolver] ✓ Q' + (i+1) + ' → clicked "' + labelText + '" (partial match)');
                }
              });
            }
            
            // Try clicking radio buttons / checkboxes directly
            if (!clicked) {
              document.querySelectorAll('[role="radio"], [role="checkbox"]').forEach(function(el) {
                if (clicked && type !== 'checkbox') return;
                var label = el.getAttribute('aria-label') || el.textContent || '';
                if (label.includes(answer) || answer.includes(label)) {
                  el.click();
                  clicked = true;
                  filled++;
                  console.log('[FormSolver] ✓ Q' + (i+1) + ' → clicked radio/checkbox');
                }
              });
            }
            
            if (!clicked) {
              console.warn('[FormSolver] ✗ Q' + (i+1) + ' → could not find option: ' + answer);
            }
          }
          
          // For text / paragraph / short_text
          else if (type === 'short_text' || type === 'paragraph' || type === 'text') {
            // Find the nearest text input
            // Google Forms wraps each question; try to find input within the question container
            var container = containers[i];
            var input = null;
            
            if (container) {
              input = container.querySelector('input[type="text"], input:not([type]), textarea');
            }
            
            // Fallback: use input by index
            if (!input && inputIndex < allInputs.length) {
              input = allInputs[inputIndex];
            }
            
            if (input) {
              if (input.tagName === 'TEXTAREA') {
                fillTextArea(input, answer);
              } else {
                fillTextInput(input, answer);
              }
              filled++;
              inputIndex++;
              console.log('[FormSolver] ✓ Q' + (i+1) + ' → filled text: ' + answer.substring(0, 50));
            } else {
              console.warn('[FormSolver] ✗ Q' + (i+1) + ' → no input found');
              inputIndex++;
            }
          }
          
          // For linear_scale
          else if (type === 'linear_scale') {
            document.querySelectorAll('label').forEach(function(label) {
              if ((label.textContent || '').trim() === answer) {
                label.click();
                filled++;
                console.log('[FormSolver] ✓ Q' + (i+1) + ' → scale: ' + answer);
              }
            });
          }
          
          // For date / time
          else {
            var container = containers[i];
            if (container) {
              var input = container.querySelector('input');
              if (input) {
                fillTextInput(input, answer);
                filled++;
                console.log('[FormSolver] ✓ Q' + (i+1) + ' → ' + type + ': ' + answer);
              }
            }
          }
        });
        
        return { success: filled > 0, filled: filled, total: total };
      }
      
      // Wait a bit for Google Form to render, then fill
      var result = doFill();
      
      // If not all filled, retry after a short delay (Google Forms renders progressively)
      if (result.filled < result.total) {
        setTimeout(function() {
          var retryResult = doFill();
          console.log('[FormSolver] Retry: filled ' + retryResult.filled + '/' + retryResult.total);
        }, 1500);
        
        setTimeout(function() {
          var retryResult2 = doFill();
          console.log('[FormSolver] Retry 2: filled ' + retryResult2.filled + '/' + retryResult2.total);
        }, 3000);
      }
      
      return result;
    },
    
    /**
     * Auto-submit the form after filling
     */
    submitForm: function() {
      var submitBtn = document.querySelector('[role="button"][aria-label="Submit"], '
        + 'div[role="button"]:last-child span:last-child');
      if (!submitBtn) {
        // Try to find the submit button by text
        var allSpans = document.querySelectorAll('span');
        allSpans.forEach(function(span) {
          if (span.textContent.trim() === 'Submit') {
            submitBtn = span.closest('[role="button"]');
          }
        });
      }
      if (submitBtn) {
        submitBtn.click();
        console.log('[FormSolver] 📤 Form submitted!');
        return true;
      }
      console.warn('[FormSolver] ⚠️ Could not find Submit button');
      return false;
    }
  };
  
  // Listen for postMessage from parent (in case iframe is same-origin via proxy)
  window.addEventListener('message', function(event) {
    try {
      var data = JSON.parse(event.data);
      if (data.action === 'fillForm' && data.questions) {
        console.log('[FormSolver] Received fill command via postMessage');
        var result = window.FormSolver.fillAnswers(data.questions);
        if (data.autoSubmit && result.success) {
          setTimeout(function() {
            window.FormSolver.submitForm();
          }, 2000);
        }
      }
    } catch(e) {}
  });
  
  // Signal to parent that we're ready
  console.log('[FormSolver] ✅ Proxy loaded. FormSolver API ready.');
  
  // If parent already set answers (same-origin direct access), auto-fill
  if (window.__pendingAnswers) {
    console.log('[FormSolver] Found pending answers, auto-filling...');
    setTimeout(function() {
      window.FormSolver.fillAnswers(window.__pendingAnswers);
      window.__pendingAnswers = null;
    }, 2000);
  }
  
})();
</script>
<!-- ══════════ END FORM SOLVER INJECTION ══════════ -->

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
// Remove X-Frame-Options if set by PHP config
header_remove('X-Frame-Options');
echo $html;
