<?php
/**
 * admin/lib/openrouter-models.php — fetches the live OpenRouter model catalogue
 * for the admin model picker, cached for an hour so we're not hitting their
 * API on every page load.
 */

require_once __DIR__ . '/../../lib/db.php';

function ensure_model_cache_table(): void {
    get_db()->exec('
        CREATE TABLE IF NOT EXISTS openrouter_models_cache (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            payload_json TEXT NOT NULL,
            fetched_at DATETIME NOT NULL
        )
    ');
}

/**
 * Returns an array of models: [ ['id' => ..., 'name' => ..., 'is_free' => bool, 'context_length' => ...], ... ]
 * Falls back to cached data (even if stale) if the live fetch fails, so a
 * flaky OpenRouter API never breaks the admin panel.
 */
function get_openrouter_models(bool $forceRefresh = false): array {
    ensure_model_cache_table();
    $db = get_db();

    if (!$forceRefresh) {
        $stmt = $db->query('SELECT payload_json, fetched_at FROM openrouter_models_cache WHERE id = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (time() - strtotime($row['fetched_at'])) < 3600) {
            return json_decode($row['payload_json'], true);
        }
    }

    $fresh = fetch_openrouter_models_live();

    if ($fresh !== null) {
        $stmt = $db->prepare('
            INSERT INTO openrouter_models_cache (id, payload_json, fetched_at)
            VALUES (1, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(id) DO UPDATE SET payload_json = excluded.payload_json, fetched_at = excluded.fetched_at
        ');
        $stmt->execute([json_encode($fresh)]);
        return $fresh;
    }

    // Live fetch failed — serve whatever's cached, even if stale, rather than an empty dropdown.
    $stmt = $db->query('SELECT payload_json FROM openrouter_models_cache WHERE id = 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? json_decode($row['payload_json'], true) : [];
}

function fetch_openrouter_models_live(): ?array {
    $ch = curl_init('https://openrouter.ai/api/v1/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['data']) || !is_array($data['data'])) {
        return null;
    }

    $models = array_map(function ($m) {
        return [
            'id' => $m['id'],
            'name' => $m['name'] ?? $m['id'],
            'is_free' => str_ends_with($m['id'], ':free'),
            'context_length' => $m['context_length'] ?? null,
            'prompt_price' => $m['pricing']['prompt'] ?? null,
        ];
    }, $data['data']);

    // Free models first, then alphabetical — makes the $0 options easy to spot at a glance.
    usort($models, function ($a, $b) {
        if ($a['is_free'] !== $b['is_free']) {
            return $a['is_free'] ? -1 : 1;
        }
        return strcmp($a['name'], $b['name']);
    });

    return $models;
}
