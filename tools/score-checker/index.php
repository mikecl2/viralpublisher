<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/usage-gate.php';

$fingerprint = ensure_fingerprint();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Virality Score Checker — viralpublisher.com</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap');

  :root{
    --ink:#10181B; --ink-soft:#182226; --paper:#F6F4EE; --signal:#C6FF3D;
    --coral:#FF6B4A; --slate:#8B96A3; --line:rgba(246,244,238,0.10);
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{ background:var(--ink); color:var(--paper); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }
  .wrap{ max-width:720px; margin:0 auto; padding:0 24px; }

  nav{ display:flex; justify-content:space-between; align-items:center; padding:26px 0; border-bottom:1px solid var(--line); }
  .logo{ font-family:'Space Grotesk'; font-weight:700; font-size:17px; }
  .logo span{ color:var(--signal); }
  .back{ font-size:13px; color:var(--slate); }

  .head{ padding:52px 0 36px; }
  .eyebrow{
    font-family:'IBM Plex Mono'; font-size:11px; letter-spacing:0.08em; text-transform:uppercase;
    color:var(--signal); margin-bottom:14px;
  }
  h1{ font-family:'Space Grotesk'; font-weight:600; font-size:34px; letter-spacing:-0.01em; max-width:520px; }
  .sub{ color:var(--slate); font-size:15px; margin-top:12px; max-width:460px; line-height:1.55; }

  .field-group{ margin-bottom:24px; }
  .field-label{ font-size:13px; color:var(--slate); margin-bottom:10px; display:flex; justify-content:space-between; }
  textarea{
    width:100%; background:var(--ink-soft); border:1px solid var(--line); color:var(--paper);
    padding:16px 18px; font-size:16px; font-family:'Inter'; outline:none; border-radius:2px;
    min-height:110px; resize:vertical; line-height:1.5;
  }
  textarea:focus{ border-color:var(--signal); }
  textarea::placeholder{ color:var(--slate); }
  .char-count{ font-family:'IBM Plex Mono'; font-size:11px; color:var(--slate); }
  .char-count.over{ color:var(--coral); }

  .chip-row{ display:flex; flex-wrap:wrap; gap:8px; }
  .chip{
    padding:9px 16px; border:1px solid var(--line); border-radius:20px; font-size:13px;
    color:var(--slate); cursor:pointer; transition:all 0.15s ease; background:transparent;
  }
  .chip:hover{ border-color:var(--slate); color:var(--paper); }
  .chip.selected{ border-color:var(--signal); color:var(--signal); background:rgba(198,255,61,0.06); }

  .primary-btn{
    background:var(--signal); color:var(--ink); border:none; padding:16px 28px;
    font-weight:600; font-size:15px; cursor:pointer; border-radius:2px; width:100%;
    margin-top:12px; transition:opacity 0.15s ease;
  }
  .primary-btn:hover{ opacity:0.9; }
  .primary-btn:disabled{ opacity:0.4; cursor:not-allowed; }
  .limit-note{ font-family:'IBM Plex Mono'; font-size:11px; color:var(--slate); margin-top:14px; text-align:center; }

  /* LOADING */
  .loading-wrap{ padding:60px 0; display:flex; flex-direction:column; align-items:center; gap:20px; }
  .gauge-ring{
    width:120px; height:120px; border-radius:50%; border:3px solid var(--line);
    border-top-color:var(--signal); animation:spin 1s linear infinite;
  }
  @keyframes spin{ to{ transform:rotate(360deg); } }
  .loading-label{ font-family:'IBM Plex Mono'; font-size:12px; color:var(--slate); }

  /* RESULTS */
  .score-hero{ display:flex; align-items:center; gap:28px; padding:12px 0 32px; }
  .score-number{ font-family:'Space Grotesk'; font-weight:700; font-size:64px; letter-spacing:-0.02em; }
  .score-number.high{ color:var(--signal); }
  .score-number.mid{ color:#E8D34D; }
  .score-number.low{ color:var(--coral); }
  .score-max{ font-size:20px; color:var(--slate); font-weight:400; }
  .score-verdict{ font-size:15px; color:var(--paper); line-height:1.5; max-width:380px; }

  .breakdown{ display:flex; flex-direction:column; gap:14px; padding-bottom:32px; }
  .breakdown-row{ opacity:0; animation:rowin 0.35s ease forwards; }
  @keyframes rowin{ from{opacity:0; transform:translateY(4px);} to{opacity:1; transform:translateY(0);} }
  .breakdown-top{ display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px; }
  .breakdown-top .cat{ color:var(--paper); font-weight:500; }
  .breakdown-top .pts{ font-family:'IBM Plex Mono'; color:var(--slate); }
  .bar-track{ height:6px; background:var(--ink-soft); border-radius:3px; overflow:hidden; }
  .bar-fill{ height:100%; background:var(--signal); border-radius:3px; transition:width 0.6s ease; }
  .breakdown-note{ font-size:12px; color:var(--slate); margin-top:5px; line-height:1.4; }

  .rewrite-box{
    border:1px solid var(--line); border-radius:2px; padding:22px; background:var(--ink-soft);
    margin-bottom:24px; position:relative;
  }
  .rewrite-label{ font-family:'IBM Plex Mono'; font-size:11px; text-transform:uppercase; letter-spacing:0.05em;
    color:var(--signal); margin-bottom:10px; }
  .rewrite-text{ font-size:15px; line-height:1.55; }
  .rewrite-blur{ filter:blur(6px); user-select:none; }
  .copy-btn{
    background:transparent; border:1px solid var(--line); color:var(--paper);
    padding:8px 14px; font-size:12px; border-radius:2px; cursor:pointer; font-family:'Inter';
    margin-top:14px;
  }
  .copy-btn:hover{ border-color:var(--signal); color:var(--signal); }
  .copy-btn.copied{ border-color:var(--signal); color:var(--signal); }

  .again-row{ display:flex; justify-content:center; padding:8px 0 60px; }
  .text-btn{ background:none; border:none; color:var(--slate); font-size:14px; cursor:pointer; font-family:'Inter'; }
  .text-btn:hover{ color:var(--paper); }

  .gate-bar{
    border:1px solid var(--signal); background:rgba(198,255,61,0.05); border-radius:2px;
    padding:24px; margin-bottom:24px;
  }
  .gate-bar h4{ font-family:'Space Grotesk'; font-size:17px; font-weight:600; margin-bottom:6px; }
  .gate-bar p{ color:var(--slate); font-size:13px; margin-bottom:16px; line-height:1.5; }
  .gate-form{ display:flex; gap:0; }
  .gate-form input{
    flex:1; background:var(--ink); border:1px solid var(--line); border-right:none; color:var(--paper);
    padding:13px 16px; font-size:14px; font-family:'Inter'; outline:none;
  }
  .gate-form button{
    background:var(--signal); color:var(--ink); border:none; padding:13px 22px; font-weight:600;
    font-size:14px; cursor:pointer; white-space:nowrap;
  }
  .gate-msg{ font-size:12px; margin-top:10px; }
  .gate-msg.error{ color:var(--coral); }
  .gate-msg.success{ color:var(--signal); }

  .hidden{ display:none; }

  @media(max-width:600px){
    h1{ font-size:27px; }
    .score-hero{ flex-direction:column; align-items:flex-start; gap:14px; }
    .gate-form{ flex-direction:column; }
    .gate-form input{ border-right:1px solid var(--line); border-bottom:none; }
  }
</style>
</head>
<body>
<div class="wrap">
  <nav>
    <div class="logo">viral<span>publisher</span></div>
    <a class="back" href="/">← All tools</a>
  </nav>

  <div class="head">
    <div class="eyebrow">01 / Score</div>
    <h1>Virality Score Checker</h1>
    <p class="sub">Paste a hook, caption, or opening line. Get a 0–100 score against proven patterns, a category breakdown, and a rewrite.</p>
  </div>

  <!-- STEP 1: input -->
  <div id="step-input">
    <div class="field-group">
      <label class="field-label">
        <span>What do you want scored?</span>
        <span class="char-count" id="char-count">0 / 600</span>
      </label>
      <textarea id="text-input" placeholder="e.g. Nobody talks about the part of cold plunges that actually works." maxlength="600"></textarea>
    </div>

    <div class="field-group">
      <label class="field-label"><span>Platform</span></label>
      <div class="chip-row" id="platform-chips">
        <div class="chip selected" data-value="general">General</div>
        <div class="chip" data-value="tiktok">TikTok</div>
        <div class="chip" data-value="reels">Reels</div>
        <div class="chip" data-value="shorts">Shorts</div>
        <div class="chip" data-value="x">X</div>
        <div class="chip" data-value="linkedin">LinkedIn</div>
      </div>
    </div>

    <button class="primary-btn" id="score-btn">Check my score</button>
    <div class="limit-note">Free to try · no account needed</div>
  </div>

  <!-- STEP 2: loading -->
  <div id="step-loading" class="hidden">
    <div class="loading-wrap">
      <div class="gauge-ring"></div>
      <div class="loading-label">Scoring against the pattern library...</div>
    </div>
  </div>

  <!-- STEP 3: results -->
  <div id="step-results" class="hidden">
    <div id="results-content"></div>

    <div id="gate-bar" class="gate-bar hidden">
      <h4>You've used today's free checks</h4>
      <p>Enter your email to unlock more — this also unlocks extended free access across every tool on the site, not just this one.</p>
      <div class="gate-form">
        <input type="text" id="gate-email" placeholder="you@email.com">
        <button id="gate-submit">Unlock →</button>
      </div>
      <div class="gate-msg" id="gate-msg"></div>
    </div>

    <div class="again-row" id="again-row">
      <button class="text-btn" id="again-btn">Check another one</button>
    </div>
  </div>
</div>

<script>
const stepInput = document.getElementById('step-input');
const stepLoading = document.getElementById('step-loading');
const stepResults = document.getElementById('step-results');
const textInput = document.getElementById('text-input');
const charCount = document.getElementById('char-count');
const scoreBtn = document.getElementById('score-btn');
const resultsContent = document.getElementById('results-content');
const gateBar = document.getElementById('gate-bar');
const againRow = document.getElementById('again-row');
const toolKey = 'score_checker';
const MAX_CHARS = 600;

let selectedPlatform = 'general';

document.querySelectorAll('#platform-chips .chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('#platform-chips .chip').forEach(c => c.classList.remove('selected'));
    chip.classList.add('selected');
    selectedPlatform = chip.dataset.value;
  });
});

