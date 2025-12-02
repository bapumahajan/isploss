<?php
session_name('oss_portal');  // must match dashboard.php
session_start();

if (!isset($_SESSION['test'])) {
    $_SESSION['test'] = 0;
}
$_SESSION['test']++;

echo "Session test count: " . $_SESSION['test'] . "<br>";
echo "Session name: " . session_name() . "<br>";
echo "Session ID: " . session_id() . "<br>";
?>
