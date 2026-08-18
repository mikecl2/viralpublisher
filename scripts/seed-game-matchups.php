<?php
/**
 * scripts/seed-game-matchups.php
 * Run: php scripts/seed-game-matchups.php
 *
 * Seeds the "Would This Go Viral?" matchup pool. These are original
 * illustrative pairs written to demonstrate real hook-writing principles —
 * not claims about specific real posts or their actual performance, since
 * we can't verify or attribute real social data here. The explanations
 * teach the same taxonomy used in the Hook Generator/Score Checker so the
 * whole site shares one vocabulary.
 *
 * Idempotent by default: if matchups already exist, it skips rather than
 * duplicating. Pass --force to wipe and reseed.
 */

require_once __DIR__ . '/../lib/db.php';

$db = get_db();
$force = in_array('--force', $argv ?? [], true);

$existingCount = (int) $db->query('SELECT COUNT(*) FROM game_matchups')->fetchColumn();

if ($existingCount > 0 && !$force) {
    echo "Matchups already seeded ({$existingCount} present). Run with --force to wipe and reseed.\n";
    exit;
}

if ($force) {
    $db->exec('DELETE FROM game_plays'); // FK depends on matchups, clear first
    $db->exec('DELETE FROM game_matchups');
    echo "Cleared existing matchups and plays.\n";
}

