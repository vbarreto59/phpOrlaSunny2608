<?php
// pages/proprietarios/delete.php
session_start();

// 1. Verificações de Segurança
require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// 2. Validação do ID
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: list.php?erro=" . urlencode("ID de proprietário inválido."));
    exit;
}

try {
    // 3. Verificação de Vínculos (Impede a exclusão se houver imóveis ativos)
    // Isso evita que imóveis fiquem "sem dono" no sistema.
    $sql_check = "SELECT COUNT(*) FROM imoveis WHERE proprietario_id = ? AND deleted_at IS NULL";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$id]);
    $total_imoveis = $stmt_check->fetchColumn();

    if ($total_imoveis > 0) {
        $msg = "Não é possível excluir: este proprietário possui $total_imoveis imóvel(is) vinculado(s).";
        header("Location: list.php?erro=" . urlencode($msg));
        exit;
    }

    // 4. Execução da Exclusão Lógica
    // Em vez de DELETE, usamos UPDATE no campo deleted_at
    $sql_delete = "UPDATE proprietarios SET deleted_at = NOW() WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->execute([$id]);

    if ($stmt_delete->rowCount() > 0) {
        // Opcional: Registrar no log de atividades do sistema
        // log_atividade($_SESSION['user_id'], 'excluir', 'proprietarios', $id);

        header("Location: list.php?sucesso=" . urlencode("Proprietário removido com sucesso!"));
    } else {
        header("Location: list.php?erro=" . urlencode("Proprietário não encontrado ou já excluído."));
    }

} catch (PDOException $e) {
    // Tratamento de erro de banco de dados
    error_log("Erro ao excluir proprietário: " . $e->getMessage());
    header("Location: list.php?erro=" . urlencode("Erro interno ao processar a exclusão."));
}
exit;