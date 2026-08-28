<?php
require_once __DIR__ . '/../conn_cap.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>