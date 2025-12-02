<?php
//filename:update_customer.php
require_once 'includes/db.php';
require_once 'includes/audit.php';
session_name('oss_portal');
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: view_customer.php');
    exit;
}

$circuit_id            = $_POST['circuit_id'];
$organization_name     = $_POST['organization_name'];
$customer_address      = $_POST['customer_address'];
$contact_person_name   = $_POST['contact_person_name'];
$contact_numbers       = $_POST['contact_number'] ?? [];
$ce_email_ids          = $_POST['ce_email_id'] ?? [];

$product_type          = $_POST['product_type'];
$pop_name              = $_POST['pop_name'];
$switch_name           = $_POST['switch_name'];
$pop_ip                = $_POST['pop_ip'];
$switch_ip             = $_POST['switch_ip'];
$switch_port           = $_POST['switch_port'] ?? null;
$bandwidth             = $_POST['bandwidth'] ?? null;
$circuit_status        = $_POST['circuit_status'] ?? null;
$vlan                  = $_POST['vlan'] ?? null;

$installation_date     = $_POST['installation_date'] ?: null;

$auth_type             = $_POST['auth_type'];
$wan_ip                = trim($_POST['wan_ip'] ?? '');
$netmask               = $_POST['netmask'] ?? '';
$wan_gateway           = $_POST['wan_gateway'] ?? '';
$dns1                  = $_POST['dns1'] ?? '';
$dns2                  = $_POST['dns2'] ?? null;

$pppoe_user            = $auth_type === 'PPPoE' ? trim($_POST['PPPoE_auth_username'] ?? '') : null;
$pppoe_pass            = $auth_type === 'PPPoE' ? $_POST['PPPoE_auth_password'] ?? '' : null;

$cacti_url             = $_POST['cacti_url'] ?? null;
$cacti_username        = $_POST['cacti_username'] ?? null;
$cacti_password        = $_POST['cacti_password'] ?? null;

$remark                = $_POST['remark'] ?? null; // <-- Added for remark field

$ip_addresses = array_values(array_filter(array_map('trim', $_POST['additional_ips'] ?? [])));

// -------- Third Party Section --------
$has_third_party = $_POST['has_third_party'] ?? null;
$tp_circuit_id   = trim($_POST['tp_circuit_id'] ?? '');
$party_name      = trim($_POST['party_name'] ?? '');
$tp_type_service = $_POST['tp_type_service'] ?? '';
$end_a           = trim($_POST['end_a'] ?? '');
$end_b           = trim($_POST['end_b'] ?? '');
$tp_bandwidth    = trim($_POST['bandwidth'] ?? ''); // Note: may overlap with main bandwidth, but kept for compatibility

// === Server-Side Validations with REDIRECTS ===
$validation_error = false;
$validation_params = "";

// Dynamic required validation
if ($auth_type === "Static") {
    if ($wan_ip === '') {
        $validation_error = true;
        $validation_params .= "&wan_ip_error=Required";
    }
    if ($netmask === '') {
        $validation_error = true;
        $validation_params .= "&update_error=" . urlencode("Netmask required for Static");
    }
    if ($wan_gateway === '') {
        $validation_error = true;
        $validation_params .= "&update_error=" . urlencode("WAN Gateway required for Static");
    }
    if ($dns1 === '') {
        $validation_error = true;
        $validation_params .= "&update_error=" . urlencode("DNS1 required for Static");
    }
} elseif ($auth_type === "PPPoE") {
    if ($pppoe_user === '') {
        $validation_error = true;
        $validation_params .= "&pppoe_error=Required";
    }
    if ($pppoe_pass === '') {
        $validation_error = true;
        $validation_params .= "&update_error=" . urlencode("PPPoE Password required for PPPoE");
    }
}

if ($validation_error) {
    header("Location: edit_customer.php?circuit_id=$circuit_id$validation_params");
    exit;
}

