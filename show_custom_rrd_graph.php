<?php
// Usage: show_custom_rrd_graph.php?file=localhost_traffic_in_1.rrd
$rrd_file = $_GET['file'] ?? '';
if (!$rrd_file) die("No RRD file specified.");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cacti RRD Graph Viewer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-4">
    <h3>View Cacti Graph: <small><?=htmlspecialchars($rrd_file)?></small></h3>
    <div class="btn-group mb-3">
        <a href="?file=<?=$rrd_file?>&range=day" class="btn btn-outline-primary">Day</a>
        <a href="?file=<?=$rrd_file?>&range=week" class="btn btn-outline-primary">Week</a>
        <a href="?file=<?=$rrd_file?>&range=month" class="btn btn-outline-primary">Month</a>
        <a href="?file=<?=$rrd_file?>&range=year" class="btn btn-outline-primary">Year</a>
    </div>
    <div>
        <img src="cacti_rrd_graph.php?file=<?=urlencode($rrd_file)?>&range=<?=htmlspecialchars($_GET['range'] ?? 'day')?>" class="img-fluid border shadow">
    </div>
</div>
</body>
</html>