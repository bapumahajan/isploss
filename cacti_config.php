<?php
// cacti_config.php
// Store sensitive Cacti connection info here. Do NOT expose this file publicly.

// Cacti base URL (no trailing slash)
define('CACTI_URL', 'http://103.167.185.254/cacti');

// Dedicated Cacti user (never use admin or guest)
define('CACTI_USERNAME', 'ossuser@ispl');           // Change to your Cacti username
define('CACTI_PASSWORD', 'ossuser@ispl@2021');      // Change to your Cacti password

// Optionally, add other config constants if needed for your environment