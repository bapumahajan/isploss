<?php
// includes/db_helpers.php

function get_customer_circuits_without_third_party($pdo) {
    $stmt = $pdo->query("SELECT circuit_id, organization_name FROM customer_basic_information WHERE circuit_id NOT IN (SELECT DISTINCT circuit_id FROM third_party_details) ORDER BY organization_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_third_parties($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT Third_party_name AS name FROM third_party ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function fetch_switches($pdo) {
    $stmt = $pdo->query("SELECT switch_name, switch_ip FROM switch_inventory ORDER BY switch_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>