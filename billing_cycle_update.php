<?php
// File: billing_cycle_update.php

session_name('oss_portal');
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

// ✅ Access control
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ✅ Input parsing
$input = json_decode(file_get_contents('php://input'), true);

$requiredFields = ['id', 'cost', 'circuit_status', 'justification', 'csrf_token', 'billing_type', 'start_billing_date'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $field"]);
        exit;
    }
}

// ✅ CSRF validation
if ($input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// ✅ Sanitize input
$id = intval($input['id']);
$newCost = floatval($input['cost']);
$newStatus = trim($input['circuit_status']);
$billingType = trim($input['billing_type']);
$startBillingDate = $input['start_billing_date'];
$justification = trim($input['justification']);
$username = $_SESSION['username'] ?? 'unknown';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// ✅ Compute next billing date
$startDateObj = new DateTime($startBillingDate);
switch ($billingType) {
    case 'Monthly':
        $startDateObj->modify('+1 month');
        break;
    case 'Quarterly':
        $startDateObj->modify('+3 months');
        break;
    case 'Half-Yearly':
        $startDateObj->modify('+6 months');
        break;
    case 'Yearly':
        $startDateObj->modify('+1 year');
        break;
}
$newNextBillingDate = $startDateObj->format('Y-m-d');

// ✅ Fetch current data
$stmt = $pdo->prepare("SELECT * FROM billing_cycles WHERE id = ?");
$stmt->execute([$id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {
    http_response_code(404);
    echo json_encode(['error' => 'Record not found']);
    exit;
}

// ✅ Current values
$oldCost = floatval($current['cost']);
$oldStatus = $current['circuit_status'];
$oldBillingType = $current['billing_type'];
$oldNextBillingDate = $current['next_billing_date'];

if ($oldNextBillingDate < date('Y-m-d')) {
    echo json_encode(['error' => 'Cannot modify past billing cycle']);
    exit;
}

// ✅ Check if no change
if (
    $oldCost === $newCost &&
    $oldStatus === $newStatus &&
    $oldBillingType === $billingType &&
    $oldNextBillingDate === $newNextBillingDate
) {
    echo json_encode(['message' => 'No changes detected']);
    exit;
}

// ✅ Perform update
$updateStmt = $pdo->prepare("
    UPDATE billing_cycles
    SET cost = :cost, 
        circuit_status = :status,
        billing_type = :billing_type,
        start_billing_date = :start_billing_date,
        next_billing_date = :next_billing_date
    WHERE id = :id
");
$updateStmt->execute([
    'cost' => $newCost,
    'status' => $newStatus,
    'billing_type' => $billingType,
    'start_billing_date' => $startBillingDate,
    'next_billing_date' => $newNextBillingDate,
    'id' => $id
]);

// ✅ Logging changes
$logStmt = $pdo->prepare("
    INSERT INTO billing_change_logs 
    (billing_id, field_changed, old_value, new_value, justification, modified_by, modified_at)
    VALUES (:billing_id, :field, :old, :new, :justification, :user, NOW())
");

function logChange($field, $old, $new, $id, $justification, $username, $logStmt) {
    if ($old !== $new) {
        $logStmt->execute([
            'billing_id' => $id,
            'field' => $field,
            'old' => $old,
            'new' => $new,
            'justification' => $justification,
            'user' => $username
        ]);
    }
}

logChange('cost', $oldCost, $newCost, $id, $justification, $username, $logStmt);
logChange('circuit_status', $oldStatus, $newStatus, $id, $justification, $username, $logStmt);
logChange('billing_type', $oldBillingType, $billingType, $id, $justification, $username, $logStmt);
logChange('next_billing_date', $oldNextBillingDate, $newNextBillingDate, $id, $justification, $username, $logStmt);

echo json_encode(['success' => true]);
