<?php
$host = 'localhost';
$dbname = 'customer_management';
$username = 'root';
$password = 'Bapu@1982';

// Function to clean and normalize text
function clean_text($text) {
    $text = mb_convert_encoding($text, 'UTF-8', 'auto');
    // Replace problematic characters
    $replacements = [
        "\xC2\x92" => "'", "‘" => "'", "’" => "'",
        "“" => '"', "”" => '"', "–" => "-", "—" => "-"
    ];
    return trim(strtr($text, $replacements));
}

try {
    // Ensure connection uses UTF-8
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $filename = $_FILES['csv_file']['tmp_name'];
        $file = fopen($filename, 'r');
        $rowCount = 0;
        $skipped = 0;

        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            if ($rowCount == 0 && strtolower($data[0]) == "circuit_id") {
                $rowCount++;
                continue;
            }

            // Clean and assign variables
            $circuit_id = clean_text($data[0]);
            $organization_name = clean_text($data[1]);
            $customer_address = clean_text($data[2]);
            $city = clean_text($data[3]);
            $contact_person_name = clean_text($data[4]);

            // Skip if empty circuit_id
            if (empty($circuit_id)) {
                $skipped++;
                continue;
            }

            // Check for duplicate
            $stmt = $pdo->prepare("SELECT circuit_id FROM customer_basic_information WHERE circuit_id = ?");
            $stmt->execute([$circuit_id]);
            if ($stmt->rowCount() > 0) {
                $skipped++;
                continue;
            }

            // Insert into table
            $insert = $pdo->prepare("INSERT INTO customer_basic_information 
                (circuit_id, organization_name, customer_address, City, contact_person_name)
                VALUES (?, ?, ?, ?, ?)");
            $insert->execute([
                $circuit_id,
                $organization_name,
                $customer_address,
                $city,
                $contact_person_name
            ]);

            $rowCount++;
        }
        fclose($file);

        echo "✅ Inserted $rowCount records. ❌ Skipped $skipped duplicates or invalid rows.";
    } else {
        echo "⚠️ File upload failed. Please try again.";
    }

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage();
}
?>
