<?php
// atualizar_comissao.php
session_start();
require_once 'conn.php';

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Método não permitido");
}

// Verificar parâmetros
if (!isset($_POST['id']) || !isset($_POST['comissaoVenda'])) {
    http_response_code(400);
    die("Parâmetros incompletos");
}

// Validar dados
$id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
$comissaoVenda = filter_var($_POST['comissaoVenda'], FILTER_VALIDATE_FLOAT);

if (!$id || $comissaoVenda === false) {
    die("Dados inválidos");
}

if ($comissaoVenda < 0 || $comissaoVenda > 100) {
    die("Comissão deve estar entre 0 e 100");
}

try {
    // Verificar conexão
    if (!isset($conn)) {
        throw new Exception("Conexão não disponível");
    }
    
    // Atualizar no banco
    $sql = "UPDATE Empreendimento SET ComissaoVenda = :comissao WHERE Empreend_ID = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':comissao', $comissaoVenda, PDO::PARAM_STR);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // RETORNAR "success" (não "ok")
        echo "success";
    } else {
        echo "Erro ao executar query";
    }
    
} catch (PDOException $e) {
    echo "Erro no banco de dados: " . $e->getMessage();
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}

exit();
?>