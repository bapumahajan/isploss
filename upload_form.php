<!DOCTYPE html>
<html>
<head>
    <title>Upload Customer CSV</title>
</head>
<body>
    <h2>Upload CSV File (Bulk Insert)</h2>
    <form action="upload_customers.php" method="post" enctype="multipart/form-data">
        Select CSV File: <input type="file" name="csv_file" accept=".csv" required><br><br>
        <input type="submit" value="Upload and Insert">
    </form>
</body>
</html>
