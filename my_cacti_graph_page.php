<?php
$local_graph_id = intval($_GET['local_graph_id'] ?? 0);
$range = $_GET['range'] ?? 'day';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Graph <?=htmlspecialchars($local_graph_id)?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h3>Custom Cacti Graph: <small>ID <?=htmlspecialchars($local_graph_id)?></small></h3>
    <div class="btn-group mb-3">
        <a href="?local_graph_id=<?=$local_graph_id?>&range=day" class="btn btn-outline-primary <?=$range==='day'?'active':''?>">Day</a>
        <a href="?local_graph_id=<?=$local_graph_id?>&range=week" class="btn btn-outline-primary <?=$range==='week'?'active':''?>">Week</a>
        <a href="?local_graph_id=<?=$local_graph_id?>&range=month" class="btn btn-outline-primary <?=$range==='month'?'active':''?>">Month</a>
        <a href="?local_graph_id=<?=$local_graph_id?>&range=year" class="btn btn-outline-primary <?=$range==='year'?'active':''?>">Year</a>
    </div>
    <div>
        <img src="my_cacti_graph_image.php?local_graph_id=<?=$local_graph_id?>&range=<?=$range?>" class="img-fluid border shadow">
    </div>
</div>
</body>
</html>