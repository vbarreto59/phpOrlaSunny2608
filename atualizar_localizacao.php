<?php
// ============================================================
// Endpoint: atualizar_localizacao.php
// Retorna SEMPRE JSON: { success: bool, status: 'OK'|'ERROR', message, ... }
// ============================================================

ob_start(); // captura qualquer output parasita

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/erros_localizacao.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// Resposta unificada (mantém "OK" legado para compatibilidade)
// ============================================================
function responder($success, $message, $extra = []) {
    if (ob_get_length()) ob_clean();
    echo json_encode(array_merge([
        'success' => (bool)$success,
        'status'  => $success ? 'OK' : 'ERROR', // legado
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 1) Método
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método inválido. Use POST.');
}

// ============================================================
// 2) Entrada
// ============================================================
$id          = isset($_POST['id'])          ? (int)$_POST['id']              : 0;
$localizacao = isset($_POST['localizacao']) ? trim((string)$_POST['localizacao']) : '';
$linkmapa    = isset($_POST['linkmapa'])    ? trim((string)$_POST['linkmapa'])    : '';

if ($id <= 0) {
    responder(false, 'ID inválido ou ausente.');
}

// Normalizações
$localizacao = mb_strtoupper($localizacao); // consistência com Localidade
// LinkMapa NÃO é uppercased — URLs são case-sensitive

if (mb_strlen($localizacao) > 255) {
    responder(false, 'A localização excede 255 caracteres.');
}
if (mb_strlen($linkmapa) > 500) {
    responder(false, 'O link do mapa excede 500 caracteres.');
}

// ============================================================
// 3) Conexão
// ============================================================
try {
    require_once __DIR__ . '/conn.php';

    if (isset($conn) && $conn instanceof PDO)      $db = $conn;
    elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
    else responder(false, 'Conexão com banco não encontrada (verifique conn.php).');
} catch (Throwable $e) {
    error_log('[LOCALIZACAO] Falha em conn.php: ' . $e->getMessage());
    responder(false, 'Erro interno ao conectar ao banco.');
}

// ============================================================
// 4) Verifica existência + valor atual
// ============================================================
try {
    $stmt = $db->prepare("SELECT Localizacao, LinkMapa FROM empreendimento WHERE Empreend_ID = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        responder(false, 'Empreendimento não encontrado (ID ' . $id . ').');
    }

    $locAtual = (string)($row['Localizacao'] ?? '');
    $mapAtual = (string)($row['LinkMapa'] ?? '');

    if (mb_strtoupper($locAtual) === $localizacao && $mapAtual === $linkmapa) {
        responder(true, 'Nenhuma alteração necessária.', [
            'id' => $id,
            'rows_affected' => 0,
            'localizacao_anterior' => $locAtual,
            'linkmapa_anterior'    => $mapAtual
        ]);
    }
} catch (PDOException $e) {
    error_log('[LOCALIZACAO] SELECT: ' . $e->getMessage());
    responder(false, 'Erro ao consultar o registro.');
}

// ============================================================
// 5) Update
// ============================================================
try {
    $stmt = $db->prepare("UPDATE empreendimento SET Localizacao = :loc, LinkMapa = :map WHERE Empreend_ID = :id");
    $stmt->execute([
        ':loc' => $localizacao,
        ':map' => $linkmapa,
        ':id'  => $id
    ]);
    $rows = $stmt->rowCount();
} catch (PDOException $e) {
    error_log('[LOCALIZACAO] UPDATE: ' . $e->getMessage());
    responder(false, 'Erro ao atualizar: ' . $e->getMessage());
}

// ============================================================
// 6) Histórico (opcional)
// ============================================================
try {
    $userId  = $_SESSION['UserId']  ?? 0;
    $usuario = $_SESSION['usuario'] ?? 'Sistema';

    $mudancas = [];
    if (mb_strtoupper($locAtual) !== $localizacao) {
        $mudancas[] = "Localização: '{$locAtual}' → '{$localizacao}'";
    }
    if ($mapAtual !== $linkmapa) {
        $mudancas[] = "LinkMapa atualizado";
    }

    if (!empty($mudancas)) {
        $stmt = $db->prepare("INSERT INTO histatulog (IdEmpreend, IdUser, Usuario, Historico)
                              VALUES (:idEmp, :idUser, :usuario, :historico)");
        $stmt->execute([
            ':idEmp'     => $id,
            ':idUser'    => $userId,
            ':usuario'   => $usuario,
            ':historico' => implode(' | ', $mudancas)
        ]);
    }
} catch (Throwable $e) {
    error_log('[LOCALIZACAO] Histórico: ' . $e->getMessage());
}

// ============================================================
// 7) Sucesso
// ============================================================
responder(true, 'Atualizado com sucesso!', [
    'id' => $id,
    'rows_affected' => $rows,
    'localizacao_anterior' => $locAtual,
    'linkmapa_anterior'    => $mapAtual,
    'localizacao_nova'     => $localizacao,
    'linkmapa_novo'        => $linkmapa
]);