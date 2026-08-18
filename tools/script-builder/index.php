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
<title>Script Builder — viralpublisher.com</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap');

  :root{
    --ink:#10181B; --ink-soft:#182226; --paper:#F6F4EE; --signal:#C6FF3D;
    --coral:#FF6B4A; --slate:#8B96A3; --line:rgba(246,244,238,0.10);
    --paper-ink:#2A241C;
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{ background:var(--ink); color:var(--paper); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }
  .wrap{ max-width:760px; margin:0 auto; padding:0 24px 60px; }

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
  .sub{ color:var(--slate); font-size:15px; margin-top:12px; max-width:480px; line-height:1.55; }

  .section-label{
    font-family:'IBM Plex Mono'; font-size:11px; text-transform:uppercase; letter-spacing:0.06em;
    color:var(--signal); margin:32px 0 14px;
  }
  .field-group{ margin-bottom:18px; }
  .field-label{ font-size:13px; color:var(--slate); margin-bottom:8px; display:block; }
  input[type=text], textarea{
    width:100%; background:var(--ink-soft); border:1px solid var(--line); color:var(--paper);
    padding:13px 16px; font-size:14px; font-family:'Inter'; outline:none; border-radius:2px; line-height:1.5;
  }
  input:focus, textarea:focus{ border-color:var(--signal); }
  input::placeholder, textarea::placeholder{ color:var(--slate); }
  textarea{ min-height:70px; resize:vertical; }
  .row2{ display:flex; gap:14px; }
  .row2 > div{ flex:1; }

  .chip-row{ display:flex; flex-wrap:wrap; gap:8px; }
  .chip{
    padding:9px 16px; border:1px solid var(--line); border-radius:20px; font-size:13px;
    color:var(--slate); cursor:pointer; transition:all 0.15s ease; background:transparent;
  }
  .chip:hover{ border-color:var(--slate); color:var(--paper); }
  .chip.selected{ border-color:var(--signal); color:var(--signal); background:rgba(198,255,61,0.06); }

  .step-block{
    border:1px solid var(--line); border-radius:2px; padding:16px; margin-bottom:12px; background:var(--ink-soft);
    position:relative;
  }
  .step-block-head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
  .step-num{ font-family:'IBM Plex Mono'; font-size:11px; color:var(--signal); }
  .remove-step{ background:none; border:none; color:var(--slate); font-size:12px; cursor:pointer; }
  .remove-step:hover{ color:var(--coral); }
  .step-block .field-group{ margin-bottom:10px; }
  .step-block .field-group:last-child{ margin-bottom:0; }

  .add-step-btn{
    background:transparent; border:1px dashed var(--line); color:var(--slate); width:100%;
    padding:12px; font-size:13px; cursor:pointer; border-radius:2px; margin-bottom:24px;
  }
  .add-step-btn:hover{ border-color:var(--signal); color:var(--signal); }

  .primary-btn{
    background:var(--signal); color:var(--ink); border:none; padding:16px 28px;
    font-weight:600; font-size:15px; cursor:pointer; border-radius:2px; width:100%;
    margin-top:8px; transition:opacity 0.15s ease;
  }
  .primary-btn:hover{ opacity:0.9; }
  .primary-btn:disabled{ opacity:0.4; cursor:not-allowed; }
  .limit-note{ font-family:'IBM Plex Mono'; font-size:11px; color:var(--slate); margin-top:14px; text-align:center; }
  .form-error{ color:var(--coral); font-size:13px; margin-top:10px; text-align:center; }

  /* LOADING — tally light, callback to the film-production identity */
  .loading-wrap{ padding:70px 0; display:flex; flex-direction:column; align-items:center; gap:18px; }
  .tally{ display:flex; align-items:center; gap:10px; }
  .tally-dot{ width:12px; height:12px; border-radius:50%; background:var(--coral); animation:tally-pulse 1s infinite; }
  @keyframes tally-pulse{ 0%,100%{opacity:1;} 50%{opacity:0.3;} }
  .tally-label{ font-family:'IBM Plex Mono'; font-size:12px; color:var(--slate); letter-spacing:0.04em; text-transform:uppercase; }

  /* RESULTS — paper script card, the signature visual moment */
  .script-card{
    background:var(--paper); color:var(--paper-ink); border-radius:2px; padding:36px 32px;
    font-family:'IBM Plex Mono'; margin-bottom:20px;
  }
  .script-section{ margin-bottom:26px; opacity:0; animation:sectionin 0.4s ease forwards; }
  @keyframes sectionin{ from{opacity:0; transform:translateY(6px);} to{opacity:1; transform:translateY(0);} }
  .script-section:last-child{ margin-bottom:0; }
  .script-section-title{
    font-weight:600; font-size:13px; text-transform:uppercase; letter-spacing:0.04em;
    border-bottom:1px solid rgba(42,36,28,0.15); padding-bottom:8px; margin-bottom:10px;
  }
  .cue-line{ font-size:13px; line-height:1.6; margin-bottom:6px; }
  .cue-label{ font-weight:600; }
  .save-bait-block{
    background:rgba(42,36,28,0.06); border-left:3px solid var(--coral); padding:10px 14px;
    margin-bottom:10px; font-size:12px; line-height:1.5;
  }
  .step-cue{ margin-bottom:14px; padding-left:14px; border-left:2px solid rgba(42,36,28,0.12); }
  .step-cue:last-child{ margin-bottom:0; }
  .step-cue-name{ font-weight:600; font-size:13px; margin-bottom:4px; }

  .locked-section{
    display:flex; align-items:center; gap:10px; padding:12px 0; opacity:0.5;
    font-size:13px; border-bottom:1px solid rgba(42,36,28,0.1);
  }
  .locked-section:last-child{ border-bottom:none; }
  .lock-icon{ font-size:14px; }

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

  .result-actions{ display:flex; gap:10px; margin-bottom:24px; }
  .copy-btn{
    background:transparent; border:1px solid var(--line); color:var(--paper);
    padding:10px 18px; font-size:13px; border-radius:2px; cursor:pointer; font-family:'Inter'; flex:1;
  }
  .copy-btn:hover{ border-color:var(--signal); color:var(--signal); }
  .copy-btn.copied{ border-color:var(--signal); color:var(--signal); }

  .again-row{ display:flex; justify-content:center; padding:8px 0 20px; }
  .text-btn{ background:none; border:none; color:var(--slate); font-size:14px; cursor:pointer; font-family:'Inter'; }
  .text-btn:hover{ color:var(--paper); }

  .hidden{ display:none; }

  @media(max-width:600px){
    h1{ font-size:27px; }
    .row2{ flex-direction:column; }
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
    <div class="eyebrow">04 / Script</div>
    <h1>Script Builder</h1>
    <p class="sub">Answer a short call sheet. Get a full 7-part "Yapping Reel" script — hook, proof, steps, and CTA — formatted the way you'd actually shoot it.</p>
  </div>

  <!-- STEP 1: call sheet form -->
  <div id="step-input">
    <div class="section-label">The Hook</div>
    <div class="row2">
      <div class="field-group">
        <label class="field-label">Hot topic A</label>
        <input type="text" id="topic-a" placeholder="e.g. cold plunges">
      </div>
      <div class="field-group">
        <label class="field-label">Hot topic B</label>
        <input type="text" id="topic-b" placeholder="e.g. burnout recovery">
      </div>
    </div>

    <div class="section-label">Proof</div>
    <div class="field-group">
      <label class="field-label">What proves you know this? (credential, result, experience)</label>
      <textarea id="proof" placeholder="e.g. I've coached 200+ clients through this exact reset"></textarea>
    </div>

    <div class="section-label">Benefits &amp; Objections</div>
    <div class="field-group">
      <label class="field-label">Benefits (one per line)</label>
      <textarea id="benefits" placeholder="Better sleep&#10;More energy by 2pm&#10;No more afternoon crash"></textarea>
    </div>
    <div class="field-group">
      <label class="field-label">Pain points to defuse (one per line)</label>
      <textarea id="pain-points" placeholder="Sounds like it takes forever&#10;I've tried things like this before"></textarea>
    </div>

    <div class="section-label">Named Steps <span style="color:var(--slate); text-transform:none;">(2–6)</span></div>
    <div id="step-fields"></div>
    <button class="add-step-btn" id="add-step-btn" type="button">+ Add another step</button>

    <div class="section-label">Save Bait &amp; CTA</div>
    <div class="field-group">
      <label class="field-label">What goes on the "save this" graphic?</label>
      <textarea id="save-bait" placeholder="e.g. all 5 steps listed with icons"></textarea>
    </div>
    <div class="row2">
      <div class="field-group">
        <label class="field-label">CTA keyword</label>
        <input type="text" id="cta-keyword" placeholder="e.g. RESET">
      </div>
      <div class="field-group">
        <label class="field-label">Offer/bonus details <span style="color:var(--slate)">(optional)</span></label>
        <input type="text" id="offer-details" placeholder="e.g. free this week only">
      </div>
    </div>

    <div class="section-label">Platform</div>
    <div class="chip-row" id="platform-chips">
      <div class="chip selected" data-value="reels">Reels</div>
      <div class="chip" data-value="tiktok">TikTok</div>
      <div class="chip" data-value="shorts">Shorts</div>
    </div>

    <button class="primary-btn" id="build-btn">Write my script</button>
    <div class="form-error hidden" id="form-error"></div>
    <div class="limit-note">Free to try · no account needed</div>
  </div>

  <!-- STEP 2: loading -->
  <div id="step-loading" class="hidden">
    <div class="loading-wrap">
      <div class="tally"><div class="tally-dot"></div><div class="tally-label">Recording</div></div>
      <div class="tally-label" style="text-transform:none; letter-spacing:normal;">Writing your 7-section script...</div>
    </div>
  </div>

  <!-- STEP 3: results -->
  <div id="step-results" class="hidden">
    <div class="script-card" id="script-card"></div>

    <div id="gate-bar" class="gate-bar hidden">
      <h4>You're seeing the free preview</h4>
      <p>Section 1 is unlocked. Enter your email to reveal the full script — this also unlocks extended free access across every tool on the site.</p>
      <div class="gate-form">
        <input type="text" id="gate-email" placeholder="you@email.com">
        <button id="gate-submit">Unlock full script →</button>
      </div>
      <div class="gate-msg" id="gate-msg"></div>
    </div>

    <div class="result-actions hidden" id="result-actions">
      <button class="copy-btn" id="copy-script-btn">Copy full script</button>
    </div>

    <div class="again-row">
      <button class="text-btn" id="again-btn">Start a new script</button>
    </div>
  </div>
</div>

<script>
const stepInput = document.getElementById('step-input');
const stepLoading = document.getElementById('step-loading');
const stepResults = document.getElementById('step-results');
const stepFields = document.getElementById('step-fields');
const addStepBtn = document.getElementById('add-step-btn');
const buildBtn = document.getElementById('build-btn');
const formError = document.getElementById('form-error');
const scriptCard = document.getElementById('script-card');
const gateBar = document.getElementById('gate-bar');
const resultActions = document.getElementById('result-actions');
const toolKey = 'script_builder';
const MIN_STEPS = 2;
const MAX_STEPS = 6;

let selectedPlatform = 'reels';
let lastFullScriptText = '';

document.querySelectorAll('#platform-chips .chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('#platform-chips .chip').forEach(c => c.classList.remove('selected'));
    chip.classList.add('selected');
    selectedPlatform = chip.dataset.value;
  });
});

