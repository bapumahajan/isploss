<?php
session_name('oss_portal');
session_start();

require_once 'cacti_config.php';
// ... your DB connect and token validation code here ...

// Example for token-based access:
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    exit('Access denied');
}
if (empty($_GET['token']) || !isset($_SESSION['cacti_tokens'][$_GET['token']])) {
    http_response_code(403);
    exit('Invalid token');
}
$local_graph_id = (int)$_SESSION['cacti_tokens'][$_GET['token']];

// Build Cacti graph URL
$graph_url = rtrim(CACTI_URL, '/') . "/graph_image.php?action=view&local_graph_id={$local_graph_id}&rra_id=all";

// For HTTP Basic Auth (rare for Cacti; see below for form login)
$opts = [
    'http' => [
        'header' => "Authorization: Basic " . base64_encode(CACTI_USERNAME . ":" . CACTI_PASSWORD)
    ]
];
$context = stream_context_create($opts);

// Fetch the image
$image_data = file_get_contents($graph_url, false, $context);
if ($image_data === false) {
    http_response_code(502);
    exit('Could not fetch image');
}
header('Content-Type: image/png');
header('Cache-Control: no-cache');
echo $image_data;
exit;