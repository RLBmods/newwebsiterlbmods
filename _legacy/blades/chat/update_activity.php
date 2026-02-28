<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/session.php';
require_once '../../db/connection.php';

if (isset($_SESSION['user_id'])) {
    $con->query("UPDATE usertable SET last_activity = NOW() WHERE id = {$_SESSION['user_id']}");
}
?>