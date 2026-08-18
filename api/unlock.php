<?php
/**
 * api/unlock.php — shared across all tools. Called when someone submits
 * their email from any tool's inline capture bar.
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/usage-gate.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$sourceTool = $input['tool'] ?? 'unknown';
$fingerprint = $_COOKIE[FINGERPRINT_COOKIE] ?? null;

if (!$email || !$fingerprint) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_fields']);
    exit;
}

try {
    $result = handle_email_unlock($email, $sourceTool, $fingerprint, get_client_ip());
    echo json_encode($result);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid_email']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'unlock_failed']);
}
