<?php
/**
 * process.php — ផ្ទាំងបង្ហាញ Form ដែល Clone + AI បំពេញចម្លើយ
 */

// ──── Autoload Classes ────────────────────────────────────────
require_once __DIR__ . '/../config/ai_config.php';
require_once __DIR__ . '/../src/FormScraper.php';
require_once __DIR__ . '/../src/DeepSeekSolver.php';

// ──── Input Validation ────────────────────────────────────────
$formUrl = trim($_POST['form_url'] ?? '');
$context = trim($_POST['context']  ?? '');

if (empty($formUrl)) {
    header('Location: index.php?error=' . urlencode('សូមបញ្ចូល Google Form URL!'));
    exit;
}

// ──── Process ─────────────────────────────────────────────────
$errorMsg   = '';
$questions  = [];
$formTitle  = 'Google Form';
$formDesc   = '';
$timeTaken  = 0;

try {
    $startTime = microtime(true);

    // 1. Scrape
    $scraper   = new FormScraper($formUrl);
    $scraper->fetchForm();
    $formTitle = $scraper->getFormTitle();
    $formDesc  = $scraper->getFormDescription();
    $questions = $scraper->parseQuestions();

    if (empty($questions)) {
        throw new RuntimeException('រកមិនឃើញសំណួរនៅក្នុង Form នេះទេ។ សូមពិនិត្យ URL ម្តងទៀត។');
    }

    // 2. Solve with AI
    $solver    = new DeepSeekSolver();
    $questions = $solver->solveAll($questions, $context);

    $timeTaken = round(microtime(true) - $startTime, 2);

} catch (InvalidArgumentException $e) {
    $errorMsg = 'URL មិនត្រឹមត្រូវ: ' . $e->getMessage();
} catch (RuntimeException $e) {
    $errorMsg = $e->getMessage();
} catch (Exception $e) {
    $errorMsg = DEBUG_MODE ? $e->getMessage() : 'មានបញ្ហា! សូមសាកល្បងម្តងទៀត។';
}