function addStepBlock() {
  const count = stepFields.children.length;
  if (count >= MAX_STEPS) return;

  const block = document.createElement('div');
  block.className = 'step-block';
  block.innerHTML = `
    <div class="step-block-head">
      <span class="step-num">STEP ${count + 1}</span>
      <button type="button" class="remove-step">Remove</button>
    </div>
    <div class="field-group">
      <label class="field-label">Step name</label>
      <input type="text" class="step-name" placeholder="e.g. The 4-7-8 Reset">
    </div>
    <div class="field-group">
      <label class="field-label">What it does</label>
      <input type="text" class="step-does" placeholder="e.g. resets your nervous system in under a minute">
    </div>
    <div class="field-group">
      <label class="field-label">Main lesson <span style="color:var(--slate)">(optional)</span></label>
      <input type="text" class="step-lesson" placeholder="Leave blank to infer">
    </div>
  `;
  block.querySelector('.remove-step').addEventListener('click', () => {
    if (stepFields.children.length > MIN_STEPS) {
      block.remove();
      renumberSteps();
    }
  });
  stepFields.appendChild(block);
}

function renumberSteps() {
  stepFields.querySelectorAll('.step-block').forEach((block, i) => {
    block.querySelector('.step-num').textContent = `STEP ${i + 1}`;
  });
}

