<?php
session_name('oss_portal');
session_start();
// edit_circuit_graph.php
// Show form to add or edit graph for circuit_id

$circuit_id = $_GET['circuit_id'] ?? null;
if (!$circuit_id) {
    die("circuit_id missing");
}

$pdo = new PDO("mysql:host=localhost;dbname=yourdb;charset=utf8mb4", "user", "pass");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch existing data if any
$stmt = $pdo->prepare("SELECT local_graph_id, description FROM cacti_graphs WHERE circuit_id = ?");
$stmt->execute([$circuit_id]);
$graph = $stmt->fetch(PDO::FETCH_ASSOC);

$local_graph_id = $graph['local_graph_id'] ?? '';
$description = $graph['description'] ?? '';

?>

<!DOCTYPE html>
<html>
<head><title><?= $graph ? 'Edit' : 'Add' ?> Circuit Graph</title></head>
<body>

<h2><?= $graph ? 'Edit' : 'Add' ?> Graph for Circuit ID: <?=htmlspecialchars($circuit_id)?></h2>

<form method="post" action="save_circuit_graph.php">
    <input type="hidden" name="circuit_id" value="<?=htmlspecialchars($circuit_id)?>">
    
    <label for="local_graph_id">Local Graph ID:</label><br>
    <input type="text" id="local_graph_id" name="local_graph_id" required value="<?=htmlspecialchars($local_graph_id)?>"><br><br>
    
    <label for="description">Description:</label><br>
    <textarea id="description" name="description" rows="3" cols="50"><?=htmlspecialchars($description)?></textarea><br><br>
    
    <button type="submit"><?= $graph ? 'Update' : 'Add' ?> Graph</button>
</form>

<p><a href="view_circuit_graph.php?circuit_id=<?=urlencode($circuit_id)?>">Back to View</a></p>

</body>
</html>
