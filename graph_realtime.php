<?php
session_start();

// Security: Only allow access with a valid token mapped to a local_graph_id
if (!isset($_GET['token']) || !isset($_SESSION['cacti_tokens'][$_GET['token']])) {
    http_response_code(403);
    exit('Invalid or expired token');
}

$token = $_GET['token'];
$local_graph_id = $_SESSION['cacti_tokens'][$token];

// Optional: Let the user tweak width/height (safely), or set defaults like Cacti
$width = isset($_GET['width']) && intval($_GET['width']) > 0 ? intval($_GET['width']) : 900;
$height = isset($_GET['height']) && intval($_GET['height']) > 0 ? intval($_GET['height']) : 330;
$refresh = isset($_GET['refresh']) && intval($_GET['refresh']) > 0 ? intval($_GET['refresh']) : 5; // seconds
$rra_id = isset($_GET['rra_id']) ? $_GET['rra_id'] : 0;

// Build the proxied graph image URL (auto-bust cache every refresh)
$img_url = "cacti_proxy.php?type=graph_image&token=" . urlencode($token) .
    "&rra_id=" . urlencode($rra_id) .
    "&width=" . $width .
    "&height=" . $height .
    "&rand=" . time();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cacti Realtime Graph (Secure Proxy)</title>
    <meta http-equiv="refresh" content="<?= $refresh ?>">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        body {
            background: #e5e5e5;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
        }
        #box {
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px #888;
            padding: 24px 24px 14px 24px;
            max-width: <?= $width + 32 ?>px;
        }
        #title {
            font-size: 20px;
            margin-bottom: 4px;
            color: #00528E;
        }
        #refresh-note {
            font-size: 12px;
            color: #666;
        }
        .graph-img {
            border: 1px solid #aaa;
            border-radius: 6px;
            background: #fafafa;
        }
        .controls {
            text-align: right;
            margin-bottom: 10px;
        }
        .controls input[type=number] {
            width: 50px;
        }
    </style>
</head>
<body>
<div id="box">
    <div id="title">Cacti Realtime Graph</div>
    <div class="controls">
        <!-- Controls for width, height, refresh, rra_id -->
        <form method="get" style="display:inline;">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            Width: <input type="number" name="width" value="<?= $width ?>" min="100" max="2000" />
            Height: <input type="number" name="height" value="<?= $height ?>" min="50" max="1000" />
            Refresh: <input type="number" name="refresh" value="<?= $refresh ?>" min="1" max="60" />s
            Time Window:
            <select name="rra_id">
                <option value="0"<?= $rra_id == 0 ? ' selected' : '' ?>>Realtime</option>
                <option value="all"<?= $rra_id === "all" ? ' selected' : '' ?>>Default (all)</option>
                <option value="1"<?= $rra_id == 1 ? ' selected' : '' ?>>5 Minutes</option>
                <option value="2"<?= $rra_id == 2 ? ' selected' : '' ?>>30 Minutes</option>
                <option value="3"<?= $rra_id == 3 ? ' selected' : '' ?>>2 Hours</option>
                <option value="4"<?= $rra_id == 4 ? ' selected' : '' ?>>1 Day</option>
                <!-- Add more if your Cacti is configured with other RRAs -->
            </select>
            <button type="submit">Apply</button>
        </form>
    </div>

    <img 
        class="graph-img"
        id="realtime-graph"
        src="<?= htmlspecialchars($img_url) ?>"
        width="<?= $width ?>"
        height="<?= $height ?>"
        alt="Cacti Realtime Graph"
    />

    <div id="refresh-note">
        The graph refreshes every <?= $refresh ?> second(s).<br>
        The current value and legend (if present) are shown below the graph.
    </div>
</div>

<!-- JavaScript to refresh image without full page reload -->
<script>
const refreshInterval = <?= $refresh * 1000 ?>;
function reloadGraph() {
    const img = document.getElementById('realtime-graph');
    // Strip existing rand parameter and add new one
    let src = img.src.replace(/([&?])rand=\d+/, '').replace(/&+$/, '');
    src += (src.includes('?') ? '&' : '?') + 'rand=' + Date.now();
    img.src = src;
}
setInterval(reloadGraph, refreshInterval);
</script>
</body>
</html>
