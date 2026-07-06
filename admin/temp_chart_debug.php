<?php
session_start();
// Debug script to inspect the AJAX output of sales_chart.php without web server.
$_SESSION['user_id'] = 1;
$_GET = ['ajax' => 1, 'period' => '1month', 'month' => 8];
include 'sales_chart.php';
