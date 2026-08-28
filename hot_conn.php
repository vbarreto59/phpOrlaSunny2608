<?php
// Configurações de conexão
$host = "189.1.1.185";
$usuario = "cli213_vbarreto";
$senha = "P@nk2025";
$banco = "cli213_gabnet1";

// 1. Conectar ao banco de dados usando MySQLi
$conn = new mysqli($host, $usuario, $senha, $banco);

// 2. Verificar se a conexão falhou
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// 3. Ajustar o charset para utf8mb4 (evita erros de acentuação vindos do banco)
$conn->set_charset("utf8mb4");

// Opcional: Remover o comentário abaixo para testar a conexão
// echo "Conectado com sucesso!";
?>