// ──── Helper Function ─────────────────────────────────────────
function typeLabel(string $type): string {
    return match ($type) {
        'multiple_choice' => 'MCQ',
        'checkbox'        => 'Checkbox',
        'dropdown'        => 'Dropdown',
        'paragraph'       => 'Paragraph',
        'linear_scale'    => 'Scale',
        'date'            => 'Date',
        'time'            => 'Time',
        default           => 'Text',
    };
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="AI ចម្លើយ Google Form — <?= htmlspecialchars($formTitle) ?>" />
  <title><?= htmlspecialchars($formTitle) ?> — AI Solved</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:         #0b0f1a;
      --surface:    #111827;
      --surface2:   #1a2235;
      --surface3:   #1f2d45;
      --border:     rgba(99,179,237,0.12);
      --accent:     #63b3ed;
      --accent2:    #9f7aea;
      --success:    #68d391;
      --success-bg: rgba(104,211,145,0.08);
      --warning:    #f6c90e;
      --error:      #fc8181;
      --text:       #e2e8f0;
      --muted:      #718096;
      --radius:     16px;
      --font:       'Outfit', sans-serif;
    }

    body {
      font-family: var(--font);
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      padding: 2rem 1rem 4rem;
    }

    body::before {
      content: '';
      position: fixed; inset: 0;
      background:
        radial-gradient(ellipse 80% 50% at 10% 5%, rgba(99,179,237,0.07) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 90% 90%, rgba(159,122,234,0.07) 0%, transparent 60%);
      pointer-events: none; z-index: 0;
    }

    /* ──── Layout ──── */
    .container {
      position: relative; z-index: 1;
      max-width: 800px;
      margin: 0 auto;
    }

    /* ──── Topbar ──── */
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: .55rem 1.1rem;
      border-radius: 100px;
      background: var(--surface2);
      border: 1px solid var(--border);
      color: var(--text);
      text-decoration: none;
      font-size: .85rem;
      font-weight: 500;
      transition: background .2s, border-color .2s;
    }
    .back-btn:hover { background: var(--surface3); border-color: rgba(99,179,237,0.3); }

    .stats {
      display: flex;
      gap: .6rem;
      flex-wrap: wrap;
    }
    .stat-pill {
      padding: .4rem .9rem;
      border-radius: 100px;
      background: var(--surface2);
      border: 1px solid var(--border);
      font-size: .75rem;
      color: var(--muted);
    }
    .stat-pill strong { color: var(--text); }

    /* ──── Form Header Card ──── */
    .form-header {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 1.5rem;
      position: relative;
      overflow: hidden;
    }
    .form-header::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--accent), var(--accent2));
    }
    .form-header h1 {
      font-size: 1.5rem;
      font-weight: 700;
      letter-spacing: -.02em;
      margin-bottom: .4rem;
    }
    .form-header .form-desc { font-size: .88rem; color: var(--muted); line-height: 1.5; }
    .form-header .form-url  {
      margin-top: 1rem;
      font-size: .75rem;
      color: var(--muted);
      word-break: break-all;
      display: flex; align-items: center; gap: 6px;
    }

    /* ──── Questions ──── */
    .questions-list { display: flex; flex-direction: column; gap: 1rem; }

    .q-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 1.5rem;
      animation: fadeIn .4s ease both;
      transition: border-color .25s, transform .25s;
      position: relative;
    }
    .q-card:hover { border-color: rgba(99,179,237,0.25); transform: translateY(-1px); }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .q-card:nth-child(1)  { animation-delay: .05s; }
    .q-card:nth-child(2)  { animation-delay: .10s; }
    .q-card:nth-child(3)  { animation-delay: .15s; }
    .q-card:nth-child(4)  { animation-delay: .20s; }
    .q-card:nth-child(5)  { animation-delay: .25s; }
    .q-card:nth-child(n+6){ animation-delay: .30s; }

    .q-meta {
      display: flex;
      align-items: center;
      gap: .5rem;
      margin-bottom: .85rem;
    }
    .q-num {
      min-width: 28px; height: 28px;
      border-radius: 8px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff;
      font-size: .75rem;
      font-weight: 700;
      display: flex; align-items: center; justify-content: center;
    }
    .q-type-badge {
      padding: 2px 10px;
      border-radius: 100px;
      background: var(--surface2);
      border: 1px solid var(--border);
      font-size: .68rem;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--muted);
    }
    .q-required {
      font-size: .68rem; color: var(--error);
      margin-left: auto;
    }

    .q-text {
      font-size: 1rem;
      font-weight: 500;
      line-height: 1.5;
      margin-bottom: 1rem;
    }

    /* Answer box */
    .answer-box {
      background: var(--success-bg);
      border: 1px solid rgba(104,211,145,0.25);
      border-radius: 12px;
      padding: .85rem 1rem;
      display: flex;
      align-items: flex-start;
      gap: .75rem;
    }
    .answer-icon { font-size: 1.1rem; flex-shrink: 0; margin-top: .1rem; }
    .answer-label {
      font-size: .7rem;
      font-weight: 600;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--success);
      margin-bottom: .3rem;
    }
    .answer-text {
      font-size: .92rem;
      color: var(--text);
      line-height: 1.5;
      word-break: break-word;
    }

    /* Choices */
    .choices-list {
      display: flex;
      flex-wrap: wrap;
      gap: .4rem;
      margin-top: .6rem;
      margin-bottom: .75rem;
    }
    .choice-chip {
      padding: .3rem .85rem;
      border-radius: 100px;
      background: var(--surface2);
      border: 1px solid var(--border);
      font-size: .78rem;
      color: var(--muted);
      transition: all .2s;
    }
    .choice-chip.selected {
      background: rgba(99,179,237,0.15);
      border-color: rgba(99,179,237,0.5);
      color: var(--accent);
      font-weight: 600;
    }

    /* ──── Error Card ──── */
    .error-card {
      background: rgba(252,129,129,0.08);
      border: 1px solid rgba(252,129,129,0.25);
      border-radius: 18px;
      padding: 2rem;
      text-align: center;
    }
    .error-card .emoji  { font-size: 3rem; margin-bottom: 1rem; }
    .error-card h2      { font-size: 1.2rem; margin-bottom: .5rem; }
    .error-card p       { color: var(--muted); font-size: .9rem; margin-bottom: 1.5rem; }
    .error-card .detail { font-size: .8rem; color: var(--error); margin-bottom: 1.5rem; }

    /* ──── Action Buttons ──── */
    .actions {
      display: flex;
      gap: .75rem;
      margin-top: 1.5rem;
      flex-wrap: wrap;
    }
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: .75rem 1.5rem;
      border-radius: var(--radius);
      border: none;
      font-family: var(--font);
      font-size: .9rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: transform .2s, box-shadow .2s;
    }
    .btn-primary {
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #fff;
      box-shadow: 0 4px 16px rgba(99,179,237,0.3);
    }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(99,179,237,0.4); }
    .btn-outline {
      background: var(--surface2);
      border: 1px solid var(--border);
      color: var(--text);
    }
    .btn-outline:hover { background: var(--surface3); border-color: rgba(99,179,237,0.3); }

    /* ──── Summary Banner ──── */
    .summary-banner {
      background: var(--surface);
      border: 1px solid rgba(104,211,145,0.2);
      border-radius: 18px;
      padding: 1.25rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    .summary-icon { font-size: 1.8rem; }
    .summary-text strong { font-size: 1rem; color: var(--success); }
    .summary-text p { font-size: .82rem; color: var(--muted); margin-top: 2px; }

    /* ──── Copy All Button ──── */
    #copyAll {
      margin-left: auto;
    }

    footer {
      text-align: center;
      margin-top: 2.5rem;
      font-size: .75rem;
      color: var(--muted);
    }
  </style>
