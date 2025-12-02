<?php
// Assumes you have DB access and RRDtool PHP extension installed
require_once 'your_cacti_db_connection.php'; // create $pdo

$local_graph_id = intval($_GET['local_graph_id'] ?? 0);
$range = $_GET['range'] ?? 'day';

switch ($range) {
    case 'year': $start = '-1y'; break;
    case 'month': $start = '-1m'; break;
    case 'week': $start = '-1w'; break;
    default: $start = '-1d'; $range = 'day'; break;
}

// 1. Get the RRD file path(s) and DS names via DB lookup
$stmt = $pdo->prepare("
    SELECT rrd_path, ds_in, ds_out FROM your_graph_rrd_map WHERE local_graph_id = ?
");
$stmt->execute([$local_graph_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    header("HTTP/1.1 404 Not Found");
    exit("Graph not found.");
}
$rrd = $row['rrd_path'];
$ds_in = $row['ds_in'];
$ds_out = $row['ds_out'];

// 2. Generate the graph image
$tmp_img = tempnam(sys_get_temp_dir(), 'rrd') . ".png";
$ret = rrd_graph(
    $tmp_img,
    [
        "--start", $start,
        "--end", "now",
        "--title=Custom Graph (ID $local_graph_id, $range)",
        "--width=700",
        "--height=200",
        "--vertical-label=Bits/sec",
        "DEF:in=$rrd:$ds_in:AVERAGE",
        "DEF:out=$rrd:$ds_out:AVERAGE",
        "CDEF:inbits=in,8,*",
        "CDEF:outbits=out,8,*",
        "LINE1:inbits#00FF00:Inbound",
        "LINE1:outbits#0000FF:Outbound"
    ]
);

if (!$ret) {
    $err = rrd_error();
    header("HTTP/1.1 500 Internal Server Error");
    exit("RRD graph error: $err");
}

header('Content-Type: image/png');
readfile($tmp_img);
unlink($tmp_img);
exit;
?>