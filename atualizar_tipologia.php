<?php
// atualizar_tipologia.php
require_once 'conn.php';

// Receber dados (mantendo compatibilidade com o ASP)
$id = $_POST['id'] ?? 0;
$tipologia = $_POST['tipologia'] ?? '';

// Converter tipos
$id = intval($id);
$tipologia = trim($tipologia);

try {
    // SQL idêntico ao ASP: atualiza APENAS o campo Tipologia
    $sql = "UPDATE Empreendimento SET Tipologia = :tipologia WHERE Empreend_ID = :id";
    
    // Preparar e executar
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tipologia' => $tipologia,
        ':id' => $id
    ]);
    
    // Resposta exatamente igual ao ASP
    echo "ok";
    
} catch (PDOException $e) {
    // Em caso de erro, também retorna texto simples
    echo "Erro";
}
?>