// Uniqueness: WAN IP (if present, excluding Terminated)
if ($wan_ip !== '') {
    $stmt = $pdo->prepare("
        SELECT cnd.circuit_id
        FROM circuit_network_details cnd
        JOIN network_details nd ON cnd.circuit_id = nd.circuit_id
        WHERE cnd.wan_ip = ? AND cnd.circuit_id != ? AND nd.circuit_status != 'Terminated'
        LIMIT 1
    ");
    $stmt->execute([$wan_ip, $circuit_id]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        header("Location: edit_customer.php?circuit_id=$circuit_id&wan_ip_error=" . urlencode($wan_ip) . "&conflict_circuit=" . urlencode($existing));
        exit;
    }
}

// Uniqueness: PPPoE Username (if PPPoE and present, excluding Terminated)
if ($auth_type === 'PPPoE' && $pppoe_user !== '') {
    $stmt = $pdo->prepare("
        SELECT cnd.circuit_id
        FROM circuit_network_details cnd
        JOIN network_details nd ON cnd.circuit_id = nd.circuit_id
        WHERE cnd.PPPoE_auth_username = ? AND cnd.circuit_id != ? AND nd.circuit_status != 'Terminated'
        LIMIT 1
    ");
    $stmt->execute([$pppoe_user, $circuit_id]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        header("Location: edit_customer.php?circuit_id=$circuit_id&pppoe_error=" . urlencode($pppoe_user) . "&conflict_circuit=" . urlencode($existing));
        exit;
    }
}

// Additional IPs duplication in DB (excluding Terminated)
$stmt = $pdo->prepare("
    SELECT ci.circuit_id
    FROM circuit_ips ci
    JOIN network_details nd ON ci.circuit_id = nd.circuit_id
    WHERE ci.ip_address = ? AND ci.circuit_id != ? AND nd.circuit_status != 'Terminated'
    LIMIT 1
");
foreach ($ip_addresses as $ip) {
    if ($ip === '') continue;
    $stmt->execute([$ip, $circuit_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        header("Location: edit_customer.php?circuit_id=$circuit_id&ip_error=" . urlencode($ip) . "&conflict_circuit=" . urlencode($result['circuit_id']));
        exit;
    }
}

// Additional IPs duplication in INPUT itself
if (count($ip_addresses) !== count(array_unique($ip_addresses))) {
    header("Location: edit_customer.php?circuit_id=$circuit_id&client_ip_dup=1");
    exit;
}

// -------- Third Party Validation (server-side for update) --------
if ($has_third_party === 'yes') {
    if ($tp_circuit_id === '' || $party_name === '' || !in_array($tp_type_service, ['ILL', 'Point-to-Point', 'MPLS']) ||
        $end_a === '' || $end_b === '' || $tp_bandwidth === '' || !is_numeric($tp_bandwidth) || $tp_bandwidth <= 0) {
        // Send back to edit with error
        header("Location: edit_customer.php?circuit_id=$circuit_id&update_error=" . urlencode("Third party details incomplete or invalid"));
        exit;
    }
}

try {
    $pdo->beginTransaction();

    // --- 1) customer_basic_information
    $stmtOld = $pdo->prepare("SELECT organization_name, customer_address, contact_person_name FROM customer_basic_information WHERE circuit_id = ?");
    $stmtOld->execute([$circuit_id]);
    $old_customer = $stmtOld->fetch(PDO::FETCH_ASSOC);

    $new_customer = [
        'organization_name'    => $organization_name,
        'customer_address'     => $customer_address,
        'contact_person_name'  => $contact_person_name,
    ];

    if ($old_customer != $new_customer) {
        $stmt1 = $pdo->prepare("
            UPDATE customer_basic_information
            SET organization_name = :org,
                customer_address = :addr,
                contact_person_name = :contact
            WHERE circuit_id = :cid
        ");
        $stmt1->execute([
            'org'     => $organization_name,
            'addr'    => $customer_address,
            'contact' => $contact_person_name,
            'cid'     => $circuit_id,
        ]);
        log_activity(
            $pdo,
            $_SESSION['username'],
            'update',
            'customer_basic_information',
            $circuit_id,
            "Changed: " .
                json_encode(['old' => $old_customer, 'new' => $new_customer])
        );
    }

    // --- 2) network_details
    $stmtOld = $pdo->prepare("SELECT product_type, pop_name, switch_name, pop_ip, switch_ip, switch_port, bandwidth, circuit_status, vlan FROM network_details WHERE circuit_id = ?");
    $stmtOld->execute([$circuit_id]);
    $old_network = $stmtOld->fetch(PDO::FETCH_ASSOC);

    $new_network = [
        'product_type'  => $product_type,
        'pop_name'      => $pop_name,
        'switch_name'   => $switch_name,
        'pop_ip'        => $pop_ip,
        'switch_ip'     => $switch_ip,
        'switch_port'   => $switch_port,
        'bandwidth'     => $bandwidth,
        'circuit_status'=> $circuit_status,
        'vlan'          => $vlan,
    ];

    if ($old_network != $new_network) {
        $stmt2 = $pdo->prepare("
            UPDATE network_details
            SET product_type = :ptype,
                pop_name = :pop,
                switch_name = :sw,
                pop_ip = :pip,
                switch_ip = :sip,
                switch_port = :sport,
                bandwidth = :bw,
                circuit_status = :status,
                vlan = :vlan
            WHERE circuit_id = :cid
        ");
        $stmt2->execute([
            'ptype'  => $product_type,
            'pop'    => $pop_name,
            'sw'     => $switch_name,
            'pip'    => $pop_ip,
            'sip'    => $switch_ip,
            'sport'  => $switch_port,
            'bw'     => $bandwidth,
            'status' => $circuit_status,
            'vlan'   => $vlan,
            'cid'    => $circuit_id,
        ]);
        log_activity(
            $pdo,
            $_SESSION['username'],
            'update',
            'network_details',
            $circuit_id,
            "Changed: " . json_encode(['old' => $old_network, 'new' => $new_network])
        );
    }

    // --- 3) circuit_network_details
    $stmtOld = $pdo->prepare("SELECT installation_date, wan_ip, netmask, wan_gateway, dns1, dns2, auth_type, PPPoE_auth_username, PPPoE_auth_password, cacti_url, cacti_username, cacti_password, remark FROM circuit_network_details WHERE circuit_id = ?");
    $stmtOld->execute([$circuit_id]);
    $old_cnd = $stmtOld->fetch(PDO::FETCH_ASSOC);

    // Only set fields relevant to selected auth_type
    if ($auth_type === "Static") {
        $save_wan_ip = $wan_ip;
        $save_netmask = $netmask;
        $save_wan_gateway = $wan_gateway;
        $save_dns1 = $dns1;
        $save_dns2 = $dns2;
        $save_pppoe_user = null;
        $save_pppoe_pass = null;
    } elseif ($auth_type === "PPPoE") {
        $save_wan_ip = $wan_ip;
        $save_netmask = ''; // NOT NULL field, so empty string
        $save_wan_gateway = '';
        $save_dns1 = '';
        $save_dns2 = '';
        $save_pppoe_user = $pppoe_user;
        $save_pppoe_pass = $pppoe_pass;
    } else {
        $save_wan_ip = $wan_ip;
        $save_netmask = $netmask;
        $save_wan_gateway = $wan_gateway;
        $save_dns1 = $dns1;
        $save_dns2 = $dns2;
        $save_pppoe_user = $pppoe_user;
        $save_pppoe_pass = $pppoe_pass;
    }

    $new_cnd = [
        'installation_date'    => $installation_date,
        'wan_ip'               => $save_wan_ip,
        'netmask'              => $save_netmask,
        'wan_gateway'          => $save_wan_gateway,
        'dns1'                 => $save_dns1,
        'dns2'                 => $save_dns2,
        'auth_type'            => $auth_type,
        'PPPoE_auth_username'  => $save_pppoe_user,
        'PPPoE_auth_password'  => $save_pppoe_pass,
        'cacti_url'            => $cacti_url,
        'cacti_username'       => $cacti_username,
        'cacti_password'       => $cacti_password,
        'remark'               => $remark, // <-- Add remark here
    ];

    if ($old_cnd != $new_cnd) {
        $stmt3 = $pdo->prepare("
            INSERT INTO circuit_network_details
            (circuit_id, installation_date, wan_ip, netmask, wan_gateway, dns1, dns2,
             auth_type, PPPoE_auth_username, PPPoE_auth_password,
             cacti_url, cacti_username, cacti_password, remark)
            VALUES
            (:cid, :inst, :wan, :nm, :gw, :d1, :d2, :atype, :ppu, :ppp, :curl, :cu, :cp, :remark)
            ON DUPLICATE KEY UPDATE
                installation_date = VALUES(installation_date),
                wan_ip = VALUES(wan_ip),
                netmask = VALUES(netmask),
                wan_gateway = VALUES(wan_gateway),
                dns1 = VALUES(dns1),
                dns2 = VALUES(dns2),
                auth_type = VALUES(auth_type),
                PPPoE_auth_username = VALUES(PPPoE_auth_username),
                PPPoE_auth_password = VALUES(PPPoE_auth_password),
                cacti_url = VALUES(cacti_url),
                cacti_username = VALUES(cacti_username),
                cacti_password = VALUES(cacti_password),
                remark = VALUES(remark)
        ");
        $stmt3->execute([
            'cid'   => $circuit_id,
            'inst'  => $installation_date,
            'wan'   => $save_wan_ip,
            'nm'    => $save_netmask,
            'gw'    => $save_wan_gateway,
            'd1'    => $save_dns1,
            'd2'    => $save_dns2,
            'atype' => $auth_type,
            'ppu'   => $save_pppoe_user,
            'ppp'   => $save_pppoe_pass,
            'curl'  => $cacti_url,
            'cu'    => $cacti_username,
            'cp'    => $cacti_password,
            'remark'=> $remark, // <-- Add remark here
        ]);
        log_activity(
            $pdo,
            $_SESSION['username'],
            'update',
            'circuit_network_details',
            $circuit_id,
            "Changed: " . json_encode(['old' => $old_cnd, 'new' => $new_cnd])
        );
    }

    // --- 4) circuit_ips (array)
    $stmtOld = $pdo->prepare("SELECT ip_address FROM circuit_ips WHERE circuit_id = ? ORDER BY ip_address ASC");
    $stmtOld->execute([$circuit_id]);
    $old_ips = $stmtOld->fetchAll(PDO::FETCH_COLUMN);

    $new_ips = $ip_addresses;
    sort($old_ips);
    sort($new_ips);

    if ($old_ips != $new_ips) {
        // Replace IPs
        $pdo->prepare("DELETE FROM circuit_ips WHERE circuit_id = ?")->execute([$circuit_id]);
        $stmt_ip = $pdo->prepare("INSERT INTO circuit_ips (circuit_id, ip_address) VALUES (?, ?)");
        foreach ($ip_addresses as $ip) {
            if ($ip === '') continue;
            $stmt_ip->execute([$circuit_id, $ip]);
        }
        log_activity(
            $pdo,
            $_SESSION['username'],
            'update',
            'circuit_ips',
            $circuit_id,
            "Changed: " . json_encode(['old' => $old_ips, 'new' => $new_ips])
        );
    }

    // --- 5) customer_contacts (array)
    $stmtOld = $pdo->prepare("SELECT contact_number FROM customer_contacts WHERE circuit_id = ? ORDER BY contact_number ASC");
    $stmtOld->execute([$circuit_id]);
    $old_contacts = $stmtOld->fetchAll(PDO::FETCH_COLUMN);

    $new_contacts = array_values(array_filter(array_map('trim', $contact_numbers)));
    sort($old_contacts);
    sort($new_contacts);

    if ($old_contacts != $new_contacts) {
        // Replace contacts
        $pdo->prepare("DELETE FROM customer_contacts WHERE circuit_id = ?")->execute([$circuit_id]);
        $stmt_contact = $pdo->prepare("INSERT INTO customer_contacts (circuit_id, contact_number) VALUES (?, ?)");
        foreach ($new_contacts as $num) {
            if ($num === '') continue;
            $stmt_contact->execute([$circuit_id, $num]);
        }
        log_activity(
            $pdo,
            $_SESSION['username'],
            'update',
            'customer_contacts',
            $circuit_id,
            "Changed: " . json_encode(['old' => $old_contacts, 'new' => $new_contacts])
        );
    }

    // --- 6) customer_emails (array)
    $stmtOld = $pdo->prepare("SELECT ce_email_id FROM customer_emails WHERE circuit_id = ? ORDER BY ce_email_id ASC");
    $stmtOld->execute([$circuit_id]);
    $old_emails = $stmtOld->fetchAll(PDO::FETCH_COLUMN);

    $new_emails = array_values(array_filter(array_map('trim', $ce_email_ids)));
    sort($old_emails);
    sort($new_emails);

    if ($old_emails != $new_emails) {
        // Replace emails
        $pdo->prepare("DELETE FROM customer_emails WHERE circuit_id = ?")->execute([$circuit_id]);
        $stmt_email = $pdo->prepare("INSERT INTO customer_emails (circuit_id, ce_email_id) VALUES (?, ?)");
        foreach ($new_emails as $email) {
            if ($email === '') continue;
            $stmt_email->execute([$circuit_id, $email]);
        }
        log_activity(
            $pdo,
            $_SESSION['username'],
            'update',
            'customer_emails',
            $circuit_id,
            "Changed: " . json_encode(['old' => $old_emails, 'new' => $new_emails])
        );
    }

    // --- 7) Third Party Details ---
    $stmt_tp = $pdo->prepare("SELECT * FROM third_party_details WHERE circuit_id = ?");
    $stmt_tp->execute([$circuit_id]);
    $old_tp = $stmt_tp->fetch(PDO::FETCH_ASSOC);

    if ($has_third_party === 'yes') {
        // Insert or update third party details
        if ($old_tp) {
            // Update
            $stmt_tp_up = $pdo->prepare("UPDATE third_party_details SET tp_circuit_id = ?, Third_party_name = ?, tp_type_service = ?, end_a = ?, end_b = ?, bandwidth = ? WHERE circuit_id = ?");
            $stmt_tp_up->execute([$tp_circuit_id, $party_name, $tp_type_service, $end_a, $end_b, $tp_bandwidth, $circuit_id]);
            log_activity(
                $pdo,
                $_SESSION['username'],
                'update',
                'third_party_details',
                $old_tp['id'],
                "Updated third party details"
            );
        } else {
            // Insert
            $stmt_tp_in = $pdo->prepare("INSERT INTO third_party_details (circuit_id, tp_circuit_id, Third_party_name, tp_type_service, end_a, end_b, bandwidth) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_tp_in->execute([$circuit_id, $tp_circuit_id, $party_name, $tp_type_service, $end_a, $end_b, $tp_bandwidth]);
            log_activity(
                $pdo,
                $_SESSION['username'],
                'insert',
                'third_party_details',
                $pdo->lastInsertId(),
                "Added third party details"
            );
        }
    } else {
        // If user selected "No", delete any third party record
        if ($old_tp) {
            $stmt_tp_del = $pdo->prepare("DELETE FROM third_party_details WHERE circuit_id = ?");
            $stmt_tp_del->execute([$circuit_id]);
            log_activity(
                $pdo,
                $_SESSION['username'],
                'delete',
                'third_party_details',
                $old_tp['id'],
                "Removed third party details"
            );
        }
    }

    $pdo->commit();

    header("Location: view_customer.php?circuit_id=" . urlencode($circuit_id) . "&updated=1");
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: edit_customer.php?circuit_id=$circuit_id&update_error=" . urlencode($e->getMessage()));
    exit;
}
?>