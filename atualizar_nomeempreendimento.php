<?php
require_once 'conn.php';
session_start();

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo "Método não permitido";
    exit();
}

// Receber os dados via POST
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';

// Validações
if ($id <= 0) {
    http_response_code(400); // Bad Request
    echo "ID inválido";
    exit();
}

if (empty($nome)) {
    http_response_code(400); // Bad Request
    echo "Nome não pode estar vazio";
    exit();
}

try {
    // Preparar e executar a query
    $sql = "UPDATE Empreendimento SET NomeEmpreendimento = :nome WHERE Empreend_ID = :id";
    $stmt = $pdo->prepare($sql);
    
    $stmt->bindParam(':nome', $nome, PDO::PARAM_STR);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    // Executar a atualização
    $stmt->execute();
    
    // Verificar se alguma linha foi afetada
    if ($stmt->rowCount() > 0) {
        // Sucesso
        echo "ok";
        
        // Opcional: registrar log ou histórico
        // registrarHistorico($id, "Atualização do nome do empreendimento para: $nome");
    } else {
        // Nenhuma linha afetada (ID não encontrado ou mesmo valor)
        echo "ok"; // Ou pode retornar "nenhuma_alteracao" se preferir
    }
    
} catch (PDOException $e) {
    // Erro no banco de dados
    http_response_code(500); // Internal Server Error
    echo "Erro ao atualizar: " . $e->getMessage();
    // Para produção, não mostre a mensagem de erro completa:
    // echo "Erro ao atualizar. Tente novamente mais tarde.";
}
?>