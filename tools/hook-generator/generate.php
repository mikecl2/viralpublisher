<?php
/**
 * tools/hook-generator/generate.php
 * The only tool-specific logic here is the prompt shape and the save call —
 * everything else (gating, usage recording, AI call) is shared infrastructure.
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/usage-gate.php';
require_once __DIR__ . '/../../api/ai-proxy.php';

header('Content-Type: application/json');

const TOOL_KEY = 'hook_generator';

$input = json_decode(file_get_contents('php://input'), true);
$topic = trim($input['topic'] ?? '');
$platform = $input['platform'] ?? 'general';
$tone = $input['tone'] ?? null;
$fingerprint = $_COOKIE[FINGERPRINT_COOKIE] ?? null;
$ip = get_client_ip();

if (!$topic || !$fingerprint) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_fields']);
    exit;
}

if (mb_strlen($topic) > 140) {
    http_response_code(400);
    echo json_encode(['error' => 'topic_too_long']);
    exit;
}

// Shared gate check — same function every tool calls before spending an AI call
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

    $userPrompt = "Topic: {$topic}\nPlatform: {$platform}\n"
        . ($tone ? "Tone: {$tone}\n" : "")
        . "\nGenerate exactly 10 hooks using the framework in your system instructions. "
        . "Respond with ONLY a JSON array, no other text, in this exact shape: "
        . '[{"hook": "...", "structure_type": "..."}, ...]';

    $rawResult = call_openrouter(
        $config['model'],
        [
            ['role' => 'system', 'content' => $config['system_prompt']],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        (float) $config['temperature'],
        (int) $config['max_tokens']
    );

    $hooks = extract_json_array($rawResult);

    if (!$hooks || count($hooks) === 0) {
        $preview = 'length=' . strlen($rawResult)
            . ' | head=' . substr($rawResult, 0, 150)
            . ' | tail=' . substr($rawResult, -150);
        throw new RuntimeException('Model returned no parseable hooks. ' . $preview);
    }

    $uuid = generate_uuid_v4();
    $leadId = get_linked_lead_id($fingerprint);

    save_hook_generation($uuid, $fingerprint, $leadId, $topic, $platform, $tone, $hooks, $config['model']);
    record_usage($fingerprint, TOOL_KEY, $ip);

    echo json_encode(['success' => true, 'hooks' => $hooks, 'uuid' => $uuid]);

} catch (Exception $e) {
    error_log('Hook generation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'generation_failed']);
}

/**
 * Models don't always respect "JSON only" instructions perfectly — this
 * strips markdown code fences or stray text before/after the array if present.
 */
function extract_json_array(string $raw): ?array {
    $trimmed = trim($raw);
    $trimmed = preg_replace('/^```json\s*|```$/m', '', $trimmed);
    $trimmed = trim($trimmed);

    // If there's stray text around the array, grab just the [ ... ] span
    if ($trimmed[0] !== '[') {
        $start = strpos($trimmed, '[');
        $end = strrpos($trimmed, ']');
        if ($start === false || $end === false) {
            return null;
        }
        $trimmed = substr($trimmed, $start, $end - $start + 1);
    }

    $decoded = json_decode($trimmed, true);
    return is_array($decoded) ? $decoded : null;
}
