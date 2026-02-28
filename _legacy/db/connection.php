<?php

require_once __DIR__ . '/../config.php';

// Create MySQLi database connection
$con = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check MySQLi connection
if ($con->connect_error) {
    die("MySQLi Database connection failed: " . $con->connect_error);
}

// Create PDO database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO Database connection failed: " . $e->getMessage());
}


// Fetch Site Settings
$sql = "SELECT * FROM site_settings LIMIT 1";
$result = mysqli_query($con, $sql);
$row = mysqli_fetch_assoc($result);

$site_name = $row['site_name'];
$site_domain = $row['site_domain'];
$site_logo = $row['logo'];
$copyright = $row['copyright'];
$site_icon = $row['favicon'];