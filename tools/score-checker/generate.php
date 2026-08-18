<?php
/**
 * tools/score-checker/generate.php
 * Same shared infrastructure as the Hook Generator — only the prompt shape,
 * the parsed JSON shape (object, not array), and the save call differ.
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/usage-gate.php';
require_once __DIR__ . '/../../api/ai-proxy.php';

header('Content-Type: application/json');

const TOOL_KEY = 'score_checker';
const MAX_INPUT_CHARS = 600; // a hook/caption/opening line, not a full script

$input = json_decode(file_get_contents('php://input'), true);
$text = trim($input['text'] ?? '');
$platform = $input['platform'] ?? 'general';
$fingerprint = $_COOKIE[FINGERPRINT_COOKIE] ?? null;
$ip = get_client_ip();

if (!$text || !$fingerprint) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_fields']);
    exit;
}

if (mb_strlen($text) > MAX_INPUT_CHARS) {
    http_response_code(400);
    echo json_encode(['error' => 'input_too_long', 'max_chars' => MAX_INPUT_CHARS]);
    exit;
}

$gate = check_usage_gate($fingerprint, TOOL_KEY, $ip);
if (!$gate['allowed']) {
    http_response_code(402);
    echo json_encode([
        'error' => 'limit_reached',
        'requires_email' => $gate['requires_email'],
    ]);
    exit;
}

try {
    $config = get_tool_config(TOOL_KEY);

    $userPrompt = "Platform: {$platform}\n\nContent to score:\n\"{$text}\"\n\n"
        . "Score this using the rubric in your system instructions. "
        . "Respond with ONLY a JSON object, no other text, in the exact shape described.";

    $rawResult = call_openrouter(
        $config['model'],
        [
            ['role' => 'system', 'content' => $config['system_prompt']],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        (float) $config['temperature'],
        (int) $config['max_tokens']
    );

    $result = extract_json_object($rawResult);

    if (!$result || !isset($result['score'])) {
        throw new RuntimeException('Model returned an unparseable score result');
    }

    $score = (int) $result['score'];
    $uuid = generate_uuid_v4();
    $leadId = get_linked_lead_id($fingerprint);

    save_score_check($uuid, $fingerprint, $leadId, $text, $platform, $score, $result, $config['model']);
    record_usage($fingerprint, TOOL_KEY, $ip);

    echo json_encode(['success' => true, 'result' => $result, 'uuid' => $uuid]);

} catch (Exception $e) {
    error_log('Score check failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'generation_failed']);
}

/**
 * Same defensive parsing approach as the Hook Generator's extract_json_array(),
 * but for a JSON object instead of an array — strips markdown fences and
 * grabs the { ... } span if the model wraps its answer in commentary.
 */
function extract_json_object(string $raw): ?array {
    $trimmed = trim($raw);
    $trimmed = preg_replace('/^```json\s*|```$/m', '', $trimmed);
    $trimmed = trim($trimmed);

    if (($trimmed[0] ?? '') !== '{') {
        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false) {
            return null;
        }
        $trimmed = substr($trimmed, $start, $end - $start + 1);
    }

    $decoded = json_decode($trimmed, true);
    return is_array($decoded) ? $decoded : null;
}
