<?php
/**
 * tools/script-builder/generate.php
 *
 * Same shared infrastructure as the other tools, with one important
 * difference: this is a HARD gate, not a count-based one. Whether a
 * fingerprint has ever linked to a lead (via ANY tool's email capture)
 * determines whether they see the full script or a locked preview —
 * and that trimming happens here, server-side, before the response is
 * ever sent. The client never receives section content it isn't allowed
 * to show, so there's nothing to unlock by inspecting the network tab.
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/usage-gate.php';
require_once __DIR__ . '/../../api/ai-proxy.php';

header('Content-Type: application/json');

const TOOL_KEY = 'script_builder';
const MAX_STEPS = 6;
const MIN_STEPS = 2;

$input = json_decode(file_get_contents('php://input'), true);
$fingerprint = $_COOKIE[FINGERPRINT_COOKIE] ?? null;
$ip = get_client_ip();

if (!$fingerprint) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_fields']);
    exit;
}

$topicA = trim($input['topic_a'] ?? '');
$topicB = trim($input['topic_b'] ?? '');
$proof = trim($input['proof'] ?? '');
$benefits = trim($input['benefits'] ?? '');
$painPoints = trim($input['pain_points'] ?? '');
$saveBait = trim($input['save_bait'] ?? '');
$ctaKeyword = trim($input['cta_keyword'] ?? '');
$offerDetails = trim($input['offer_details'] ?? '');
$platform = $input['platform'] ?? 'reels';
$steps = is_array($input['steps'] ?? null) ? $input['steps'] : [];

if (!$topicA || !$topicB || !$proof || !$ctaKeyword) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_required_fields']);
    exit;
}

$steps = array_values(array_filter($steps, fn($s) => !empty(trim($s['name'] ?? ''))));
if (count($steps) < MIN_STEPS) {
    http_response_code(400);
    echo json_encode(['error' => 'not_enough_steps', 'min' => MIN_STEPS]);
    exit;
}
if (count($steps) > MAX_STEPS) {
    $steps = array_slice($steps, 0, MAX_STEPS);
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

    $stepsText = implode("\n", array_map(
        fn($s, $i) => ($i + 1) . '. "' . trim($s['name']) . '" — does: ' . trim($s['does'] ?? '')
            . (!empty($s['lesson']) ? ' — lesson: ' . trim($s['lesson']) : ''),
        $steps,
        array_keys($steps)
    ));

    $userPrompt = "Platform: {$platform}\n\n"
        . "Hot topic A: {$topicA}\n"
        . "Hot topic B: {$topicB}\n\n"
        . "Proof/credibility: {$proof}\n\n"
        . "Benefits:\n{$benefits}\n\n"
        . "Pain points to defuse:\n{$painPoints}\n\n"
        . "Steps (write Section 5 covering every one, in this order):\n{$stepsText}\n\n"
        . "Save bait content: {$saveBait}\n\n"
        . "CTA keyword: {$ctaKeyword}\n"
        . "Offer/bonus/urgency details: {$offerDetails}\n\n"
        . "Write the full 7-section script now. Respond with ONLY the JSON array described in your system instructions.";

    $rawResult = call_openrouter(
        $config['model'],
        [
            ['role' => 'system', 'content' => $config['system_prompt']],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        (float) $config['temperature'],
        (int) $config['max_tokens'],
        75 // longer than the 30s default — see call_openrouter()'s docblock for why
    );

    $sections = extract_json_array($rawResult);

    if (!$sections || count($sections) === 0) {
        // Log enough of the raw output to actually diagnose the failure next
        // time this happens, rather than a generic message with no evidence.
        // The tail is the most telling part: if it cuts off mid-string/mid-object
        // instead of ending in "}]", that's truncation from hitting max_tokens,
        // not the model breaking format — two very different fixes.
        $preview = 'length=' . strlen($rawResult)
            . ' | head=' . substr($rawResult, 0, 150)
            . ' | tail=' . substr($rawResult, -150);
        throw new RuntimeException('Model returned no parseable script sections. ' . $preview);
    }

    $uuid = generate_uuid_v4();
    $leadId = get_linked_lead_id($fingerprint);
    $isLocked = !$leadId;

    $inputData = [
        'topic_a' => $topicA, 'topic_b' => $topicB, 'proof' => $proof,
        'benefits' => $benefits, 'pain_points' => $painPoints, 'steps' => $steps,
        'save_bait' => $saveBait, 'cta_keyword' => $ctaKeyword, 'offer_details' => $offerDetails,
    ];

    // Always save the FULL script, regardless of lock status — see save_script_build() docblock.
    save_script_build($uuid, $fingerprint, $leadId, $platform, $inputData, $sections, $config['model']);
    record_usage($fingerprint, TOOL_KEY, $ip);

    // The actual gate enforcement: trim section content before it ever leaves the server.
    $responseSections = $isLocked ? lock_sections_for_response($sections) : $sections;

    echo json_encode([
        'success' => true,
        'locked' => $isLocked,
        'sections' => $responseSections,
        'uuid' => $uuid,
    ]);

} catch (Exception $e) {
    error_log('Script build failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'generation_failed']);
}

/**
 * Keeps Section 1 in full (the "free taste") and strips all content from
 * sections 2-7 down to just number/title/locked — no on_screen, spoken,
 * camera_notes, steps, or save_bait_screen data is sent to the client at all.
 */
function lock_sections_for_response(array $sections): array {
    return array_map(function ($section, $index) {
        if ($index === 0) {
            return $section; // Section 1 stays full — the free preview
        }
        return [
            'number' => $section['number'] ?? ($index + 1),
            'title' => $section['title'] ?? '',
            'locked' => true,
        ];
    }, $sections, array_keys($sections));
}

/**
 * Same defensive JSON-array parsing pattern as the Hook Generator's.
 */
function extract_json_array(string $raw): ?array {
    $trimmed = trim($raw);
    $trimmed = preg_replace('/^```json\s*|```$/m', '', $trimmed);
    $trimmed = trim($trimmed);

    if (($trimmed[0] ?? '') !== '[') {
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