addStepBtn.addEventListener('click', addStepBlock);
for (let i = 0; i < 3; i++) addStepBlock(); // default 3 step slots

function collectSteps() {
  return Array.from(stepFields.querySelectorAll('.step-block')).map(block => ({
    name: block.querySelector('.step-name').value.trim(),
    does: block.querySelector('.step-does').value.trim(),
    lesson: block.querySelector('.step-lesson').value.trim(),
  })).filter(s => s.name);
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}

function renderCueLines(section) {
  let html = '';
  if (section.save_bait_screen) {
    html += `<div class="save-bait-block">SAVE BAIT SCREEN: ${escapeHtml(section.save_bait_screen)}</div>`;
  }
  if (section.on_screen) html += `<div class="cue-line"><span class="cue-label">ON SCREEN:</span> ${escapeHtml(section.on_screen)}</div>`;
  if (section.spoken) html += `<div class="cue-line"><span class="cue-label">SPOKEN:</span> ${escapeHtml(section.spoken)}</div>`;
  if (section.camera_notes) html += `<div class="cue-line"><span class="cue-label">CAMERA NOTES:</span> ${escapeHtml(section.camera_notes)}</div>`;
  return html;
}

function renderResults(sections, locked) {
  let html = '';
  let plainText = '';

  sections.forEach((section, i) => {
    if (section.locked) {
      html += `<div class="locked-section" style="animation-delay:${i * 0.05}s"><span class="lock-icon">🔒</span> Section ${section.number} — ${escapeHtml(section.title)} (unlock with email)</div>`;
      return;
    }

    html += `<div class="script-section" style="animation-delay:${i * 0.05}s">
      <div class="script-section-title">Section ${section.number} — ${escapeHtml(section.title)}</div>`;

    plainText += `SECTION ${section.number} — ${section.title}\n`;

    if (section.steps && Array.isArray(section.steps)) {
      section.steps.forEach(step => {
        html += `<div class="step-cue">
          <div class="step-cue-name">Step ${step.step_number}: ${escapeHtml(step.name)}</div>
          ${renderCueLines(step)}
        </div>`;
        plainText += `\nStep ${step.step_number}: ${step.name}\nON SCREEN: ${step.on_screen || ''}\nSPOKEN: ${step.spoken || ''}\nCAMERA NOTES: ${step.camera_notes || ''}\n`;
      });
    } else {
      html += renderCueLines(section);
      if (section.save_bait_screen) plainText += `SAVE BAIT SCREEN: ${section.save_bait_screen}\n`;
      plainText += `ON SCREEN: ${section.on_screen || ''}\nSPOKEN: ${section.spoken || ''}\nCAMERA NOTES: ${section.camera_notes || ''}\n`;
    }

    html += `</div>`;
    plainText += `\n`;
  });

  scriptCard.innerHTML = html;
  lastFullScriptText = plainText.trim();

  gateBar.classList.toggle('hidden', !locked);
  resultActions.classList.toggle('hidden', locked);
}

