<?php
session_start();

if (!isset($_SESSION['import_data'])) {
    header('Location: upload_csv.php');
    exit;
}

$data = $_SESSION['import_data'];
$errors = [];

// Validation function for one row
function validateRow($row) {
    $rowErrors = [];

    // circuit_id required and numeric
    if (empty($row['circuit_id']) || !is_numeric($row['circuit_id'])) {
        $rowErrors['circuit_id'] = "Circuit ID required and must be a number.";
    }

    // Validate IP fields
    if (!empty($row['pop_ip']) && !filter_var($row['pop_ip'], FILTER_VALIDATE_IP)) {
        $rowErrors['pop_ip'] = "Invalid POP IP.";
    }
    if (!empty($row['switch_ip']) && !filter_var($row['switch_ip'], FILTER_VALIDATE_IP)) {
        $rowErrors['switch_ip'] = "Invalid Switch IP.";
    }

    // installation_date optional but if present must be valid date
    if (!empty($row['installation_date']) && !strtotime($row['installation_date'])) {
        $rowErrors['installation_date'] = "Invalid date format.";
    }

    return $rowErrors;
}

function convertToDate($value) {
    if (empty($value) || $value === "0000-00-00" || $value === "0000-00-00 00:00:00") {
        return null;
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update data from form inputs
    foreach ($data as $idx => $row) {
        foreach ($row as $key => $val) {
            if (isset($_POST['data'][$idx][$key])) {
                $data[$idx][$key] = trim($_POST['data'][$idx][$key]);
            }
        }
    }
    $_SESSION['import_data'] = $data;
}

// Validate all rows
foreach ($data as $idx => $row) {
    $errors[$idx] = validateRow($row);
}

// Check if all rows are valid
$allValid = true;
foreach ($errors as $rowErrors) {
    if (count($rowErrors) > 0) {
        $allValid = false;
        break;
    }
}

// Handle final import
if (isset($_POST['final_submit']) && $allValid) {
    require 'includes/db.php';

    try {
        $pdo->beginTransaction();

        // Prepare example insert or update statement for your customer table
        $stmtCustomer = $pdo->prepare("
            INSERT INTO customer_basic_information 
            (circuit_id, organization_name, contact_person_name, customer_address,city) 
            VALUES (:circuit_id, :organization_name, :contact_person_name, :customer_address,:City)
            ON DUPLICATE KEY UPDATE
            organization_name = VALUES(organization_name),
            contact_person_name = VALUES(contact_person_name),
			city = VALUES(City),
            customer_address = VALUES(customer_address)
        ");

        foreach ($data as $row) {
            $stmtCustomer->execute([
                ':circuit_id' => $row['circuit_id'],
                ':organization_name' => $row['organization_name'],
                ':contact_person_name' => $row['contact_person_name'],
				':City' => $row['City'],
                ':customer_address' => $row['customer_address'],
            ]);
        }

        $pdo->commit();

        unset($_SESSION['import_data']);

        echo "<p style='color:green'>Data imported successfully! <a href='upload_csv.php'>Import more</a></p>";
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<p style='color:red'>Import failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Validate & Import CSV Data</title>
    <style>
        input[type=text] { width: 150px; }
        .error { color: red; font-size: smaller; }
        table { border-collapse: collapse; }
        td, th { border: 1px solid #ccc; padding: 5px; }
    </style>
</head>
<body>
<h2>Validate Imported Data</h2>

<form method="post">
<table>
    <thead>
        <tr>
            <?php foreach (array_keys($data[0]) as $col): ?>
                <th><?=htmlspecialchars($col)?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data as $idx => $row): ?>
            <tr>
                <?php foreach ($row as $key => $value): ?>
                    <td>
                        <input type="text" name="data[<?=$idx?>][<?=$key?>]" value="<?=htmlspecialchars($value)?>">
                        <?php if (!empty($errors[$idx][$key])): ?>
                            <div class="error"><?=htmlspecialchars($errors[$idx][$key])?></div>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<br>
<?php if (!$allValid): ?>
    <p style="color:red;">Please fix errors before final import.</p>
<?php else: ?>
    <button type="submit" name="final_submit" value="1">Import All Valid Data</button>
<?php endif; ?>
<button type="submit">Validate Changes</button>
</form>

<p><a href="upload_csv.php">Back to Upload</a></p>
</body>
</html>
