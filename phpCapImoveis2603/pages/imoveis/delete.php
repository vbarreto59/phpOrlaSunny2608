<?php
session_start();

require_once '../../includes/auth.php';
require_once '../../conn_cap.php';
require_once '../../includes/functions.php';

// Verifica se o ID foi passado
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // Opcional: Verificar se o proprietário tem imóveis ativos antes de deletar
        // Se preferir impedir a exclusão de quem tem imóveis, descomente as linhas abaixo:
        /*
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM imoveis WHERE proprietario_id = ? AND deleted_at IS NULL");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            header("Location: list.php?erro=Não é possível excluir um proprietário que possui imóveis ativos.");
            exit;
        }
        */

        // Executa o Soft Delete (Atualiza a data de exclusão em vez de apagar o registro)
        $sql = "UPDATE proprietarios SET deleted_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            // Registra no histórico (se sua função log_historico suportar proprietários)
            if (function_exists('log_historico')) {
                log_historico($id, 'excluir_proprietario', "Proprietário ID $id movido para a lixeira.");
            }
            
            header("Location: list.php?sucesso=Proprietário excluído com sucesso!");
        } else {
            header("Location: list.php?erro=Proprietário não encontrado ou já excluído.");
        }

    } catch (PDOException $e) {
        header("Location: list.php?erro=Erro ao excluir: " . urlencode($e->getMessage()));
    }
} else {
    header("Location: list.php?erro=ID inválido.");
}
exit;