async function runScriptBuild() {
  formError.classList.add('hidden');

  const topicA = document.getElementById('topic-a').value.trim();
  const topicB = document.getElementById('topic-b').value.trim();
  const proof = document.getElementById('proof').value.trim();
  const benefits = document.getElementById('benefits').value.trim();
  const painPoints = document.getElementById('pain-points').value.trim();
  const saveBait = document.getElementById('save-bait').value.trim();
  const ctaKeyword = document.getElementById('cta-keyword').value.trim();
  const offerDetails = document.getElementById('offer-details').value.trim();
  const steps = collectSteps();

  if (!topicA || !topicB || !proof || !ctaKeyword) {
    formError.textContent = 'Please fill in both hot topics, your proof, and a CTA keyword.';
    formError.classList.remove('hidden');
    return;
  }
  if (steps.length < MIN_STEPS) {
    formError.textContent = `Please name at least ${MIN_STEPS} steps.`;
    formError.classList.remove('hidden');
    return;
  }

  stepInput.classList.add('hidden');
  stepLoading.classList.remove('hidden');
  stepResults.classList.add('hidden');

  try {
    const res = await fetch('/tools/script-builder/generate.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        topic_a: topicA, topic_b: topicB, proof, benefits, pain_points: painPoints,
        save_bait: saveBait, cta_keyword: ctaKeyword, offer_details: offerDetails,
        platform: selectedPlatform, steps,
      })
    });

    stepLoading.classList.add('hidden');
    stepResults.classList.remove('hidden');

    if (res.status === 402) {
      scriptCard.innerHTML = '<div style="font-size:13px; color:var(--paper-ink);">Your free scripts for today are used up — unlock more below.</div>';
      gateBar.classList.remove('hidden');
      resultActions.classList.add('hidden');
      return;
    }

    if (!res.ok) throw new Error('generation_failed');

    const data = await res.json();
    renderResults(data.sections, data.locked);

  } catch (err) {
    stepLoading.classList.add('hidden');
    stepResults.classList.remove('hidden');
    scriptCard.innerHTML = '<div style="font-size:13px; color:var(--coral);">Something went wrong writing that script. Try again in a moment.</div>';
  }
}

buildBtn.addEventListener('click', runScriptBuild);

document.getElementById('copy-script-btn').addEventListener('click', () => {
  navigator.clipboard.writeText(lastFullScriptText);
  const btn = document.getElementById('copy-script-btn');
  btn.textContent = 'Copied';
  btn.classList.add('copied');
  setTimeout(() => { btn.textContent = 'Copy full script'; btn.classList.remove('copied'); }, 1500);
});

document.getElementById('again-btn').addEventListener('click', () => {
  stepResults.classList.add('hidden');
  stepInput.classList.remove('hidden');
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
      msg.textContent = 'Unlocked. Generating your full script again...';
      msg.classList.add('success');
      setTimeout(() => { runScriptBuild(); }, 1000);
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
