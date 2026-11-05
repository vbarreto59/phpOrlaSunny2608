<?php
// atualizar_tipologia.php — versão com suporte a JSON + compatibilidade "ok"
require_once 'conn.php';

// --- Captura qualquer output parasita (warning/notice/BOM) ---
ob_start();

// --- Detecta o que o cliente prefere ---
//    Front novo envia header X-Requested-With: XMLHttpRequest + Accept: application/json
//    Front antigo (seu código atual) não envia nada → cai no echo "ok"
$querJson = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

if ($querJson) {
    header('Content-Type: application/json; charset=utf-8');
}

// --- Resposta unificada ---
function responder($success, $message, $extra = []) {
    global $querJson;
    if (ob_get_length()) ob_clean();

    if ($querJson) {
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message
        ], $extra), JSON_UNESCAPED_UNICODE);
    } else {
        // Compatibilidade com o ASP antigo / cliente que espera "ok"
        echo $success ? 'ok' : 'Erro';
    }
    exit;
}

// --- Conexão: aceita $conn ou $pdo ---
if (isset($conn) && $conn instanceof PDO)     $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)   $db = $pdo;
else                                          responder(false, 'Conexão não encontrada em conn.php.');

// --- Validação ---
$id        = isset($_POST['id'])        ? intval($_POST['id'])              : 0;
$tipologia = isset($_POST['tipologia']) ? trim((string)$_POST['tipologia']) : '';

if ($id <= 0) {
    responder(false, 'ID inválido ou ausente.');
}
if ($tipologia === '') {
    responder(false, 'A tipologia não pode estar vazia.');
}
if (mb_strlen($tipologia) > 100) {
    responder(false, 'A tipologia excede 100 caracteres.');
}

// Normaliza para maiúsculas (mesmo padrão que você já usa no banco)
$tipologia = mb_strtoupper($tipologia);

// --- Verifica existência e valor atual ---
try {
    $stmt = $db->prepare("SELECT Tipologia FROM Empreendimento WHERE Empreend_ID = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        responder(false, 'Empreendimento não encontrado (ID ' . $id . ').');
    }

    $tipAtual = (string)($row['Tipologia'] ?? '');

    if (mb_strtoupper($tipAtual) === $tipologia) {
        responder(true, 'Nenhuma alteração necessária (a tipologia já era essa).', [
            'id' => $id,
            'tipologia_anterior' => $tipAtual,
            'tipologia_nova'     => $tipologia
        ]);
    }
} catch (PDOException $e) {
    error_log('[TIPOLOGIA] SELECT: ' . $e->getMessage());
    responder(false, 'Erro ao consultar o registro.');
}

// --- Update ---
try {
    $stmt = $db->prepare("UPDATE Empreendimento SET Tipologia = :tipologia WHERE Empreend_ID = :id");
    $stmt->execute([
        ':tipologia' => $tipologia,
        ':id'        => $id
    ]);
} catch (PDOException $e) {
    error_log('[TIPOLOGIA] UPDATE: ' . $e->getMessage());
    responder(false, 'Erro ao atualizar: ' . $e->getMessage());
}

// --- Sucesso ---
responder(true, 'Tipologia atualizada com sucesso.', [
    'id' => $id,
    'tipologia_anterior' => $tipAtual,
    'tipologia_nova'     => $tipologia
]);