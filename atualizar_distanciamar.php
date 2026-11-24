<?php
/**
 * atualizar_distanciamar.php
 * Endpoint AJAX — salva DistanciaMar (INTEGER), BeiraMar (auto) e ProximoMarDetalhe.
 * Regra: DistanciaMar <= 10  →  BeiraMar = 1
 *
 * Sempre retorna JSON. Nunca retorna vazio.
 */

// ---------- Garantia de JSON + captura de erros ----------
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/erros_distanciamar.log');
error_reporting(E_ALL);

ob_start(); // amortece qualquer output indesejado

register_shutdown_function(function () {
    $last = error_get_last();
    if ($last && in_array($last['type'],
        [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        while (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
        }
        echo json_encode([
            'success' => false,
            'message' => 'FATAL: ' . $last['message'] .
                         ' em ' . basename($last['file']) . ':' . $last['line'],
        ]);
    }
});

function respond($ok, $msg, $extra = []) {
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true, 200);
    }
    echo json_encode(array_merge(
        ['success' => (bool)$ok, 'message' => (string)$msg],
        $extra
    ));
    exit;
}

// ---------- Carrega conexão ----------
if (!file_exists(__DIR__ . '/conn.php')) {
    respond(false, 'conn.php não encontrado em ' . __DIR__);
}

try {
    require_once __DIR__ . '/conn.php';
} catch (Throwable $e) {
    respond(false, 'Erro ao carregar conn.php: ' . $e->getMessage());
}

$pdoCon = null;
if (isset($conn) && $conn instanceof PDO)     $pdoCon = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)   $pdoCon = $pdo;
elseif (isset($db) && $db instanceof PDO)     $pdoCon = $db;

if (!$pdoCon instanceof PDO) {
    respond(false, 'Nenhuma conexão PDO encontrada em conn.php (esperado $conn, $pdo ou $db).');
}

// ---------- Regra de negócio ----------
const LIMITE_BEIRA_MAR_METROS = 10;

// ---------- Entrada ----------
$id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$distRaw = isset($_POST['distancia']) ? trim((string)$_POST['distancia']) : '';
$detalhe = isset($_POST['detalhe']) ? trim((string)$_POST['detalhe']) : '';

if ($id <= 0) {
    respond(false, 'ID inválido.');
}

// Distância agora é INTEGER: vazio → NULL; senão inteiro >= 0
$dist = null;
if ($distRaw !== '') {
    if (!preg_match('/^\d+$/', $distRaw)) {
        respond(false, 'Distância deve ser um número inteiro ≥ 0.');
    }
    $dist = (int)$distRaw;
    if ($dist < 0) {
        respond(false, 'Distância não pode ser negativa.');
    }
}

// BeiraMar auto-calculado
$beiraMar = ($dist !== null && $dist <= LIMITE_BEIRA_MAR_METROS) ? 1 : 0;

// Detalhe: vazio → NULL; limite de tamanho do schema
if ($detalhe === '') {
    $detalheVal = null;
} else {
    if (mb_strlen($detalhe, 'UTF-8') > 255) {
        respond(false, 'O texto de "Próximo Mar (Detalhe)" excede 255 caracteres.');
    }
    $detalheVal = $detalhe;
}

// ---------- Update ----------
try {
    $sql = "UPDATE empreendimento
               SET DistanciaMar      = :dist,
                   BeiraMar          = :bm,
                   ProximoMarDetalhe = :det
             WHERE Empreend_ID       = :id";

    $stmt = $pdoCon->prepare($sql);
    $stmt->execute([
        ':dist' => $dist,
        ':bm'   => $beiraMar,
        ':det'  => $detalheVal,
        ':id'   => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        // Pode ser "nenhum campo mudou" (MySQL não conta como update).
        // Confere se o registro existe para diferenciar.
        $chk = $pdoCon->prepare("SELECT COUNT(*) FROM empreendimento WHERE Empreend_ID = :id");
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() === 0) {
            respond(false, "Empreendimento #$id não encontrado.");
        }
    }

    $msg = $dist === null
        ? 'Distância removida'
        : ($beiraMar
            ? 'Distância salva — Beira Mar ✓'
            : 'Distância salva');

    respond(true, $msg, [
        'beira_mar' => $beiraMar,
        'distancia' => $dist,
    ]);

} catch (PDOException $e) {
    @file_put_contents(
        __DIR__ . '/erros_distanciamar.log',
        '[' . date('d/m/Y H:i:s') . '] PDO: ' . $e->getMessage() . "\n",
        FILE_APPEND
    );
    respond(false, 'Erro ao salvar: ' . $e->getMessage());
} catch (Throwable $e) {
    @file_put_contents(
        __DIR__ . '/erros_distanciamar.log',
        '[' . date('d/m/Y H:i:s') . '] FATAL: ' . $e->getMessage() . "\n",
        FILE_APPEND
    );
    respond(false, 'Erro inesperado: ' . $e->getMessage());
}