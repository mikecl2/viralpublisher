<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/usage-gate.php';

$fingerprint = ensure_fingerprint(); // harmless to set here too, keeps cookie consistent site-wide
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Would This Go Viral? — viralpublisher.com</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap');

  :root{
    --ink:#10181B; --ink-soft:#182226; --paper:#F6F4EE; --signal:#C6FF3D;
    --coral:#FF6B4A; --slate:#8B96A3; --line:rgba(246,244,238,0.10);
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{ background:var(--ink); color:var(--paper); font-family:'Inter',sans-serif; -webkit-font-smoothing:antialiased; }
  a{ color:inherit; text-decoration:none; }
  .wrap{ max-width:640px; margin:0 auto; padding:0 24px 60px; }

  nav{ display:flex; justify-content:space-between; align-items:center; padding:26px 0; border-bottom:1px solid var(--line); }
  .logo{ font-family:'Space Grotesk'; font-weight:700; font-size:17px; }
  .logo span{ color:var(--signal); }
  .back{ font-size:13px; color:var(--slate); }

  .head{ padding:44px 0 8px; text-align:center; }
  .eyebrow{
    font-family:'IBM Plex Mono'; font-size:11px; letter-spacing:0.08em; text-transform:uppercase;
    color:var(--signal); margin-bottom:14px;
  }
  h1{ font-family:'Space Grotesk'; font-weight:600; font-size:30px; letter-spacing:-0.01em; }
  .sub{ color:var(--slate); font-size:14px; margin-top:10px; }

  .streak-row{
    display:flex; justify-content:center; gap:24px; padding:20px 0 8px;
    font-family:'IBM Plex Mono'; font-size:12px; color:var(--slate);
  }
  .streak-row b{ color:var(--signal); font-size:14px; }

  .matchup{ padding:36px 0; }
  .vs-label{
    text-align:center; font-family:'IBM Plex Mono'; font-size:11px; color:var(--slate);
    text-transform:uppercase; letter-spacing:0.1em; margin-bottom:18px;
  }

  .option{
    border:1px solid var(--line); border-radius:2px; padding:24px 22px; margin-bottom:14px;
    cursor:pointer; transition:all 0.15s ease; background:var(--ink-soft); position:relative;
  }
  .option:hover{ border-color:var(--signal); }
  .option-label{
    font-family:'IBM Plex Mono'; font-size:10px; color:var(--slate); text-transform:uppercase;
    letter-spacing:0.06em; margin-bottom:10px;
  }
  .option-text{ font-size:17px; line-height:1.5; font-weight:500; }

  .option.correct{ border-color:var(--signal); background:rgba(198,255,61,0.06); }
  .option.incorrect{ border-color:var(--coral); background:rgba(255,107,74,0.06); opacity:0.7; }
  .option.disabled{ cursor:default; }
  .option.disabled:hover{ border-color:var(--line); }
  .option.correct:hover, .option.incorrect:hover{ border-color:inherit; }

  .result-tag{
    font-family:'IBM Plex Mono'; font-size:10px; text-transform:uppercase; letter-spacing:0.05em;
    padding:3px 10px; border-radius:20px; display:inline-block; margin-top:12px;
  }
  .result-tag.win{ background:rgba(198,255,61,0.12); color:var(--signal); }
  .result-tag.lose{ background:rgba(255,107,74,0.12); color:var(--coral); }

  .loading-row{ text-align:center; padding:60px 0; color:var(--slate); font-family:'IBM Plex Mono'; font-size:12px; }

  .reveal-box{
    border:1px solid var(--line); border-radius:2px; padding:22px; background:var(--ink-soft);
    margin-top:8px; opacity:0; animation:revealin 0.35s ease forwards;
  }
  @keyframes revealin{ from{opacity:0; transform:translateY(6px);} to{opacity:1; transform:translateY(0);} }
  .reveal-verdict{ font-family:'Space Grotesk'; font-weight:600; font-size:18px; margin-bottom:8px; }
  .reveal-verdict.win{ color:var(--signal); }
  .reveal-verdict.lose{ color:var(--coral); }
  .reveal-text{ font-size:14px; color:var(--paper); line-height:1.55; }

  .action-row{ display:flex; gap:10px; margin-top:20px; }
  .primary-btn{
    background:var(--signal); color:var(--ink); border:none; padding:15px 24px;
    font-weight:600; font-size:14px; cursor:pointer; border-radius:2px; flex:1;
  }
  .primary-btn:hover{ opacity:0.9; }
  .share-btn{
    background:transparent; border:1px solid var(--line); color:var(--paper);
    padding:15px 20px; font-size:14px; cursor:pointer; border-radius:2px; font-family:'Inter';
  }
  .share-btn:hover{ border-color:var(--signal); color:var(--signal); }
  .share-btn.copied{ border-color:var(--signal); color:var(--signal); }

  .cross-promo{
    text-align:center; padding:32px 0 0; font-size:13px; color:var(--slate);
  }
  .cross-promo a{ color:var(--signal); }

  .hidden{ display:none; }

  @media(max-width:600px){
    h1{ font-size:24px; }
    .option-text{ font-size:15px; }
    .action-row{ flex-direction:column; }
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
    <div class="eyebrow">03 / Play</div>
    <h1>Would This Go Viral?</h1>
    <p class="sub">Two hooks, same topic. Pick the one that pulls harder.</p>
  </div>

  <div class="streak-row">
    <div>Round <b id="round-count">1</b></div>
    <div>Score <b id="score-count">0</b>/<span id="attempted-count">0</span></div>
  </div>

  <div id="loading-row" class="loading-row">Loading a round...</div>

  <div id="matchup" class="matchup hidden">
    <div class="vs-label">Which one wins?</div>
    <div class="option" id="option-left" data-side="left">
      <div class="option-label">Option A</div>
      <div class="option-text" id="text-left"></div>
    </div>
    <div class="option" id="option-right" data-side="right">
      <div class="option-label">Option B</div>
      <div class="option-text" id="text-right"></div>
    </div>

    <div id="reveal-box" class="reveal-box hidden">
      <div class="reveal-verdict" id="reveal-verdict"></div>
      <div class="reveal-text" id="reveal-text"></div>
      <div class="action-row">
        <button class="primary-btn" id="next-btn">Next round →</button>
        <button class="share-btn" id="share-btn">Copy score</button>
      </div>
    </div>
  </div>

  <div class="cross-promo">
    Want your own hooks scored like this? Try the <a href="/tools/score-checker/">Virality Score Checker</a>.
  </div>
</div>

<script>
const loadingRow = document.getElementById('loading-row');
const matchupBox = document.getElementById('matchup');
const optionLeft = document.getElementById('option-left');
const optionRight = document.getElementById('option-right');
const textLeft = document.getElementById('text-left');
const textRight = document.getElementById('text-right');
const revealBox = document.getElementById('reveal-box');
const revealVerdict = document.getElementById('reveal-verdict');
const revealText = document.getElementById('reveal-text');
const roundCount = document.getElementById('round-count');
const scoreCount = document.getElementById('score-count');
const attemptedCount = document.getElementById('attempted-count');
const nextBtn = document.getElementById('next-btn');
const shareBtn = document.getElementById('share-btn');

let round = 0;
let score = 0;
let attempted = 0;
let seenIds = [];
let currentMatchup = null; // { matchup_id, left_is, right_is }
let answered = false;

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}

async function loadRound() {
  answered = false;
  matchupBox.classList.add('hidden');
  revealBox.classList.add('hidden');
  loadingRow.classList.remove('hidden');
  loadingRow.textContent = 'Loading a round...';

  [optionLeft, optionRight].forEach(el => {
    el.classList.remove('correct', 'incorrect', 'disabled');
  });

  try {
    const res = await fetch(`/tools/game/next-round.php?exclude=${seenIds.join(',')}`);
    if (!res.ok) throw new Error('load_failed');
    const data = await res.json();

    currentMatchup = data;
    textLeft.textContent = data.left;
    textRight.textContent = data.right;
    seenIds.push(data.matchup_id);

    round += 1;
    roundCount.textContent = round;

    loadingRow.classList.add('hidden');
    matchupBox.classList.remove('hidden');
  } catch (err) {
    loadingRow.textContent = 'Something went wrong loading a round. Refresh to try again.';
  }
}

async function submitGuess(side) {
  if (answered || !currentMatchup) return;
  answered = true;

  const guess = side === 'left' ? currentMatchup.left_is : currentMatchup.right_is;

  try {
    const res = await fetch('/tools/game/answer.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ matchup_id: currentMatchup.matchup_id, guess })
    });
    if (!res.ok) throw new Error('answer_failed');
    const data = await res.json();

    attempted += 1;
    if (data.correct) score += 1;
    scoreCount.textContent = score;
    attemptedCount.textContent = attempted;

    const winningSide = data.winner === currentMatchup.left_is ? 'left' : 'right';
    const losingSide = winningSide === 'left' ? 'right' : 'left';
    const winEl = winningSide === 'left' ? optionLeft : optionRight;
    const loseEl = losingSide === 'left' ? optionLeft : optionRight;

    winEl.classList.add('correct', 'disabled');
    loseEl.classList.add('incorrect', 'disabled');

    revealVerdict.textContent = data.correct ? "You called it." : "Not this time.";
    revealVerdict.className = 'reveal-verdict ' + (data.correct ? 'win' : 'lose');
    revealText.innerHTML = `<strong>${escapeHtml(data.winner_type)}</strong> beat <strong>${escapeHtml(data.loser_type)}</strong> here. ${escapeHtml(data.explanation)}`;

    revealBox.classList.remove('hidden');
  } catch (err) {
    revealVerdict.textContent = 'Something went wrong.';
    revealVerdict.className = 'reveal-verdict lose';
    revealText.textContent = 'Try the next round.';
    revealBox.classList.remove('hidden');
  }
}

optionLeft.addEventListener('click', () => submitGuess('left'));
optionRight.addEventListener('click', () => submitGuess('right'));
nextBtn.addEventListener('click', loadRound);

shareBtn.addEventListener('click', () => {
  const text = `I got ${score}/${attempted} on Viral Publisher's hook game — think you can beat me?\n\nviralpublisher.com/tools/game/`;
  navigator.clipboard.writeText(text);
  shareBtn.textContent = 'Copied!';
  shareBtn.classList.add('copied');
  setTimeout(() => { shareBtn.textContent = 'Copy score'; shareBtn.classList.remove('copied'); }, 1500);
});

loadRound();
</script>
</body>
</html>
