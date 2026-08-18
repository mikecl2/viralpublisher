<?php
/**
 * db.php — shared SQLite connection + generic helpers for all viralpublisher.com tools.
 * Every tool includes this file. Nothing tool-specific lives here.
 */

define('DB_PATH', getenv('VP_DB_PATH') ?: __DIR__ . '/../data/viralpublisher.sqlite');

/**
 * Returns a shared PDO connection (one per request).
 */
function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $isNew = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL'); // better concurrent read/write under Coolify

    if ($isNew) {
        run_migrations($pdo);
    }

    return $pdo;
}

/**
 * Creates all tables if the DB file didn't exist yet.
 * Safe to call repeatedly — uses CREATE TABLE IF NOT EXISTS.
 */
function run_migrations(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            source_tool TEXT NOT NULL,
            ip_hash TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS usage_tracking (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fingerprint TEXT NOT NULL,
            ip_hash TEXT NOT NULL,
            tool TEXT NOT NULL,
            lead_id INTEGER REFERENCES leads(id) NULL,
            use_date DATE DEFAULT CURRENT_DATE,
            use_count INTEGER DEFAULT 1,
            UNIQUE(fingerprint, tool, use_date)
        );

        CREATE TABLE IF NOT EXISTS tool_config (
            tool_key TEXT PRIMARY KEY,
            model TEXT NOT NULL DEFAULT 'meta-llama/llama-3.3-70b-instruct:free',
            system_prompt TEXT NOT NULL DEFAULT '',
            free_limit_anonymous INTEGER DEFAULT 3,
            free_limit_email INTEGER DEFAULT 10,
            max_tokens INTEGER DEFAULT 800,
            temperature REAL DEFAULT 0.9
        );

        CREATE TABLE IF NOT EXISTS hook_generations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid TEXT UNIQUE NOT NULL,
            fingerprint TEXT NOT NULL,
            lead_id INTEGER REFERENCES leads(id) NULL,
            topic TEXT NOT NULL,
            platform TEXT NOT NULL,
            tone TEXT,
            output_json TEXT NOT NULL,
            model_used TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS score_checks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid TEXT UNIQUE NOT NULL,
            fingerprint TEXT NOT NULL,
            lead_id INTEGER REFERENCES leads(id) NULL,
            input_text TEXT NOT NULL,
            platform TEXT NOT NULL,
            score INTEGER NOT NULL,
            output_json TEXT NOT NULL,
            model_used TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS script_builds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid TEXT UNIQUE NOT NULL,
            fingerprint TEXT NOT NULL,
            lead_id INTEGER REFERENCES leads(id) NULL,
            platform TEXT NOT NULL,
            input_json TEXT NOT NULL,
            output_json TEXT NOT NULL,
            model_used TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS game_matchups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid TEXT UNIQUE NOT NULL,
            hook_a TEXT NOT NULL,
            hook_a_type TEXT NOT NULL,
            hook_b TEXT NOT NULL,
            hook_b_type TEXT NOT NULL,
            winner TEXT NOT NULL CHECK(winner IN ('a', 'b')),
            explanation TEXT NOT NULL,
            category TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS game_plays (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            matchup_id INTEGER NOT NULL REFERENCES game_matchups(id),
            fingerprint TEXT,
            guess TEXT NOT NULL CHECK(guess IN ('a', 'b')),
            correct INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_usage_lookup ON usage_tracking(fingerprint, tool, use_date);
        CREATE INDEX IF NOT EXISTS idx_leads_email ON leads(email);
    ");

    // Deliberately NOT auto-seeding tool_config rows here. Each tool's
    // dedicated seed script (scripts/seed-*.php) is solely responsible for
    // creating its config row with the correct model/temperature/prompt —
    // a generic default row created here would get overwritten only on
    // system_prompt by a seed script's "already exists" path, silently
    // leaving temperature/model stuck at generic table defaults instead of
    // the tuned values the seed script actually intended.
}

/**
 * UUID v4 generator — used for share-page URLs and generation records.
 */
function generate_uuid_v4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Hashes an IP for storage — never store raw IPs.
 */
function hash_ip(string $ip): string {
    return hash('sha256', $ip . getenv('VP_IP_SALT'));
}

/**
 * Returns the visitor's real IP, accounting for Coolify's reverse proxy
 * (Traefik) sitting in front of the app. Without this, $_SERVER['REMOTE_ADDR']
 * would resolve to the proxy's internal IP for every single visitor —
 * silently breaking the per-IP abuse check in usage-gate.php, since every
 * visitor would appear to share one IP.
 *
 * Caveat: X-Forwarded-For is only trustworthy when a reverse proxy is
 * guaranteed to sit in front of this app (true for a standard Coolify
 * deployment). If this app were ever exposed directly to the internet with
 * no proxy in front of it, a client could spoof this header. That's an
 * acceptable risk here since this value only feeds a soft rate-limit signal,
 * not an authentication or security boundary.
 */
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($ips[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Fetches a tool's config row as an associative array.
 * Throws if the tool hasn't been configured yet — fail loud, not silent.
 */
function get_tool_config(string $toolKey): array {
    $stmt = get_db()->prepare('SELECT * FROM tool_config WHERE tool_key = ?');
    $stmt->execute([$toolKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException("No config found for tool: {$toolKey}");
    }

    return $row;
}

/**
 * Looks up a lead by email, or null if they haven't given it yet.
 */
function find_lead_by_email(string $email): ?array {
    $stmt = get_db()->prepare('SELECT * FROM leads WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Captures a new lead, or returns the existing one if the email is already known.
 * This is the function every tool's "unlock" form calls.
 */
function capture_lead(string $email, string $sourceTool, string $ip): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email');
    }

    $existing = find_lead_by_email($email);
    if ($existing) {
        return $existing;
    }

    $uuid = generate_uuid_v4();
    $stmt = get_db()->prepare('
        INSERT INTO leads (uuid, email, source_tool, ip_hash)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$uuid, $email, $sourceTool, hash_ip($ip)]);

    return find_lead_by_email($email);
}

/**
 * Saves a hook generation record. One function per tool's output table —
 * Score Checker and Script Builder will get their own save_* functions
 * in their own tool folders, following this same shape.
 */
/**
 * Saves a score check record. Mirrors save_hook_generation's shape —
 * each tool gets its own save_* function against its own output table.
 */
function save_score_check(
    string $uuid,
    string $fingerprint,
    ?int $leadId,
    string $inputText,
    string $platform,
    int $score,
    array $result,
    string $model
): void {
    $stmt = get_db()->prepare('
        INSERT INTO score_checks (uuid, fingerprint, lead_id, input_text, platform, score, output_json, model_used)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $uuid, $fingerprint, $leadId, $inputText, $platform, $score,
        json_encode($result), $model,
    ]);
}

/**
 * Saves a script build. Always saves the FULL script regardless of whether
 * the requester was locked/unlocked at request time — the lock only affects
 * what generate.php sends back over the wire, never what's stored. This
 * means if a lead unlocks later, their past generations are recoverable
 * in full from the DB even though they only ever saw a locked preview.
 */
function save_script_build(
    string $uuid,
    string $fingerprint,
    ?int $leadId,
    string $platform,
    array $inputData,
    array $fullOutput,
    string $model
): void {
    $stmt = get_db()->prepare('
        INSERT INTO script_builds (uuid, fingerprint, lead_id, platform, input_json, output_json, model_used)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $uuid, $fingerprint, $leadId, $platform,
        json_encode($inputData), json_encode($fullOutput), $model,
    ]);
}

function save_hook_generation(
    string $uuid,
    string $fingerprint,
    ?int $leadId,
    string $topic,
    string $platform,
    ?string $tone,
    array $hooks,
    string $model
): void {
    $stmt = get_db()->prepare('
        INSERT INTO hook_generations (uuid, fingerprint, lead_id, topic, platform, tone, output_json, model_used)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $uuid, $fingerprint, $leadId, $topic, $platform, $tone,
        json_encode($hooks), $model,
    ]);
}
