<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/usage-gate.php';

$fingerprint = ensure_fingerprint(); // sets cookie if first visit, must run before any output
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hook Generator — viralpublisher.com</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap');

  :root{
    --ink:#10181B; --ink-soft:#182226; --paper:#F6F4EE; --signal:#C6FF3D;
    --coral:#FF6B4A; --slate:#8B96A3; --line:rgba(246,244,238,0.10);
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{ background:var(--ink); color:var(--paper); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }
  .wrap{ max-width:760px; margin:0 auto; padding:0 24px; }

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

  /* STEP 1 — form */
  .field-group{ margin-bottom:28px; }
  .field-label{ font-size:13px; color:var(--slate); margin-bottom:10px; display:block; }
  input[type=text]{
    width:100%; background:var(--ink-soft); border:1px solid var(--line); color:var(--paper);
    padding:15px 18px; font-size:16px; font-family:'Inter'; outline:none; border-radius:2px;
  }
  input[type=text]:focus{ border-color:var(--signal); }
  input[type=text]::placeholder{ color:var(--slate); }

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

  /* STEP 2 — loading */
  .skeleton-grid{ display:flex; flex-direction:column; gap:10px; padding:8px 0 40px; }
  .skeleton-card{
    height:64px; border:1px solid var(--line); border-radius:2px; background:var(--ink-soft);
    position:relative; overflow:hidden; opacity:0; animation:fadein 0.4s ease forwards;
  }
  .skeleton-card::after{
    content:''; position:absolute; inset:0;
    background:linear-gradient(90deg, transparent, rgba(198,255,61,0.06), transparent);
    animation:shimmer 1.4s infinite;
  }
  @keyframes shimmer{ from{transform:translateX(-100%)} to{transform:translateX(100%)} }
  @keyframes fadein{ to{opacity:1} }
  .loading-label{ font-family:'IBM Plex Mono'; font-size:12px; color:var(--slate); text-align:center; padding:20px 0; }

  /* STEP 3 — results */
  .results-list{ display:flex; flex-direction:column; gap:10px; padding:8px 0 24px; }
  .hook-card{
    border:1px solid var(--line); border-radius:2px; padding:18px 20px; background:var(--ink-soft);
    display:flex; justify-content:space-between; align-items:center; gap:16px;
    opacity:0; animation:cardin 0.35s ease forwards;
  }
  @keyframes cardin{ from{opacity:0; transform:translateY(6px);} to{opacity:1; transform:translateY(0);} }
  .hook-text{ font-size:15px; line-height:1.5; }
  .hook-tag{
    font-family:'IBM Plex Mono'; font-size:10px; color:var(--slate); text-transform:uppercase;
    letter-spacing:0.05em; margin-top:6px; display:block;
  }
  .copy-btn{
    flex-shrink:0; background:transparent; border:1px solid var(--line); color:var(--paper);
    padding:8px 14px; font-size:12px; border-radius:2px; cursor:pointer; font-family:'Inter';
  }
  .copy-btn:hover{ border-color:var(--signal); color:var(--signal); }
  .copy-btn:disabled{ opacity:0.3; cursor:not-allowed; }
  .copy-btn.copied{ border-color:var(--signal); color:var(--signal); }

  .again-row{ display:flex; justify-content:center; padding:12px 0 60px; }
  .text-btn{ background:none; border:none; color:var(--slate); font-size:14px; cursor:pointer; font-family:'Inter'; }
  .text-btn:hover{ color:var(--paper); }

  /* Email gate bar */
  .gate-bar{
    border:1px solid var(--signal); background:rgba(198,255,61,0.05); border-radius:2px;
    padding:24px; margin:8px 0 60px;
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
    <div class="eyebrow">02 / Generate</div>
    <h1>Hook Generator</h1>
    <p class="sub">Give it a topic. Get 10 scroll-stopping hooks built from proven structures — pattern interrupts, curiosity gaps, contrarian takes, and more.</p>
  </div>

  <!-- STEP 1: input -->
  <div id="step-input">
    <div class="field-group">
      <label class="field-label">What's the topic?</label>
      <input type="text" id="topic-input" placeholder="e.g. morning routines for busy parents" maxlength="140">
    </div>

    <div class="field-group">
      <label class="field-label">Platform</label>
      <div class="chip-row" id="platform-chips">
        <div class="chip selected" data-value="general">General</div>
        <div class="chip" data-value="tiktok">TikTok</div>
        <div class="chip" data-value="reels">Reels</div>
        <div class="chip" data-value="shorts">Shorts</div>
        <div class="chip" data-value="x">X</div>
        <div class="chip" data-value="linkedin">LinkedIn</div>
      </div>
    </div>

    <div class="field-group">
      <label class="field-label">Tone <span style="color:var(--slate)">(optional)</span></label>
      <div class="chip-row" id="tone-chips">
        <div class="chip" data-value="bold">Bold</div>
        <div class="chip" data-value="curious">Curious</div>
        <div class="chip" data-value="contrarian">Contrarian</div>
        <div class="chip" data-value="funny">Funny</div>
      </div>
    </div>

    <button class="primary-btn" id="generate-btn">Generate 10 hooks</button>
    <div class="limit-note">Free to try · no account needed</div>
  </div>

  <!-- STEP 2: loading -->
  <div id="step-loading" class="hidden">
    <div class="loading-label">Building your hooks...</div>
    <div class="skeleton-grid" id="skeleton-grid"></div>
  </div>

  <!-- STEP 3: results -->
  <div id="step-results" class="hidden">
    <div class="results-list" id="results-list"></div>

    <div id="gate-bar" class="gate-bar hidden">
      <h4>You've used today's free generations</h4>
      <p>Enter your email to unlock more — this also unlocks extended free access across every tool on the site, not just this one.</p>
      <div class="gate-form">
        <input type="text" id="gate-email" placeholder="you@email.com">
        <button id="gate-submit">Unlock →</button>
      </div>
      <div class="gate-msg" id="gate-msg"></div>
    </div>

    <div class="again-row" id="again-row">
      <button class="text-btn" id="again-btn">Generate again with a new topic</button>
    </div>
  </div>
</div>

<script>
const stepInput = document.getElementById('step-input');
const stepLoading = document.getElementById('step-loading');
const stepResults = document.getElementById('step-results');
const topicInput = document.getElementById('topic-input');
const generateBtn = document.getElementById('generate-btn');
const skeletonGrid = document.getElementById('skeleton-grid');
const resultsList = document.getElementById('results-list');
const gateBar = document.getElementById('gate-bar');
const againRow = document.getElementById('again-row');
const toolKey = 'hook_generator';

let selectedPlatform = 'general';
let selectedTone = null;

document.querySelectorAll('#platform-chips .chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('#platform-chips .chip').forEach(c => c.classList.remove('selected'));
    chip.classList.add('selected');
    selectedPlatform = chip.dataset.value;
  });
});