textInput.addEventListener('input', () => {
  const len = textInput.value.length;
  charCount.textContent = `${len} / ${MAX_CHARS}`;
  charCount.classList.toggle('over', len > MAX_CHARS);
});

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function scoreClass(score) {
  if (score >= 80) return 'high';
  if (score >= 55) return 'mid';
  return 'low';
}

function renderResults(result, locked) {
  const cls = scoreClass(result.score);
  let html = `
    <div class="score-hero">
      <div class="score-number ${cls}">${result.score}<span class="score-max">/100</span></div>
      <div class="score-verdict">${escapeHtml(result.verdict || '')}</div>
    </div>
    <div class="breakdown">
  `;

  (result.breakdown || []).forEach((row, i) => {
    const pct = Math.round((row.points / row.max) * 100);
    html += `
      <div class="breakdown-row" style="animation-delay:${i * 0.06}s">
        <div class="breakdown-top">
          <span class="cat">${escapeHtml(row.category)}</span>
          <span class="pts">${row.points}/${row.max}</span>
        </div>
        <div class="bar-track"><div class="bar-fill" style="width:${pct}%"></div></div>
        <div class="breakdown-note">${escapeHtml(row.note || '')}</div>
      </div>
    `;
  });

  html += `</div>`;

  if (result.rewrite) {
    html += `
      <div class="rewrite-box">
        <div class="rewrite-label">Suggested rewrite</div>
        <div class="rewrite-text ${locked ? 'rewrite-blur' : ''}" id="rewrite-text">${escapeHtml(result.rewrite)}</div>
        <button class="copy-btn" id="copy-rewrite-btn" ${locked ? 'disabled' : ''}>Copy rewrite</button>
      </div>
    `;
  }

  resultsContent.innerHTML = html;

  const copyBtn = document.getElementById('copy-rewrite-btn');
  if (copyBtn && !locked) {
    copyBtn.addEventListener('click', () => {
      navigator.clipboard.writeText(result.rewrite);
      copyBtn.textContent = 'Copied';
      copyBtn.classList.add('copied');
      setTimeout(() => { copyBtn.textContent = 'Copy rewrite'; copyBtn.classList.remove('copied'); }, 1500);
    });
  }
}