$matchups = [
    [
        'hook_a' => "Here's how to fix your morning routine.",
        'hook_a_type' => 'Explainer',
        'hook_b' => "Your morning routine isn't broken. Your alarm time is.",
        'hook_b_type' => 'Contrarian',
        'winner' => 'b',
        'explanation' => "B reframes a common belief instead of just offering advice — a Contrarian angle creates a small jolt of disagreement that a flat Explainer opening doesn't.",
        'category' => 'productivity',
    ],
    [
        'hook_a' => "3 books that changed how I think about money.",
        'hook_a_type' => 'Numbered List',
        'hook_b' => "I lied about being bad with money for 6 years. Here's what fixed it.",
        'hook_b_type' => 'Confession',
        'winner' => 'b',
        'explanation' => "A Confession creates real emotional stakes and curiosity about the fix; a list promise is useful but doesn't pull as hard on its own.",
        'category' => 'finance',
    ],
    [
        'hook_a' => "Stretching before a run doesn't do what you think.",
        'hook_a_type' => 'Blunt Warning',
        'hook_b' => "5 stretches for better running form.",
        'hook_b_type' => 'Numbered List',
        'winner' => 'a',
        'explanation' => "A challenges an assumption the viewer already holds, which creates a curiosity gap a straightforward list can't match.",
        'category' => 'fitness',
    ],
    [
        'hook_a' => "POV: you finally understand why your plants keep dying.",
        'hook_a_type' => 'POV',
        'hook_b' => "Common mistakes people make when watering houseplants.",
        'hook_b_type' => 'Explainer',
        'winner' => 'a',
        'explanation' => "POV places the viewer inside a specific emotional moment (relief, recognition) rather than describing information at a distance.",
        'category' => 'home',
    ],
    [
        'hook_a' => "Nobody explains why your resume isn't getting replies.",
        'hook_a_type' => 'Curiosity Gap',
        'hook_b' => "How to write a resume that gets replies.",
        'hook_b_type' => 'Explainer',
        'winner' => 'a',
        'explanation' => "The Curiosity Gap withholds the reason, creating a pull to keep watching; the Explainer version gives away the whole premise up front.",
        'category' => 'career',
    ],
    [
        'hook_a' => "I tried the 75 Hard challenge for 75 days.",
        'hook_a_type' => 'Storytime',
        'hook_b' => "Stop starting 75 Hard until you watch this.",
        'hook_b_type' => 'Ultimatum',
        'winner' => 'b',
        'explanation' => "The Ultimatum creates urgency and implies a mistake the viewer is about to make — sharper than a neutral recap opening.",
        'category' => 'fitness',
    ],
    [
        'hook_a' => "This is the fastest way to learn a language. No fluff.",
        'hook_a_type' => 'Direct Claim',
        'hook_b' => "Duolingo is quietly ruining how you learn languages.",
        'hook_b_type' => 'Provocation',
        'winner' => 'b',
        'explanation' => "Naming something specific and popular the viewer already uses creates instant relevance and mild controversy that a generic claim doesn't.",
        'category' => 'education',
    ],
    [
        'hook_a' => "Bet you can't watch this and not rethink your coffee order.",
        'hook_a_type' => 'Challenge',
        'hook_b' => "What's actually in your coffee shop order.",
        'hook_b_type' => 'Question Hook',
        'winner' => 'a',
        'explanation' => "A direct dare creates a small ego stake (prove me wrong) that pulls harder than an open question with no personal challenge attached.",
        'category' => 'food',
    ],
    [
        'hook_a' => "I was doing laundry completely wrong for 10 years. Nobody told me.",
        'hook_a_type' => 'Confession',
        'hook_b' => "Laundry tips that will save you time and money.",
        'hook_b_type' => 'Direct Claim',
        'winner' => 'a',
        'explanation' => "The specific timeframe and disarming admission make the Confession feel personal and credible in a way a generic benefit claim doesn't.",
        'category' => 'home',
    ],
    [
        'hook_a' => "Unpopular opinion: most productivity advice is designed to keep you busy, not effective.",
        'hook_a_type' => 'Hot Take',
        'hook_b' => "How to actually be more productive at work.",
        'hook_b_type' => 'Explainer',
        'winner' => 'a',
        'explanation' => "A Hot Take signals the video will say something the algorithm-fed version of this topic usually doesn't — that novelty is the pull.",
        'category' => 'productivity',
    ],
    [
        'hook_a' => "The 30-second mark is where everyone gives up on cold showers. Here's what's on the other side.",
        'hook_a_type' => 'Curiosity Gap',
        'hook_b' => "Why you should take cold showers every day.",
        'hook_b_type' => 'Direct Claim',
        'winner' => 'a',
        'explanation' => "Naming a specific, verifiable detail (the 30-second mark) makes the claim feel earned rather than generic advice-giving.",
        'category' => 'wellness',
    ],
    [
        'hook_a' => "How I organize my week using time blocking.",
        'hook_a_type' => 'Explainer',
        'hook_b' => "My calendar used to run my life. Now I run 3 businesses off one page.",
        'hook_b_type' => 'Before/After',
        'winner' => 'b',
        'explanation' => "Before/After implies a real transformation with a concrete before-state most viewers recognize in themselves — more emotionally specific than a method description.",
        'category' => 'productivity',
    ],
    [
        'hook_a' => "If you're still manually tracking expenses in a spreadsheet, watch this.",
        'hook_a_type' => 'Callout',
        'hook_b' => "5 apps to help you track your spending.",
        'hook_b_type' => 'Numbered List',
        'winner' => 'a',
        'explanation' => "The Callout directly names the viewer's current (outdated-feeling) behavior, which creates a personal stake a generic app list doesn't.",
        'category' => 'finance',
    ],
    [
        'hook_a' => "Why does nobody explain what actually happens to your body during a fast?",
        'hook_a_type' => 'Question Hook',
        'hook_b' => "The stages of intermittent fasting explained.",
        'hook_b_type' => 'Explainer',
        'winner' => 'a',
        'explanation' => "Framing it as a question implies an answer is coming that most explainers skip — the frustration in the phrasing itself creates curiosity.",
        'category' => 'wellness',
    ],
    [
        'hook_a' => "This one habit fixed my sleep more than anything else I tried.",
        'hook_a_type' => 'Superlative Claim',
        'hook_b' => "Sleep hygiene tips backed by research.",
        'hook_b_type' => 'Direct Claim',
        'winner' => 'a',
        'explanation' => "Naming it as the single best thing (versus everything else tried) sets up a curiosity gap around what the one thing actually is.",
        'category' => 'wellness',
    ],
    [
        'hook_a' => "I'm done pretending budgeting apps are the answer.",
        'hook_a_type' => 'Declaration',
        'hook_b' => "Why budgeting apps don't work for everyone.",
        'hook_b_type' => 'Explainer',
        'winner' => 'a',
        'explanation' => "A flat, confident declaration carries more personality and implies a stronger opinion is coming than a hedged explainer framing.",
        'category' => 'finance',
    ],
];

$insert = $db->prepare('
    INSERT INTO game_matchups (uuid, hook_a, hook_a_type, hook_b, hook_b_type, winner, explanation, category)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
');

foreach ($matchups as $m) {
    $insert->execute([
        generate_uuid_v4(),
        $m['hook_a'], $m['hook_a_type'],
        $m['hook_b'], $m['hook_b_type'],
        $m['winner'], $m['explanation'], $m['category'],
    ]);
}

echo 'Seeded ' . count($matchups) . " matchups.\n";
