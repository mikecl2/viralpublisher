<?php
/**
 * usage-gate.php — the shared freemium wall.
 * One email unlocks higher limits across every tool on the site, because
 * the gate is keyed on fingerprint + lead_id, not on a per-tool account.
 */

require_once __DIR__ . '/db.php';

const FINGERPRINT_COOKIE = 'vp_fp';
const FINGERPRINT_TTL_DAYS = 400; // ~13 months, matches modern cookie-lifetime norms

/**
 * Call this at the top of every tool page (not the API endpoint — the page itself)
 * to make sure every visitor has a stable fingerprint before they hit "generate."
 */
function ensure_fingerprint(): string {
    if (!empty($_COOKIE[FINGERPRINT_COOKIE])) {
        return $_COOKIE[FINGERPRINT_COOKIE];
    }

    $fingerprint = generate_uuid_v4();
    setcookie(FINGERPRINT_COOKIE, $fingerprint, [
        'expires' => time() + (FINGERPRINT_TTL_DAYS * 86400),
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[FINGERPRINT_COOKIE] = $fingerprint;

    return $fingerprint;
}

/**
 * The core gate check. Called by every tool's generate.php before spending an AI call.
 *
 * Logic:
 *  - Look up today's usage row for this fingerprint + tool.
 *  - If no email is linked yet, cap at free_limit_anonymous.
 *  - If an email IS linked (from any tool, not just this one), cap at free_limit_email.
 *  - IP hash is a secondary check to slow down trivial cookie-clearing abuse —
 *    not bulletproof, but cheap and good enough at this scale.
 */
function check_usage_gate(string $fingerprint, string $tool, string $ip): array {
    $db = get_db();
    $config = get_tool_config($tool);
    $ipHash = hash_ip($ip);

    // Has this fingerprint ever been linked to a lead (on ANY tool)?
    $leadId = get_linked_lead_id($fingerprint);
    $limit = $leadId ? (int) $config['free_limit_email'] : (int) $config['free_limit_anonymous'];

    $stmt = $db->prepare('
        SELECT use_count FROM usage_tracking
        WHERE fingerprint = ? AND tool = ? AND use_date = CURRENT_DATE
    ');
    $stmt->execute([$fingerprint, $tool]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentCount = $row ? (int) $row['use_count'] : 0;

    // Secondary check: same IP, different fingerprint, same tool, today —
    // catches the "cleared cookies" case without hard-blocking shared IPs (offices, cafes).
    $ipCountToday = get_ip_usage_today($ipHash, $tool);
    $suspicious = $ipCountToday > ($limit * 3); // generous multiplier, avoids false positives

    $allowed = $currentCount < $limit && !$suspicious;

    return [
        'allowed' => $allowed,
        'requires_email' => !$allowed && !$leadId,
        'current_count' => $currentCount,
        'limit' => $limit,
        'has_lead' => (bool) $leadId,
    ];
}

/**
 * Records a single use after a successful generation.
 * Call this AFTER the AI call succeeds, never before — don't charge usage for failures.
 */
function record_usage(string $fingerprint, string $tool, string $ip): void {
    $db = get_db();
    $ipHash = hash_ip($ip);
    $leadId = get_linked_lead_id($fingerprint);

    $stmt = $db->prepare('
        INSERT INTO usage_tracking (fingerprint, tool, ip_hash, lead_id, use_date, use_count)
        VALUES (?, ?, ?, ?, CURRENT_DATE, 1)
        ON CONFLICT(fingerprint, tool, use_date)
        DO UPDATE SET use_count = use_count + 1
    ');
    $stmt->execute([$fingerprint, $tool, $ipHash, $leadId]);
}

/**
 * Links a fingerprint to a lead the moment they submit their email —
 * this is what makes the unlock apply retroactively to today's usage row too.
 */
function link_fingerprint_to_lead(string $fingerprint, int $leadId): void {
    $db = get_db();

    // Backfill today's row (if one exists) so the unlock takes effect immediately,
    // not just on the next generation.
    $stmt = $db->prepare('
        UPDATE usage_tracking SET lead_id = ?
        WHERE fingerprint = ? AND use_date = CURRENT_DATE AND lead_id IS NULL
    ');
    $stmt->execute([$leadId, $fingerprint]);

    // Store the fingerprint->lead link permanently for future gate checks.
    // A lightweight table keeps this separate from usage_tracking's daily rows.
    $db->exec('
        CREATE TABLE IF NOT EXISTS fingerprint_leads (
            fingerprint TEXT PRIMARY KEY,
            lead_id INTEGER NOT NULL REFERENCES leads(id)
        )
    ');
    $stmt = $db->prepare('
        INSERT OR REPLACE INTO fingerprint_leads (fingerprint, lead_id) VALUES (?, ?)
    ');
    $stmt->execute([$fingerprint, $leadId]);
}

function get_linked_lead_id(string $fingerprint): ?int {
    $db = get_db();
    $db->exec('
        CREATE TABLE IF NOT EXISTS fingerprint_leads (
            fingerprint TEXT PRIMARY KEY,
            lead_id INTEGER NOT NULL REFERENCES leads(id)
        )
    ');
    $stmt = $db->prepare('SELECT lead_id FROM fingerprint_leads WHERE fingerprint = ?');
    $stmt->execute([$fingerprint]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['lead_id'] : null;
}

function get_ip_usage_today(string $ipHash, string $tool): int {
    $stmt = get_db()->prepare('
        SELECT COALESCE(SUM(use_count), 0) as total
        FROM usage_tracking
        WHERE ip_hash = ? AND tool = ? AND use_date = CURRENT_DATE
    ');
    $stmt->execute([$ipHash, $tool]);
    return (int) $stmt->fetchColumn();
}

/**
 * Endpoint handler for the "Unlock free tier" form on any tool page.
 * POST { email, tool } -> unlocks the fingerprint across the whole site.
 */
function handle_email_unlock(string $email, string $sourceTool, string $fingerprint, string $ip): array {
    $lead = capture_lead($email, $sourceTool, $ip);
    link_fingerprint_to_lead($fingerprint, (int) $lead['id']);

    return [
        'success' => true,
        'message' => 'Unlocked — you now have extended free access across all tools.',
    ];
}
