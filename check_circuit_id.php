<?php
// check_circuit_id.php
session_name('oss_portal');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'includes/auth.php';
require_roles(['admin', 'manager']);
require_once 'includes/db.php';

header('Content-Type: application/json');

$circuit_id = $_POST['circuit_id'] ?? '';

if (!$circuit_id) {
    echo json_encode(['exists' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM network_details WHERE circuit_id = ?");
$stmt->execute([$circuit_id]);
$exists = $stmt->fetchColumn() > 0;

echo json_encode(['exists' => $exists]);
