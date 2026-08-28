<?php
require_once 'conn_cap.php';
echo "<h2>Setup do Sistema - Rode apenas 1 vez</h2>";

$sql = file_get_contents('setup.sql'); // crie o arquivo abaixo
$conn->exec($sql);

$senha = 'admin123';
$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT IGNORE INTO usuarios (username, password, role) VALUES ('barreto', ?, 'admin')");
$stmt->execute([$hash]);

echo "<h3>✅ Banco e tabelas criados!</h3>";
echo "<p>Usuário ADMIN: <strong>barreto</strong> / Senha: <strong>admin123</strong></p>";
echo "<p><a href='index.php'>Ir para login</a></p>";
?>