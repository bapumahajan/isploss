<?php
if (!isset($_GET['activation_date']) || !isset($_GET['billing_type'])) {
    exit('Invalid');
}
$date = new DateTime($_GET['activation_date']);
switch (strtolower($_GET['billing_type'])) {
    case 'monthly': $date->modify('+1 month'); break;
    case 'quarterly': $date->modify('+3 months'); break;
    case 'half-yearly': $date->modify('+6 months'); break;
    case 'yearly': $date->modify('+1 year'); break;
    default: exit('Invalid Type');
}
echo $date->format('d-M-Y');
