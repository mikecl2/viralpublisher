<?php
/**
 * tools/game/answer.php
 * Takes a matchup_id and a guess of 'a' or 'b' (the client translates its
 * left/right click back to the real a/b using the mapping next-round.php
 * gave it), records the play, and returns the full reveal.
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/usage-gate.php'; // only used for FINGERPRINT_COOKIE constant, no gate applied

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$matchupId = (int) ($input['matchup_id'] ?? 0);
$guess = $input['guess'] ?? '';
$fingerprint = $_COOKIE[FINGERPRINT_COOKIE] ?? null; // optional here — game never gates on this

if (!$matchupId || !in_array($guess, ['a', 'b'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_fields']);
    exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT * FROM game_matchups WHERE id = ?');
$stmt->execute([$matchupId]);
$matchup = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$matchup) {
    http_response_code(404);
    echo json_encode(['error' => 'matchup_not_found']);
    exit;
}

$correct = ($guess === $matchup['winner']);

$insert = $db->prepare('
    INSERT INTO game_plays (matchup_id, fingerprint, guess, correct)
    VALUES (?, ?, ?, ?)
');
$insert->execute([$matchupId, $fingerprint, $guess, $correct ? 1 : 0]);

echo json_encode([
    'correct' => $correct,
    'winner' => $matchup['winner'],
    'winner_type' => $matchup['winner'] === 'a' ? $matchup['hook_a_type'] : $matchup['hook_b_type'],
    'loser_type' => $matchup['winner'] === 'a' ? $matchup['hook_b_type'] : $matchup['hook_a_type'],
    'explanation' => $matchup['explanation'],
]);
