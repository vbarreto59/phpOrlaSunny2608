<?php
session_start();
require_once 'conn.php';

$id = (int)($_POST['id'] ?? 0);
$localizacao = trim($_POST['localizacao'] ?? '');
$linkmapa = trim($_POST['linkmapa'] ?? '');

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("UPDATE Empreendimento SET Localizacao = ?, LinkMapa = ? WHERE Empreend_ID = ?");
    $result = $stmt->execute([$localizacao, $linkmapa, $id]);
    
    echo json_encode([
        'success' => 'OK',
        'message' => 'Atualizado com sucesso!',
        'rows_affected' => $stmt->rowCount()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => 'ERROR',
        'message' => 'Erro ao atualizar: ' . $e->getMessage()
    ]);
}
?>