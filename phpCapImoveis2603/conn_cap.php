<?php
if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['SERVER_ADDR'] === '127.0.0.1') {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'sistema_captacao');
    define('DB_USER', 'root');
    define('DB_PASS', 'caio');
} else {
    define('DB_HOST', '189.1.1.185');
    define('DB_NAME', 'cli213_sistema_captacao');
    define('DB_USER', 'cli213_vbarreto');
    define('DB_PASS', 'P@nk2025');
}

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>