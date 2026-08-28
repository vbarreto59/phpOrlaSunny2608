<?php
// =================================================
//  17 01 2026 - Usando PDO para conectar. 
// Configurações de conexão
// =================================================

// Configurações de conexão - DESENVOLVIMENTO
$host = "localhost";
$usuario = "root";
$senha = "caio";
$banco = "SunnyImob2026";

/*
// Configurações de conexão - PRODUÇÃO
$host = "189.1.1.185";
$usuario = "cli213_vbarreto";
$senha = "P@nk2025";
$banco = "cli213_gabnet1";
*/

// 1. Conectar ao banco de dados usando PDO
try {
    // Definindo o DSN (Data Source Name) com charset utf8mb4
    $dsn = "mysql:host=$host;dbname=$banco;charset=utf8mb4";
    
    // Configurações extras para segurança e performance
    $opcoes = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Criando a instância da conexão 
    $conn = new PDO($dsn, $usuario, $senha, $opcoes);

    $pdo = $conn; // Criar alias para compatibilidade
    
    // Configurar timezone
    $conn->exec("SET time_zone = '-03:00'");

} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Erro de conexão PDO: " . $e->getMessage());
    
    if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
        die("<h2>Erro de Conexão</h2><p>Erro: " . htmlspecialchars($e->getMessage()) . "</p>");
    } else {
        die("<h2>Sistema Temporariamente Indisponível</h2><p>Tente novamente em alguns minutos.</p>");
    }
}

// Funções de apoio
function GetOpenConnectionLog() {
    global $conn;
    return $conn;
}

function CloseConnection(&$conexao) {
    $conexao = null;
}
?>