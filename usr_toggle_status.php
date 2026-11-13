<?php
// ============================================================
// ATIVAR / DESATIVAR USUÁRIO (AJAX · JSON)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';

header('Content-Type: application/json; charset=utf-8');

// ------------------------------------------------------------
// Autenticação
// ------------------------------------------------------------
if (empty($_SESSION['Usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// ------------------------------------------------------------
// Método
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método inválido.']);
    exit;
}

// ------------------------------------------------------------
// CSRF
// ------------------------------------------------------------
$csrfSess = $_SESSION['csrf_usr_list'] ?? '';
$csrfPost = $_POST['csrf_token'] ?? '';
if (!$csrfSess || !hash_equals($csrfSess, $csrfPost)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Token de segurança inválido.']);
    exit;
}

// ------------------------------------------------------------
// Conexão
// ------------------------------------------------------------
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Conexão com o banco não encontrada.']);
    exit;
}

// ------------------------------------------------------------
// Permissão (ajuste conforme sua regra de negócio)
// Ex.: somente 'barreto' ou admin (Permissao <= 2) pode alternar.
// ------------------------------------------------------------
$usuarioLogado = $_SESSION['Usuario'] ?? '';
$ehBarreto     = strtolower($usuarioLogado) === 'barreto';

// Descomente e ajuste se quiser restringir:
/*
if (!$ehBarreto) {
    // Exemplo: consultar permissão na tabela usuarios
    $st = $db->prepare("SELECT Permissao FROM usuarios WHERE Usuario = :u LIMIT 1");
    $st->execute([':u' => $usuarioLogado]);
    $perm = (int)($st->fetchColumn() ?: 999);
    if ($perm > 2) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'Sem permissão para alterar status.']);
        exit;
    }
}
*/

// ------------------------------------------------------------
// Parâmetros
// ------------------------------------------------------------
$id    = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$ativo = ($_POST['ativo'] ?? '') === '1' ? 1 : 0;

if (!$id || $id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
    exit;
}

// ------------------------------------------------------------
// Update
// ------------------------------------------------------------
try {
    $stmt = $db->prepare("UPDATE usuarios SET Ativo = :ativo WHERE UserId = :id");
    $stmt->execute([':ativo' => $ativo, ':id' => $id]);

    if ($stmt->rowCount() === 0) {
        // Pode ser que o valor já fosse o mesmo — não é erro.
        // Vamos checar se o usuário existe:
        $chk = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE UserId = :id");
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'msg' => 'Usuário não encontrado.']);
            exit;
        }
    }

    echo json_encode(['ok' => true, 'ativo' => $ativo, 'id' => $id]);
} catch (PDOException $e) {
    error_log('[TOGGLE_USR] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao atualizar no banco.']);
}