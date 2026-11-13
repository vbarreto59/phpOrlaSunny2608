<?php
// usr_excluir_ajax.php — exclusão via AJAX (JSON)
session_start();
require_once 'conn.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Sessão expirada.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método inválido.']);
    exit;
}

// CSRF
$csrfSess = $_SESSION['csrf_usr_list'] ?? '';
$csrfPost = $_POST['csrf_token'] ?? '';
if (!$csrfSess || !hash_equals($csrfSess, $csrfPost)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Token de segurança inválido.']);
    exit;
}

// Conexão
if (isset($conn) && $conn instanceof PDO)   $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO) $db = $pdo;
else { http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Sem conexão.']); exit; }

// Permissão (ajuste: só superadmin/admin, ou 'barreto')
$usuarioLogado = $_SESSION['Usuario'] ?? '';
$ehBarreto     = strtolower($usuarioLogado) === 'barreto';
$perm          = (int)($_SESSION['Permissao'] ?? 999);
if (!$ehBarreto && $perm > 2) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sem permissão para excluir.']);
    exit;
}

// ID
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
    exit;
}

try {
    $db->beginTransaction();

    $chk = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE UserId = :id");
    $chk->execute([':id' => $id]);
    if ((int)$chk->fetchColumn() === 0) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Usuário já foi excluído.']);
        exit;
    }

    $db->prepare("DELETE FROM usuario_grupo WHERE UserId = :id")->execute([':id' => $id]);
    $db->prepare("DELETE FROM usuarios WHERE UserId = :id")->execute([':id' => $id]);

    $db->commit();
    echo json_encode(['ok' => true, 'id' => $id]);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[USR_DEL] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao excluir no banco.']);
}