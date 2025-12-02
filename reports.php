<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Data Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 40px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background-color: #fff;
            padding: 30px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 10px;
            text-align: center;
        }
        h2 {
            margin-bottom: 20px;
            color: #343a40;
        }
        .export-btn {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
        }
        .export-btn:hover {
            background-color: #0056b3;
        }
        p {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Export Full Customer Data</h2>
        <p>Click the button below to download all customer circuit details as a CSV file.</p>
        <a href="export_csv.php" class="export-btn">Download CSV</a>
    </div>
</body>
</html>