async function runScoreCheck() {
  const text = textInput.value.trim();
  if (!text) { textInput.focus(); return; }
  if (text.length > MAX_CHARS) { return; }

  stepInput.classList.add('hidden');
  stepLoading.classList.remove('hidden');
  stepResults.classList.add('hidden');

  try {
    const res = await fetch('/tools/score-checker/generate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text, platform: selectedPlatform })
    });

    stepLoading.classList.add('hidden');
    stepResults.classList.remove('hidden');

    if (res.status === 402) {
      gateBar.classList.remove('hidden');
      againRow.classList.add('hidden');
      resultsContent.innerHTML = '<div style="color:var(--slate); font-size:14px; padding:10px 0 20px;">Your free checks for today are used up — unlock more below.</div>';
      return;
    }

    if (!res.ok) throw new Error('generation_failed');

    const data = await res.json();
    gateBar.classList.add('hidden');
    againRow.classList.remove('hidden');
    renderResults(data.result, false);

  } catch (err) {
    stepLoading.classList.add('hidden');
    stepResults.classList.remove('hidden');
    resultsContent.innerHTML = '<div style="color:var(--coral); font-size:14px; padding:20px 0;">Something went wrong scoring that. Try again in a moment.</div>';
  }
}

scoreBtn.addEventListener('click', runScoreCheck);

document.getElementById('again-btn').addEventListener('click', () => {
  stepResults.classList.add('hidden');
  stepInput.classList.remove('hidden');
  textInput.value = '';
  charCount.textContent = `0 / ${MAX_CHARS}`;
  textInput.focus();
});

document.getElementById('gate-submit').addEventListener('click', async () => {
  const email = document.getElementById('gate-email').value.trim();
  const msg = document.getElementById('gate-msg');
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
      body: JSON.stringify({ email, tool: toolKey })
    });

    if (res.ok) {
      msg.textContent = 'Unlocked. Try checking again.';
      msg.classList.add('success');
      setTimeout(() => {
        gateBar.classList.add('hidden');
        stepResults.classList.add('hidden');
        stepInput.classList.remove('hidden');
      }, 1200);
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
