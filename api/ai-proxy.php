<?php
/**
 * api/ai-proxy.php — the only file that ever touches the OpenRouter API key.
 * Every tool's generate.php calls call_openrouter() from here; nothing else
 * should ever build its own curl request to OpenRouter directly.
 */

if (!defined('OPENROUTER_KEY')) {
    define('OPENROUTER_KEY', getenv('OPENROUTER_API_KEY'));
}

/**
 * Calls OpenRouter's chat completions endpoint and returns the model's
 * text response. Throws on any failure — callers should catch and turn
 * this into a clean user-facing error rather than let it bubble raw.
 *
 * $timeoutSeconds defaults to 30, which is enough for Hook Generator and
 * Score Checker's shorter outputs. Script Builder generates a full 7-section
 * script (up to ~2200 tokens vs the other tools' 800-900) and was
 * consistently timing out on slower free-model responses at 30s — confirmed
 * in production via curl errno 28 (CURLE_OPERATION_TIMEDOUT). Its generate.php
 * passes a longer value explicitly rather than raising this default for
 * every tool, since the lighter tools don't need to wait that long even
 * when something's genuinely broken.
 */
function call_openrouter(string $model, array $messages, float $temperature = 0.9, int $maxTokens = 800, int $timeoutSeconds = 30): string {
    if (!OPENROUTER_KEY) {
        throw new RuntimeException('OpenRouter API key not configured');
    }

    $attempt = 0;
    $maxAttempts = 2; // one retry, only for the specific case below

    while (true) {
        $attempt++;
        [$curlErrno, $curlError, $httpCode, $response] = execute_openrouter_request(
            $model, $messages, $temperature, $maxTokens, $timeoutSeconds
        );

        // Only retry on 429 (rate limited) — this fails FAST (no long hang),
        // so a short backoff-and-retry fits comfortably within the existing
        // timeout budget. Deliberately NOT applied to timeouts or other
        // errors, where a retry could push a request past its PHP execution
        // ceiling instead of just failing cleanly and quickly. Confirmed
        // against a real recurring production failure: OpenRouter's free
        // Google AI Studio pool returning 429 "temporarily rate-limited
        // upstream" under shared-pool congestion.
        if ($httpCode === 429 && $attempt < $maxAttempts) {
            error_log("OpenRouter 429 rate-limited on attempt {$attempt}, retrying in 3s...");
            sleep(3);
            continue;
        }

        break;
    }

    if ($curlErrno !== 0) {
        error_log("OpenRouter curl error ({$curlErrno}): {$curlError}");
        throw new RuntimeException('AI provider unreachable');
    }

    if ($httpCode !== 200) {
        error_log("OpenRouter HTTP error ({$httpCode}): " . substr((string) $response, 0, 500));
        throw new RuntimeException('AI provider error');
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? null;

    if ($content === null) {
        error_log('OpenRouter returned an unexpected response shape: ' . substr((string) $response, 0, 500));
        throw new RuntimeException('AI provider returned no content');
    }

    return $content;
}

/**
 * The actual single HTTP call, factored out so call_openrouter() can retry
 * it cleanly on a 429 without duplicating the curl setup. Returns
 * [curlErrno, curlError, httpCode, rawResponse].
 */
function execute_openrouter_request(string $model, array $messages, float $temperature, int $maxTokens, int $timeoutSeconds): array {
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . OPENROUTER_KEY,
            'Content-Type: application/json',
            'HTTP-Referer: https://viralpublisher.com',
            'X-Title: Viral Publisher',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            // Tells reasoning-capable models (DeepSeek-R1-style, etc.) to skip
            // the visible "thinking out loud" phase and answer directly.
            // A small number of models mandate reasoning and will 400 on
            // this — acceptable tradeoff, since that failure is still caught
            // and logged cleanly like any other, not a crash.
            'reasoning' => ['enabled' => false, 'exclude' => true],
        ]),
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$curlErrno, $curlError, $httpCode, $response];
}
