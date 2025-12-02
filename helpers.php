<?php
function get_pop_data($pdo) {
    $pop_data = $pdo->query("SELECT pop_name, pop_ip FROM pop_inventory")->fetchAll(PDO::FETCH_ASSOC);
    usort($pop_data, fn($a, $b) => strcasecmp($a['pop_name'], $b['pop_name']));
    return $pop_data;
}

function get_switch_data($pdo) {
    $switch_data = $pdo->query("SELECT switch_name, switch_ip FROM switch_inventory")->fetchAll(PDO::FETCH_ASSOC);
    usort($switch_data, fn($a, $b) => strcasecmp($a['switch_name'], $b['switch_name']));
    return $switch_data;
}

function get_product_types($pdo) {
    return $pdo->query("SELECT DISTINCT product_type FROM network_details")->fetchAll(PDO::FETCH_COLUMN);
}

function get_ips($pdo, $circuit_id) {
    $ip_stmt = $pdo->prepare("SELECT ip_address FROM circuit_ips WHERE circuit_id = ?");
    $ip_stmt->execute([$circuit_id]);
    return $ip_stmt->fetchAll(PDO::FETCH_COLUMN);
}

function get_contacts($pdo, $circuit_id) {
    $stmt = $pdo->prepare("SELECT contact_number FROM customer_contacts WHERE circuit_id = ?");
    $stmt->execute([$circuit_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function get_emails($pdo, $circuit_id) {
    $stmt = $pdo->prepare("SELECT ce_email_id FROM customer_emails WHERE circuit_id = ?");
    $stmt->execute([$circuit_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}