</head>
<body>
<div class="container">

  <!-- Topbar -->
  <div class="topbar">
    <a href="index.php" class="back-btn">← ត្រឡប់ក្រោយ</a>
    <?php if (!$errorMsg): ?>
    <div class="stats">
      <div class="stat-pill">📋 <strong><?= count($questions) ?></strong> សំណួរ</div>
      <div class="stat-pill">⏱️ <strong><?= $timeTaken ?>s</strong></div>
      <div class="stat-pill">🤖 DeepSeek AI</div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($errorMsg): ?>
  <!-- ──── Error State ──── -->
  <div class="error-card">
    <div class="emoji">😔</div>
    <h2>មានបញ្ហាក្នុងការដំណើរការ</h2>
    <p>AI Solver មិនអាចបំពេញ Form នេះបានទេ។</p>
    <p class="detail">⚠️ <?= htmlspecialchars($errorMsg) ?></p>
    <div style="display:flex;justify-content:center;gap:.75rem;flex-wrap:wrap">
      <a href="index.php" class="btn btn-primary">🔄 សាកល្បងម្តងទៀត</a>
      <a href="index.php?url=<?= urlencode($formUrl) ?>" class="btn btn-outline">✏️ កែ URL</a>
    </div>
  </div>

  <?php else: ?>
  <!-- ──── Form Header ──── -->
  <div class="form-header">
    <h1>📋 <?= htmlspecialchars($formTitle) ?></h1>
    <?php if ($formDesc): ?>
      <p class="form-desc"><?= htmlspecialchars($formDesc) ?></p>
    <?php endif; ?>
    <p class="form-url">🔗 <span><?= htmlspecialchars($formUrl) ?></span></p>
  </div>

  <!-- ──── Success Banner ──── -->
  <div class="summary-banner">
    <div class="summary-icon">✅</div>
    <div class="summary-text">
      <strong>AI បានបំពេញចម្លើយ <?= count($questions) ?> សំណួររួចរាល់!</strong>
      <p>ឆ្លើយដោយ DeepSeek AI ក្នុង <?= $timeTaken ?> វិនាទី</p>
    </div>
    <button class="btn btn-outline" id="copyAll" onclick="copyAllAnswers()">📋 Copy ចម្លើយទាំងអស់</button>
  </div>

  <!-- ──── Questions List ──── -->
  <div class="questions-list">
    <?php foreach ($questions as $i => $q): ?>
    <div class="q-card">
      <div class="q-meta">
        <div class="q-num"><?= $i + 1 ?></div>
        <span class="q-type-badge"><?= typeLabel($q['type']) ?></span>
        <?php if (!empty($q['required'])): ?>
          <span class="q-required">* ចំបាច់</span>
        <?php endif; ?>
      </div>

      <div class="q-text"><?= htmlspecialchars($q['question']) ?></div>

      <!-- Choices chips (for MCQ/dropdown/checkbox) -->
      <?php if (!empty($q['choices'])): ?>
      <div class="choices-list">
        <?php foreach ($q['choices'] as $choice): ?>
          <span class="choice-chip <?= ($q['answer'] === $choice) ? 'selected' : '' ?>">
            <?= htmlspecialchars($choice) ?>
          </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- AI Answer -->
      <div class="answer-box">
        <div class="answer-icon">🤖</div>
        <div>
          <div class="answer-label">AI ចម្លើយ</div>
          <div class="answer-text" data-answer="<?= htmlspecialchars($q['answer']) ?>">
            <?= nl2br(htmlspecialchars($q['answer'])) ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Action Buttons -->
  <div class="actions">
    <a href="index.php" class="btn btn-outline">← Form ថ្មី</a>
    <button class="btn btn-primary" onclick="window.print()">🖨️ Print / Save PDF</button>
    <button class="btn btn-outline" onclick="copyAllAnswers()">📋 Copy ចម្លើយ</button>
  </div>

  <?php endif; ?>

  <footer>🤖 Google Form Solver — Powered by DeepSeek AI &nbsp;|&nbsp; <?= APP_NAME ?> v<?= APP_VERSION ?></footer>
</div>

<script>
  // Copy all answers to clipboard
  function copyAllAnswers() {
    const boxes = document.querySelectorAll('[data-answer]');
    const questions = document.querySelectorAll('.q-text');
    let text = '';
    boxes.forEach((box, i) => {
      const qText = questions[i] ? questions[i].textContent.trim() : `Q${i+1}`;
      text += `${i+1}. ${qText}\n   ➤ ${box.dataset.answer}\n\n`;
    });
    navigator.clipboard.writeText(text.trim()).then(() => {
      const btn = document.getElementById('copyAll');
      if (btn) { btn.textContent = '✅ Copied!'; setTimeout(() => { btn.textContent = '📋 Copy ចម្លើយទាំងអស់'; }, 2000); }
    });
  }
</script>
</body>
</html>
