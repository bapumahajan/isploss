<?php
function log_activity($pdo, $user, $action, $table, $record_id, $description = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $table_name = $table ?: 'N/A'; // default to 'N/A' if null or empty
    $stmt = $pdo->prepare(
        "INSERT INTO audit_log (user, action, table_name, record_id, description, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$user ?: 'unknown', $action, $table_name, $record_id, $description, $ip]);
}

?>