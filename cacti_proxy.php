<?php
session_name('oss_portal');
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Security headers (recommended)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=()');

// Authentication check
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo "Forbidden: Not authenticated";
    exit;
}

// Validate token format
if (empty($_GET['token']) || !preg_match('/^[a-f0-9]{32}$/', $_GET['token'])) {
    http_response_code(400);
    echo "Invalid token format";
    exit;
}

// Validate token in session
$token = $_GET['token'];
if (!isset($_SESSION['cacti_tokens'][$token])) {
    http_response_code(403);
    echo "Invalid or expired token";
    exit;
}
$local_graph_id = (int)$_SESSION['cacti_tokens'][$token];

// Cacti credentials (use a dedicated, limited-permission user)
$cacti_user = "apiuser";      // <-- CHANGE THIS!
$cacti_pass = "Ispl@2025";    // <-- CHANGE THIS!

// Step 1: Login to Cacti and capture session cookie
$login_url = "http://103.167.185.254/cacti/index.php";
$graph_url = "http://103.167.185.254/cacti/graph_image.php?local_graph_id=$local_graph_id&rra_id=all";

// Temporary file to store cookies
$cookiejar = tempnam(sys_get_temp_dir(), "cacti_cookie");

// POST fields for Cacti login
$post_fields = http_build_query([
    "action" => "login",
    "login_username" => $cacti_user,
    "login_password" => $cacti_pass
]);

// Login to Cacti
$ch = curl_init($login_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$login_result = curl_exec($ch);
curl_close($ch);

// Debug: Uncomment to view login response if troubleshooting
// echo "LOGIN RESULT:\n" . substr($login_result, 0, 1000);

// Step 2: Fetch graph image using session cookie
$ch = curl_init($graph_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$image = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Remove temp cookie file
unlink($cookiejar);

// Output image according to format
if ($http_code == 200 && $image) {
    if (strpos($content_type, 'svg+xml') !== false || (strpos($image, '<svg') !== false)) {
        header('Content-Type: image/svg+xml');
        echo $image;
    } elseif (substr($image, 0, 4) === "\x89PNG") {
        header('Content-Type: image/png');
        echo $image;
    } elseif (substr($image, 0, 2) === "\xFF\xD8") {
        header('Content-Type: image/jpeg');
        echo $image;
    } else {
        header('Content-Type: text/plain');
        echo "Unsupported image format\n";
        echo "Content-Type: $content_type\n";
        echo "First 500 bytes:\n";
        echo substr($image, 0, 500);
    }
} else {
    header('Content-Type: text/plain');
    echo "DEBUG: Could not fetch valid image\n";
    echo "HTTP code: $http_code\n";
    echo "Cacti URL: $graph_url\n";
    echo "Content-Type: $content_type\n";
    echo "Image length: " . ($image !== false ? strlen($image) : 'false') . "\n";
    echo "First 500 chars/bytes of output:\n";
    if ($image !== false) {
        echo substr($image, 0, 500);
    } else {
        echo "No output received from Cacti\n";
    }
}
?>