document.querySelectorAll('#tone-chips .chip').forEach(chip => {
  chip.addEventListener('click', () => {
    const alreadySelected = chip.classList.contains('selected');
    document.querySelectorAll('#tone-chips .chip').forEach(c => c.classList.remove('selected'));
    if (!alreadySelected) {
      chip.classList.add('selected');
      selectedTone = chip.dataset.value;
    } else {
      selectedTone = null;
    }
  });
});

function buildSkeletons() {
  skeletonGrid.innerHTML = '';
  for (let i = 0; i < 10; i++) {
    const el = document.createElement('div');
    el.className = 'skeleton-card';
    el.style.animationDelay = (i * 0.06) + 's';
    skeletonGrid.appendChild(el);
  }
}

function renderResults(hooks, locked) {
  resultsList.innerHTML = '';
  hooks.forEach((h, i) => {
    const card = document.createElement('div');
    card.className = 'hook-card';
    card.style.animationDelay = (i * 0.05) + 's';
    card.innerHTML = `
      <div>
        <div class="hook-text">${escapeHtml(h.hook)}</div>
        <span class="hook-tag">${escapeHtml(h.structure_type || '')}</span>
      </div>
      <button class="copy-btn" ${locked ? 'disabled' : ''}>Copy</button>
    `;
    const copyBtn = card.querySelector('.copy-btn');
    copyBtn.addEventListener('click', () => {
      navigator.clipboard.writeText(h.hook);
      copyBtn.textContent = 'Copied';
      copyBtn.classList.add('copied');
      setTimeout(() => { copyBtn.textContent = 'Copy'; copyBtn.classList.remove('copied'); }, 1500);
    });
    resultsList.appendChild(card);
  });
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

async function runGeneration() {
  const topic = topicInput.value.trim();
  if (!topic) { topicInput.focus(); return; }

  stepInput.classList.add('hidden');
  stepLoading.classList.remove('hidden');
  stepResults.classList.add('hidden');
  buildSkeletons();

  try {
    const res = await fetch('/tools/hook-generator/generate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ topic, platform: selectedPlatform, tone: selectedTone })
    });

    stepLoading.classList.add('hidden');
    stepResults.classList.remove('hidden');

    if (res.status === 402) {
      const body = await res.json();
      gateBar.classList.remove('hidden');
      againRow.classList.add('hidden');
      // Lock whatever's currently shown, if anything was shown before
      const lockedCopyBtns = resultsList.querySelectorAll('.copy-btn');
      lockedCopyBtns.forEach(btn => btn.disabled = true);
      if (resultsList.children.length === 0) {
        resultsList.innerHTML = '<div style="color:var(--slate); font-size:14px; padding:20px 0;">Your free generations for today are used up — unlock more below.</div>';
      }
      return;
    }

    if (!res.ok) throw new Error('generation_failed');

    const data = await res.json();
    gateBar.classList.add('hidden');
    againRow.classList.remove('hidden');
    renderResults(data.hooks, false);

  } catch (err) {
    stepLoading.classList.add('hidden');
    stepResults.classList.remove('hidden');
    resultsList.innerHTML = '<div style="color:var(--coral); font-size:14px; padding:20px 0;">Something went wrong generating your hooks. Try again in a moment.</div>';
  }
}

generateBtn.addEventListener('click', runGeneration);
topicInput.addEventListener('keydown', e => { if (e.key === 'Enter') runGeneration(); });

document.getElementById('again-btn').addEventListener('click', () => {
  stepResults.classList.add('hidden');
  stepInput.classList.remove('hidden');
  topicInput.value = '';
  topicInput.focus();
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
    const data = await res.json();

    if (res.ok) {
      msg.textContent = 'Unlocked. Try generating again.';
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
