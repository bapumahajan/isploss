<?php
session_start();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed.";
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if ($handle === false) {
            $error = "Could not open file.";
        } else {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                $error = "CSV file is empty or invalid.";
            } else {
                $rows = [];
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) !== count($headers)) {
                        continue; // skip invalid row length
                    }
                    $rows[] = array_combine($headers, $row);
                }
                fclose($handle);

                if (empty($rows)) {
                    $error = "CSV contains no valid data rows.";
                } else {
                    $_SESSION['import_data'] = $rows;
                    header('Location: validate_import.php');
                    exit;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CSV Upload</title>
</head>
<body>
<h2>Upload CSV File</h2>
<?php if ($error): ?>
    <p style="color:red;"><?=htmlspecialchars($error)?></p>
<?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv" required>
    <button type="submit">Upload CSV</button>
</form>
</body>
</html>
