<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/openrouter-models.php';
require_once __DIR__ . '/../lib/db.php';

require_admin();

$validTools = ['hook_generator', 'score_checker', 'script_builder'];
$tool = $_GET['tool'] ?? 'hook_generator';
if (!in_array($tool, $validTools, true)) {
    $tool = 'hook_generator';
}

$db = get_db();
$flash = null;

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare('
        INSERT INTO tool_config (tool_key, model, system_prompt, free_limit_anonymous, free_limit_email, max_tokens, temperature)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(tool_key) DO UPDATE SET
            model = excluded.model,
            system_prompt = excluded.system_prompt,
            free_limit_anonymous = excluded.free_limit_anonymous,
            free_limit_email = excluded.free_limit_email,
            max_tokens = excluded.max_tokens,
            temperature = excluded.temperature
    ');
    $stmt->execute([
        $tool,
        $_POST['model'],
        $_POST['system_prompt'],
        (int) $_POST['free_limit_anonymous'],
        (int) $_POST['free_limit_email'],
        (int) $_POST['max_tokens'],
        (float) $_POST['temperature'],
    ]);
    $flash = 'Saved. Changes apply to the next generation — no deploy needed.';
}

// Load current config (or defaults if this tool hasn't been configured yet)
$stmt = $db->prepare('SELECT * FROM tool_config WHERE tool_key = ?');
$stmt->execute([$tool]);
$config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'model' => 'meta-llama/llama-3.3-70b-instruct:free',
    'system_prompt' => '',
    'free_limit_anonymous' => 3,
    'free_limit_email' => 10,
    'max_tokens' => 800,
    'temperature' => 0.9,
];

$refresh = isset($_GET['refresh_models']);
$models = get_openrouter_models($refresh);
$toolLabel = ucwords(str_replace('_', ' ', $tool));

admin_header($tool, $toolLabel);
?>

<h1><?= htmlspecialchars($toolLabel) ?></h1>
<p class="page-sub">Model, prompt, and free-tier limits for this tool. Applies immediately — no deploy required.</p>

<?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<form method="POST" class="card">
  <label>AI model</label>
  <select name="model">
    <?php foreach ($models as $m): ?>
      <option value="<?= htmlspecialchars($m['id']) ?>" <?= $m['id'] === $config['model'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($m['name']) ?><?= $m['is_free'] ? '  ·  FREE' : '' ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div style="margin-top:8px; font-size:12px; color:var(--slate);">
    <?= count($models) ?> models loaded from OpenRouter ·
    <a href="?tool=<?= urlencode($tool) ?>&refresh_models=1" style="color:var(--signal)">refresh list</a>
  </div>

  <label>System prompt</label>
  <textarea name="system_prompt" placeholder="You are a viral hook copywriter..."><?= htmlspecialchars($config['system_prompt']) ?></textarea>
  <div style="margin-top:6px; font-size:12px; color:var(--slate);">
    This is sent as the system message on every generation for this tool. Edit freely — takes effect on save.
  </div>

  <div class="row">
    <div>
      <label>Free uses (no email)</label>
      <input type="number" name="free_limit_anonymous" value="<?= (int)$config['free_limit_anonymous'] ?>" min="0">
    </div>
    <div>
      <label>Free uses per day (email given)</label>
      <input type="number" name="free_limit_email" value="<?= (int)$config['free_limit_email'] ?>" min="0">
    </div>
  </div>

  <div class="row">
    <div>
      <label>Max output tokens</label>
      <input type="number" name="max_tokens" value="<?= (int)$config['max_tokens'] ?>" min="100" max="4000">
    </div>
    <div>
      <label>Temperature</label>
      <input type="number" name="temperature" value="<?= htmlspecialchars((string)$config['temperature']) ?>" min="0" max="2" step="0.1">
    </div>
  </div>

  <button type="submit">Save changes</button>
</form>

<?php admin_footer(); ?>
