<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Inventory</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-header {
            background-color: #f8f9fa;
            font-size: 1.25rem;
        }
        .card-body {
            font-size: 0.9rem;
        }
        .card {
            margin-top: 15px;
            box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075); /* Custom small shadow */
        }
        .alert {
            margin-top: 20px;
        }
        .form-label {
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container mt-4">

    <!-- Search Form -->
    <div class="card shadow-sm">
        <div class="card-body text-center">
            <form action="search_inventory.php" method="POST">
                <div class="mb-3">
                    <label for="searchTerm" class="form-label">Enter Circuit ID:</label><br>
                    <input type="text" class="form-control form-control-sm d-inline-block rounded-pill shadow-sm text-center"
                           style="max-width: 300px;"
                           id="searchTerm"
                           name="searchTerm"
                           placeholder="e.g. CIR-123456"
                           required
                           value="<?php echo isset($_POST['searchTerm']) ? htmlspecialchars(trim($_POST['searchTerm'])) : ''; ?>">
                </div>
                <div class="d-flex justify-content-center gap-2 mt-2">
                    <button type="submit" class="btn btn-primary btn-sm px-4">Search</button>
                    <a href="search_inventory.php" class="btn btn-secondary btn-sm px-4">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Search Results -->
    <?php
    include 'includes/db.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $searchTerm = trim($_POST['searchTerm'] ?? '');

        if (!empty($searchTerm)) {
            $query = $pdo->prepare("
                SELECT c.circuit_id, c.organization_name, c.customer_address, c.contact_person_name, c.contact_number, c.ce_email_id, 
                       n.product_type, n.pop_name, n.pop_ip, n.switch_name, n.switch_ip, n.switch_port, n.bandwidth, n.circuit_status
                FROM customer_basic_information c
                LEFT JOIN network_details n ON c.circuit_id = n.circuit_id
                WHERE c.circuit_id = :searchTerm
            ");

            $query->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_ASSOC);

            if (count($results) > 0) {
                echo "<h4 class='text-center mt-4'>Showing " . count($results) . " result(s) for <strong>" . htmlspecialchars($searchTerm) . "</strong></h4>";

                foreach ($results as $row) {
                    echo "<div class='card shadow-sm'>
                            <div class='card-header'>
                                <strong>Circuit ID: " . htmlspecialchars($row['circuit_id'] ?? 'N/A') . "</strong>
                            </div>
                            <div class='card-body'>
                                <div class='row'>
                                    <div class='col-md-6'>
                                        <p><strong>Organization Name:</strong> " . htmlspecialchars($row['organization_name'] ?? 'N/A') . "</p>
                                        <p><strong>Customer Address:</strong> " . htmlspecialchars($row['customer_address'] ?? 'N/A') . "</p>
                                        <p><strong>Contact Person:</strong> " . htmlspecialchars($row['contact_person_name'] ?? 'N/A') . "</p>
                                        <p><strong>Contact Number:</strong> " . htmlspecialchars($row['contact_number'] ?? 'N/A') . "</p>
                                    </div>
                                    <div class='col-md-6'>
                                        <p><strong>Email ID:</strong> " . htmlspecialchars($row['ce_email_id'] ?? 'N/A') . "</p>
                                        <p><strong>Product Type:</strong> " . htmlspecialchars($row['product_type'] ?? 'N/A') . "</p>
                                        <p><strong>POP Name:</strong> " . htmlspecialchars($row['pop_name'] ?? 'N/A') . "</p>
                                        <p><strong>POP IP:</strong> " . htmlspecialchars($row['pop_ip'] ?? 'N/A') . "</p>
                                    </div>
                                </div>
                                <div class='row'>
                                    <div class='col-md-6'>
                                        <p><strong>Switch Name:</strong> " . htmlspecialchars($row['switch_name'] ?? 'N/A') . "</p>
                                        <p><strong>Switch IP:</strong> " . htmlspecialchars($row['switch_ip'] ?? 'N/A') . "</p>
                                    </div>
                                    <div class='col-md-6'>
                                        <p><strong>Switch Port:</strong> " . htmlspecialchars($row['switch_port'] ?? 'N/A') . "</p>
                                        <p><strong>Bandwidth:</strong> " . htmlspecialchars($row['bandwidth'] ?? 'N/A') . "</p>
                                        <p><strong>Circuit Status:</strong> " . htmlspecialchars($row['circuit_status'] ?? 'N/A') . "</p>
                                    </div>
                                </div>
                            </div>
                        </div>";
                }
            } else {
                echo "<div class='alert alert-warning mt-4' role='alert'>
                        No results found for the Circuit ID: <strong>" . htmlspecialchars($searchTerm) . "</strong>
                    </div>";
            }
        }
    }
    ?>
</div>

<!-- Bootstrap 5 JS Bundle (Optional) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
