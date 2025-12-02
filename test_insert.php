<?php
require_once __DIR__ . '/includes/db.php'; // Your DB connection file

$name = 'Test Provider ' . rand(1, 1000);

try {
    $stmt = $pdo->prepare("INSERT INTO Third_Party_SP (name) VALUES (?)");
    $stmt->execute([$name]);
    echo "Inserted provider: $name";
} catch (PDOException $e) {
    echo "Insert error: " . $e->getMessage();
}
?>
