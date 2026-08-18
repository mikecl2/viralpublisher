<?php
/**
 * tools/game/next-round.php
 * No usage gate, no AI call — this tool is permanently free. Only ever
 * returns the two hooks, never the winner or types, so there's nothing
 * to inspect in the network tab to cheat the reveal.
 */

require_once __DIR__ . '/../../lib/db.php';

header('Content-Type: application/json');

$db = get_db();

$excludeParam = $_GET['exclude'] ?? '';
$excludeIds = array_values(array_filter(array_map('intval', explode(',', $excludeParam))));

$totalCount = (int) $db->query('SELECT COUNT(*) FROM game_matchups')->fetchColumn();
if ($totalCount === 0) {
    http_response_code(503);
    echo json_encode(['error' => 'no_matchups_seeded']);
    exit;
}

// If the client has seen everything (or close to it), ignore excludes and
// let the cycle repeat rather than erroring out.
if (count($excludeIds) >= $totalCount) {
    $excludeIds = [];
}

if (!empty($excludeIds)) {
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
    $stmt = $db->prepare("
        SELECT id, uuid, hook_a, hook_b FROM game_matchups
        WHERE id NOT IN ({$placeholders})
        ORDER BY RANDOM() LIMIT 1
    ");
    $stmt->execute($excludeIds);
} else {
    $stmt = $db->query('SELECT id, uuid, hook_a, hook_b FROM game_matchups ORDER BY RANDOM() LIMIT 1');
}

$matchup = $stmt->fetch(PDO::FETCH_ASSOC);

// Randomize which side is A/B on screen so the correct answer isn't
// always in the same visual position across rounds.
$flip = (bool) random_int(0, 1);

echo json_encode([
    'matchup_id' => (int) $matchup['id'],
    'uuid' => $matchup['uuid'],
    'left' => $flip ? $matchup['hook_b'] : $matchup['hook_a'],
    'right' => $flip ? $matchup['hook_a'] : $matchup['hook_b'],
    'left_is' => $flip ? 'b' : 'a',
    'right_is' => $flip ? 'a' : 'b',
]);
