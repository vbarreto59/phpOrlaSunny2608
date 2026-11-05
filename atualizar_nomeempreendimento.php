<?php
// ============================================================
// Endpoint: atualizar_nomeempreendimento.php
// Retorna SEMPRE JSON: { "success": bool, "message": string }
// ============================================================

// Captura qualquer saída acidental (warning, notice, BOM)
ob_start();

// Resposta padrão
header('Content-Type: application/json; charset=utf-8');

function responder($success, $message, $extra = []) {
    // Limpa qualquer output acidental (warnings, notices, echoes)
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// Registra erros em arquivo, mas NUNCA imprime na tela
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/erros_nomeempreend.log');

// Session (precisa antes de qualquer coisa se for usar $_SESSION)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// 1) Valida método
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método inválido. Use POST.');
}

// ============================================================
// 2) Valida entrada
// ============================================================
$id   = isset($_POST['id'])   ? trim((string)$_POST['id'])   : '';
$nome = isset($_POST['nome']) ? trim((string)$_POST['nome']) : '';

if ($id === '' || !ctype_digit($id)) {
    responder(false, 'ID inválido ou ausente.');
}
if ($nome === '') {
    responder(false, 'O nome não pode estar vazio.');
}
if (mb_strlen($nome) > 255) {
    responder(false, 'O nome excede 255 caracteres.');
}

// ============================================================
// 3) Conexão
// ============================================================
try {
    require_once __DIR__ . '/conn.php';

    if (isset($conn) && $conn instanceof PDO) {
        $db = $conn;
    } elseif (isset($pdo) && $pdo instanceof PDO) {
        $db = $pdo;
    } else {
        responder(false, 'Conexão com banco não encontrada (verifique conn.php).');
    }
} catch (Throwable $e) {
    error_log('[NOMEEMP] Falha ao incluir conn.php: ' . $e->getMessage());
    responder(false, 'Erro interno ao conectar ao banco.');
}

// ============================================================
// 4) Verifica se o registro existe
// ============================================================
try {
    $stmt = $db->prepare("SELECT Empreend_ID, NomeEmpreendimento FROM empreendimento WHERE Empreend_ID = :id");
    $stmt->execute([':id' => (int)$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        responder(false, 'Empreendimento não encontrado (ID ' . $id . ').');
    }

    $nomeAntigo = $row['NomeEmpreendimento'];

    if ($nomeAntigo === $nome) {
        responder(true, 'Nenhuma alteração necessária (o nome já era esse).', [
            'id' => (int)$id,
            'nome_anterior' => $nomeAntigo,
            'nome_novo' => $nome
        ]);
    }
} catch (PDOException $e) {
    error_log('[NOMEEMP] Erro SELECT: ' . $e->getMessage());
    responder(false, 'Erro ao consultar o registro.');
}

// ============================================================
// 5) Update
// ============================================================
try {
    $stmt = $db->prepare("UPDATE empreendimento SET NomeEmpreendimento = :nome WHERE Empreend_ID = :id");
    $stmt->execute([
        ':nome' => $nome,
        ':id'   => (int)$id
    ]);

    if ($stmt->rowCount() === 0) {
        // Pode ser 0 se o valor for idêntico (alguns drivers) — tratamos como OK
        error_log('[NOMEEMP] rowCount=0 para ID ' . $id . ' (possível nome idêntico)');
    }
} catch (PDOException $e) {
    error_log('[NOMEEMP] Erro UPDATE: ' . $e->getMessage());
    responder(false, 'Erro ao atualizar o registro: ' . $e->getMessage());
}

// ============================================================
// 6) Histórico (opcional — se a tabela existir)
// ============================================================
try {
    $userId  = $_SESSION['UserId']  ?? 0;
    $usuario = $_SESSION['usuario'] ?? 'Sistema';

    $stmt = $db->prepare("INSERT INTO histatulog (IdEmpreend, IdUser, Usuario, Historico) 
                          VALUES (:idEmp, :idUser, :usuario, :historico)");
    $stmt->execute([
        ':idEmp'     => (int)$id,
        ':idUser'    => $userId,
        ':usuario'   => $usuario,
        ':historico' => "Nome alterado de '{$nomeAntigo}' para '{$nome}'"
    ]);
} catch (Throwable $e) {
    // Se a tabela histatulog não existir ou tiver colunas diferentes, ignoramos
    error_log('[NOMEEMP] Histórico não registrado: ' . $e->getMessage());
}

// ============================================================
// 7) Sucesso
// ============================================================
responder(true, 'Nome atualizado com sucesso.', [
    'id' => (int)$id,
    'nome_anterior' => $nomeAntigo,
    'nome_novo' => $nome
]);