<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/usage-gate.php';

$fingerprint = ensure_fingerprint(); // sets cookie on first visit, before any output

$db = get_db();

// Real usage stats for the ticker — falls back gracefully if the site is brand new
$hooksToday = (int) $db->query("SELECT COUNT(*) FROM hook_generations WHERE date(created_at) = date('now')")->fetchColumn();
$hooksAllTime = (int) $db->query("SELECT COUNT(*) FROM hook_generations")->fetchColumn();

$scoresToday = (int) $db->query("SELECT COUNT(*) FROM score_checks WHERE date(created_at) = date('now')")->fetchColumn();
$avgScoreRecent = $db->query("SELECT ROUND(AVG(score)) FROM score_checks WHERE date(created_at) = date('now')")->fetchColumn();

$gamePlaysToday = (int) $db->query("SELECT COUNT(*) FROM game_plays WHERE date(created_at) = date('now')")->fetchColumn();
$gameCorrectRate = $db->query("SELECT ROUND(AVG(correct) * 100) FROM game_plays WHERE date(created_at) = date('now')")->fetchColumn();

$latestHookRow = $db->query("
    SELECT output_json FROM hook_generations
    ORDER BY created_at DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$latestHookText = null;
if ($latestHookRow) {
    $decoded = json_decode($latestHookRow['output_json'], true);
    if (is_array($decoded) && !empty($decoded[0]['hook'])) {
        $latestHookText = $decoded[0]['hook'];
        if (mb_strlen($latestHookText) > 70) {
            $latestHookText = mb_substr($latestHookText, 0, 67) . '...';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>viralpublisher.com — free tools to test if it spreads</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap');

  :root{
    --ink:#10181B;
    --ink-soft:#182226;
    --paper:#F6F4EE;
    --signal:#C6FF3D;
    --coral:#FF6B4A;
    --slate:#8B96A3;
    --line: rgba(246,244,238,0.10);
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{
    background:var(--ink);
    color:var(--paper);
    font-family:'Inter',sans-serif;
    -webkit-font-smoothing:antialiased;
  }
  a{ text-decoration:none; color:inherit; }
  .wrap{max-width:1080px; margin:0 auto; padding:0 32px;}

  nav{display:flex; justify-content:space-between; align-items:center; padding:28px 0; border-bottom:1px solid var(--line);}
  .logo{font-family:'Space Grotesk'; font-weight:700; font-size:19px; letter-spacing:-0.02em;}
  .logo span{color:var(--signal);}
  .nav-links{display:flex; gap:32px; font-size:14px; color:var(--slate);}
  .nav-links a:hover{ color:var(--paper); }

  header.hero{padding:96px 0 64px; position:relative;}
  .eyebrow{
    font-family:'IBM Plex Mono'; font-size:12px; letter-spacing:0.08em; text-transform:uppercase;
    color:var(--signal); display:flex; align-items:center; gap:10px; margin-bottom:22px;
  }
  .dot{width:6px; height:6px; border-radius:50%; background:var(--signal); animation:pulse 1.6s infinite;}
  @keyframes pulse{0%,100%{opacity:1} 50%{opacity:0.25}}
  h1{
    font-family:'Space Grotesk'; font-weight:700; font-size:58px; line-height:1.02; letter-spacing:-0.02em;
    max-width:760px;
  }
  h1 em{font-style:normal; color:var(--signal);}
  .sub{max-width:520px; font-size:17px; line-height:1.6; color:var(--slate); margin-top:22px;}

  .ticker-band{
    margin-top:56px; border-top:1px solid var(--line); border-bottom:1px solid var(--line);
    overflow:hidden; white-space:nowrap; padding:14px 0;
  }
  .ticker-track{display:inline-block; animation:scroll 26s linear infinite;}
  .ticker-track span{
    font-family:'IBM Plex Mono'; font-size:13px; color:var(--slate); margin-right:48px;
  }
  .ticker-track b{color:var(--paper); font-weight:500;}
  .ticker-track .up{color:var(--signal);}
  @keyframes scroll{from{transform:translateX(0)} to{transform:translateX(-50%)}}

  .matrix-section{padding:88px 0 40px;}
  .section-head{display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:36px;}
  .section-head h2{font-family:'Space Grotesk'; font-size:28px; font-weight:600; letter-spacing:-0.01em;}
  .section-head p{color:var(--slate); font-size:14px; max-width:280px; text-align:right; line-height:1.5;}

  .grid{display:grid; grid-template-columns:1fr 1fr; gap:1px; background:var(--line); border:1px solid var(--line);}
  .card{
    background:var(--ink); padding:36px; position:relative; transition:background 0.25s ease;
    min-height:230px; display:flex; flex-direction:column; justify-content:space-between;
  }
  .card.live{ cursor:pointer; }
  .card.live:hover{background:var(--ink-soft);}
  .card.locked{ opacity:0.55; }
  .card-top{display:flex; justify-content:space-between; align-items:flex-start;}
  .stat{font-family:'IBM Plex Mono'; font-size:11px; color:var(--slate); letter-spacing:0.03em;}
  .badge{
    font-family:'IBM Plex Mono'; font-size:10px; letter-spacing:0.06em; text-transform:uppercase;
    padding:4px 9px; border-radius:20px; border:1px solid var(--line); color:var(--slate);
  }
  .badge.free{border-color:var(--signal); color:var(--signal);}
  .card h3{
    font-family:'Space Grotesk'; font-weight:600; font-size:23px; margin-top:26px; letter-spacing:-0.01em;
  }
  .card p.desc{color:var(--slate); font-size:14px; line-height:1.55; margin-top:10px; max-width:340px;}
  .card-cta{
    margin-top:24px; display:flex; align-items:center; gap:8px; font-size:14px; font-weight:500;
  }
  .card-cta .arrow{transition:transform 0.2s ease;}
  .card.live:hover .card-cta .arrow{transform:translateX(4px);}
  .card.accent .card-cta{color:var(--signal);}
  .card.neutral .card-cta{color:var(--paper);}
  .card.locked .card-cta{color:var(--slate);}

  .gate-section{padding:64px 0 100px;}
  .gate-card{
    border:1px solid var(--line); border-radius:2px; padding:44px; display:flex; justify-content:space-between;
    align-items:center; gap:40px; background:var(--ink-soft); flex-wrap:wrap;
  }
  .gate-copy h4{font-family:'Space Grotesk'; font-size:20px; font-weight:600; margin-bottom:8px;}
  .gate-copy p{color:var(--slate); font-size:14px; max-width:400px; line-height:1.55;}
  .gate-form{display:flex; gap:0; flex-shrink:0;}
  .gate-form input{
    background:transparent; border:1px solid var(--line); border-right:none; color:var(--paper);
    padding:13px 16px; font-size:14px; font-family:'Inter'; width:220px; outline:none;
  }
  .gate-form input::placeholder{color:var(--slate);}
  .gate-form button{
    background:var(--signal); color:var(--ink); border:none; padding:13px 22px; font-weight:600;
    font-size:14px; font-family:'Inter'; cursor:pointer; white-space:nowrap;
  }
  .gate-msg{ font-size:12px; margin-top:10px; width:100%; }
  .gate-msg.error{ color:var(--coral); }
  .gate-msg.success{ color:var(--signal); }

  footer{border-top:1px solid var(--line); padding:28px 0; display:flex; justify-content:space-between;
    color:var(--slate); font-size:12px; font-family:'IBM Plex Mono';}

  @media(max-width:720px){
    h1{font-size:38px;}
    .grid{grid-template-columns:1fr;}
    .section-head{flex-direction:column; align-items:flex-start; gap:12px;}
    .section-head p{text-align:left;}
    .gate-card{flex-direction:column; align-items:flex-start;}
    .gate-form{width:100%;}
    .gate-form input{width:100%;}
  }
</style>
</head>
<body>

<div class="wrap">
  <nav>
    <div class="logo">viral<span>publisher</span></div>
    <div class="nav-links">
      <a href="#tools">Tools</a>
    </div>
  </nav>

  <header class="hero">
    <div class="eyebrow"><span class="dot"></span> Free tools, no signup to start</div>
    <h1>Four ways to find out <em>if it spreads</em> — before you post it.</h1>
    <p class="sub">Score your hook, generate scripts, and stress-test ideas against the patterns that actually get shared. Built for creators and marketers who'd rather know than guess.</p>

    <div class="ticker-band">
      <div class="ticker-track">
        <?php
        // Build ticker items from real data where we have it, sensible fallbacks where we don't yet.
        $items = [];
        $items[] = $hooksToday > 0
            ? "<b>" . number_format($hooksToday) . "</b> hooks generated today"
            : "<b>" . number_format($hooksAllTime) . "</b> hooks generated so far";
        if ($latestHookText) {
            $items[] = "latest hook: <b>\"" . htmlspecialchars($latestHookText) . "\"</b>";
        }
        if ($scoresToday > 0) {
            $items[] = "<b>" . number_format($scoresToday) . "</b> scores checked today · avg <b>" . (int) $avgScoreRecent . "/100</b>";
        }
        if ($gamePlaysToday > 0) {
            $items[] = "<b>" . number_format($gamePlaysToday) . "</b> rounds played today · <b>" . (int) $gameCorrectRate . "%</b> guessed right";
        }
        $items[] = "4 tools live on this page";
        // Repeat the set so the marquee loops seamlessly
        $allItems = array_merge($items, $items);
        foreach ($allItems as $item) {
            echo "<span>{$item}</span>";
        }
        ?>
      </div>
    </div>
  </header>

  <section class="matrix-section" id="tools">
    <div class="section-head">
      <h2>Pick a tool</h2>
      <p>Every tool is free to try. No account needed for your first few runs.</p>
    </div>

    <div class="grid">
      <!-- Hook Generator: LIVE -->
      <a class="card accent live" href="/tools/hook-generator/">
        <div class="card-top">
          <span class="stat">01 / GENERATE</span>
          <span class="badge free">Free · 3 sets</span>
        </div>
        <div>
          <h3>Hook Generator</h3>
          <p class="desc">Give it a topic. Get 10 scroll-stopping hooks across 6 platforms, built from proven structures.</p>
        </div>
        <div class="card-cta">Generate hooks <span class="arrow">→</span></div>
      </a>

      <!-- Score Checker: LIVE -->
      <a class="card neutral live" href="/tools/score-checker/">
        <div class="card-top">
          <span class="stat">02 / SCORE</span>
          <span class="badge free">Free · 3 checks</span>
        </div>
        <div>
          <h3>Virality Score Checker</h3>
          <p class="desc">Paste a hook, caption, or script. Get a 0–100 score against known viral patterns, plus a rewrite.</p>
        </div>
        <div class="card-cta">Check your score <span class="arrow">→</span></div>
      </a>

      <!-- Game: LIVE -->
      <a class="card neutral live" href="/tools/game/">
        <div class="card-top">
          <span class="stat">03 / PLAY</span>
          <span class="badge free">Always free</span>
        </div>
        <div>
          <h3>Would This Go Viral?</h3>
          <p class="desc">Two real hooks, one guess. See which actually performed better — and why.</p>
        </div>
        <div class="card-cta">Play a round <span class="arrow">→</span></div>
      </a>

      <!-- Script Builder: LIVE -->
      <a class="card accent live" href="/tools/script-builder/">
        <div class="card-top">
          <span class="stat">04 / SCRIPT</span>
          <span class="badge free">1 free preview</span>
        </div>
        <div>
          <h3>Yapping Reel Script Builder</h3>
          <p class="desc">Full 7-part short-form video script from one topic — hook, structure, and CTA, ready to shoot.</p>
        </div>
        <div class="card-cta">Build a script <span class="arrow">→</span></div>
      </a>
    </div>
  </section>

  <section class="gate-section">
    <div class="gate-card">
      <div class="gate-copy">
        <h4>Unlock all four tools</h4>
        <p>One email unlocks extra runs across every tool on this page as they launch — no separate signup per tool, no spam, unsubscribe anytime.</p>
      </div>
      <div class="gate-form" id="homepage-gate-form">
        <input type="text" id="homepage-email" placeholder="you@email.com">
        <button id="homepage-gate-submit">Unlock free tier →</button>
      </div>
      <div class="gate-msg" id="homepage-gate-msg"></div>
    </div>
  </section>

  <footer>
    <span>viralpublisher.com</span>
    <span>built with viral publisher tools</span>
  </footer>
</div>

<script>
document.getElementById('homepage-gate-submit').addEventListener('click', async () => {
  const email = document.getElementById('homepage-email').value.trim();
  const msg = document.getElementById('homepage-gate-msg');
  msg.textContent = '';
  msg.className = 'gate-msg';

  if (!email || !email.includes('@')) {
    msg.textContent = 'Enter a valid email.';
    msg.classList.add('error');
    return;
  }

  try {
    const res = await fetch('/api/unlock.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, tool: 'homepage' })
    });

    if (res.ok) {
      msg.textContent = 'Unlocked — extended free access is live across all tools.';
      msg.classList.add('success');
      document.getElementById('homepage-email').value = '';
    } else {
      msg.textContent = 'Something went wrong. Try again.';
      msg.classList.add('error');
    }
  } catch (err) {
    msg.textContent = 'Something went wrong. Try again.';
    msg.classList.add('error');
  }
});
</script>

</body>
</html>
