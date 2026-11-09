<?php
// session_start() PRIMEIRO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'send_email.php';
require_once 'theme_config.php';

if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}
$loggedUser = $_SESSION['Usuario'];

// --- Conexão ---
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

// --- Detecta o nome real da coluna de ID em `usuarios` ---
$userCol = 'UserId';
try {
    $cols = $db->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('UserId', $cols, true) && in_array('UserID', $cols, true)) {
        $userCol = 'UserID';
    }
} catch (Exception $e) { /* mantém default */ }

// --- Usuário "barreto" pode excluir ---
$isBarreto = (mb_strtolower(trim($loggedUser)) === 'barreto');

// ============================================================
// ENDPOINT AJAX: exclusão em lote (CORRIGIDO)
// ============================================================
if ($isBarreto && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_users') {
    header('Content-Type: application/json; charset=utf-8');

    // Log de diagnóstico
    error_log("[DELETE_USERS] iniciado — user={$loggedUser}, method={$_SERVER['REQUEST_METHOD']}, ids=" . json_encode($_POST['ids'] ?? []));

    try {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));

        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum usuário selecionado.']);
            exit;
        }

        $totalRequested = count($ids);

        // ── Proteção do "barreto" ──
        $ph0 = implode(',', array_fill(0, count($ids), '?'));
        $barretoStmt = $db->prepare("SELECT $userCol FROM usuarios WHERE $userCol IN ($ph0) AND LOWER(TRIM(Usuario)) = 'barreto'");
        $barretoStmt->execute($ids);
        $barretoIds = $barretoStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($barretoIds)) {
            $ids = array_values(array_diff($ids, array_map('intval', $barretoIds)));
            error_log("[DELETE_USERS] barreto protegido, removidos dos ids: " . json_encode($barretoIds));
            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'O usuário "barreto" é protegido e não pode ser excluído.']);
                exit;
            }
        }

        $ph = implode(',', array_fill(0, count($ids), '?'));

        $db->beginTransaction();
        try {
            // ── 1) Tabela de relacionamento usuario_grupo (evita FK constraint) ──
            $ugCleaned = false;
            foreach (['UserId', 'UserID', 'userid'] as $ugCol) {
                try {
                    $db->prepare("DELETE FROM usuario_grupo WHERE `$ugCol` IN ($ph)")->execute($ids);
                    $ugCleaned = true;
                    break;
                } catch (Exception $e) {
                    // tenta próximo nome de coluna
                }
            }
            if (!$ugCleaned) {
                error_log("[DELETE_USERS] Aviso: não foi possível limpar usuario_grupo (tabela/coluna pode não existir)");
            }

            // ── 2) Diretorias onde o usuário é diretor ──
            try {
                $db->prepare("DELETE FROM diretorias WHERE UserId IN ($ph)")->execute($ids);
            } catch (Exception $e) {
                error_log("[DELETE_USERS] diretorias cleanup: " . $e->getMessage());
            }

            // ── 3) Gerências onde o usuário é gerente ──
            try {
                $db->prepare("DELETE FROM gerencias WHERE UserId IN ($ph)")->execute($ids);
            } catch (Exception $e) {
                error_log("[DELETE_USERS] gerencias cleanup: " . $e->getMessage());
            }

            // ── 4) O usuário em si — SEM restrição de IdEmp (cross-empresa) ──
            $stmt = $db->prepare("DELETE FROM usuarios WHERE $userCol IN ($ph)");
            $stmt->execute($ids);
            $deleted = $stmt->rowCount();

            $db->commit();

            error_log("[DELETE_USERS] OK — requested={$totalRequested}, deleted={$deleted}, ids=" . json_encode($ids));

            // ── Mensagem de retorno ──
            $success = ($deleted > 0);
            if ($deleted === $totalRequested) {
                $message = $deleted . ' usuário(s) excluído(s) com sucesso.';
            } elseif ($deleted > 0) {
                $message = $deleted . ' de ' . $totalRequested . ' usuário(s) excluído(s). Os demais não foram encontrados.';
            } else {
                $message = 'Nenhum usuário foi excluído (0 linhas afetadas). IDs: ' . implode(', ', $ids);
            }

            echo json_encode([
                'success'   => $success,
                'message'   => $message,
                'deleted'   => $deleted,
                'requested' => $totalRequested
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        error_log("[DELETE_USERS] ERRO: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// --- Tema seguro ---
$themeDefaults = [
    'primary' => '#800020', 'primary_dark' => '#4B0C1D',
    'secondary' => '#6c757d', 'accent' => '#ffc107',
    'bg_body' => '#f0f2f5', 'text_color' => '#1a1a2e',
    'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e',
    'btn_bg' => '#800020', 'btn_hover' => '#4B0C1D', 'btn_text' => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

function avatarUser($uid) {
    $path = __DIR__ . "/fotos/" . (int)$uid . ".jpg";
    if (file_exists($path)) {
        return ["url" => "fotos/" . (int)$uid . ".jpg", "initial" => null];
    }
    return ["url" => null, "initial" => null];
}

function isBarretoUser($usuario) {
    return mb_strtolower(trim((string)$usuario)) === 'barreto';
}

try {
    $idsDiretores = $db->query("SELECT DISTINCT UserId FROM diretorias WHERE UserId IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $idsGerentes  = $db->query("SELECT DISTINCT UserId FROM gerencias  WHERE UserId IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

    // Lista TODOS os usuários (todas as empresas)
    $todosUsuarios = $db->query("
        SELECT * FROM usuarios
        ORDER BY Ativo DESC, Usuario ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $mapaGrupos   = $db->query("SELECT ID_Grupo, Nome_Grupo FROM grupo")->fetchAll(PDO::FETCH_KEY_PAIR);
    $toccaGroupId = array_search('TOCCA_ONZE', $mapaGrupos);

    $lideranca = [];
    $colaboradores = [];
    $ativosTocca = 0;
    $totalLideranca = 0;
    $totalColaboradores = 0;
    $totalAtivos = 0;
    $totalInativos = 0;

    foreach ($todosUsuarios as $u) {
        $uid = $u[$userCol] ?? $u['UserId'] ?? $u['UserID'] ?? 0;
        $isLider = (in_array($uid, $idsDiretores) || in_array($uid, $idsGerentes));
        $isAtivo = (int)($u['Ativo'] ?? 0) === 1;

        if ($isLider) {
            $lideranca[] = $u;
        } else {
            $colaboradores[] = $u;
        }

        if ($isAtivo) {
            $totalAtivos++;
            if ($isLider) $totalLideranca++;
            else          $totalColaboradores++;
            if ($toccaGroupId !== false && ($u['GrupoId'] ?? null) == $toccaGroupId) {
                $ativosTocca++;
            }
        } else {
            $totalInativos++;
        }
    }

    $usuariosFinal    = array_merge($lideranca, $colaboradores);
    $totalGeralAtivos = $totalAtivos;
    $totalGeral       = $totalAtivos + $totalInativos;

    // --- Email de notificação ---
    if (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '::1') {
        $assunto  = "Usuarios Orlae - " . strtoupper($loggedUser) . " - " . date('d/m/Y H:i:s');
        $mensagem = "Acesso à página de Gestão de Ativos\n\n"
                  . "Título: Usuarios Tocca Onze\n"
                  . "Usuário Logado: " . strtoupper($loggedUser) . "\n"
                  . "Total de Usuários Ativos: " . $totalAtivos . "\n"
                  . "Total de Usuários Inativos: " . $totalInativos . "\n"
                  . "Total Geral: " . $totalGeral . "\n"
                  . "Liderança (ativos): " . $totalLideranca . "\n"
                  . "Colaboradores (ativos): " . $totalColaboradores . "\n"
                  . "Usuários no Grupo Tocca Onze (ativos): " . $ativosTocca . "\n"
                  . "IP de Acesso: " . $_SERVER['REMOTE_ADDR'];
        @enviarEmailSGVendas("sendmail@sunnyimob.com.br, valterpb@hotmail.com", $assunto, $mensagem);
    }

} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

$baseAtivos   = max(0, $ativosTocca - 10);
$val_licencas = $baseAtivos * 30;
$val_total    = $val_licencas + 350;
$referencia   = $baseAtivos . "-" . $val_licencas . "-350-" . $val_total;
$data_hora    = date("d/m/Y H:i");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Ativos — TOCCA ONZE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: <?php echo $themeSafe['primary']; ?>;
            --primary-dark: <?php echo $themeSafe['primary_dark']; ?>;
            --secondary: <?php echo $themeSafe['secondary']; ?>;
            --accent: <?php echo $themeSafe['accent']; ?>;
            --bg-body: <?php echo $themeSafe['bg_body']; ?>;
            --text-color: <?php echo $themeSafe['text_color']; ?>;
            --card-bg: <?php echo $themeSafe['card_bg']; ?>;
            --shadow-color: <?php echo $themeSafe['shadow_color']; ?>;
            --btn-bg: <?php echo $themeSafe['btn_bg']; ?>;
            --btn-hover: <?php echo $themeSafe['btn_hover']; ?>;
            --btn-text: <?php echo $themeSafe['btn_text']; ?>;
            --success: #10b981;
            --danger: #ef4444;
            --info: #3b82f6;
            --warning: #f59e0b;
            --inativo: #6b7280;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding: 0 0 40px;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* HEADER */
        .app-header {
            background: var(--primary);
            color: #fff;
            border-bottom: 5px solid var(--shadow-color);
            box-shadow: 0 6px 0 0 var(--shadow-color);
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 50;
            margin-bottom: 24px;
            position: relative;
        }
        .app-header::after {
            content: "";
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .header-inner {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .header-brand { display: flex; align-items: center; gap: 16px; min-width: 0; }
        .logo-img {
            height: 58px; width: auto; max-width: 160px; min-width: 58px;
            object-fit: contain;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #fff;
            padding: 5px 8px;
            flex-shrink: 0;
        }
        .header-title h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: -0.4px;
            line-height: 1.15;
        }
        .header-title p {
            margin: 4px 0 0;
            font-size: 0.78rem;
            font-weight: 600;
            opacity: 0.9;
        }
        .header-title p strong {
            background: rgba(255,255,255,0.18);
            padding: 1px 8px;
            border-radius: 6px;
        }

        .header-stats { display: flex; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }
        .stat-badge {
            background: rgba(0,0,0,0.18);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            padding: 8px 16px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 80px;
        }
        .stat-badge .num { font-size: 1.3rem; font-weight: 900; line-height: 1; letter-spacing: -0.5px; }
        .stat-badge .label {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
            font-weight: 700;
            opacity: 0.9;
        }
        .stat-badge.is-success  { background: var(--success); }
        .stat-badge.is-info     { background: var(--info);    }
        .stat-badge.is-warning  { background: var(--warning); color: #000; }
        .stat-badge.is-inativo  { background: var(--inativo); }

        .btn-close-header {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            text-decoration: none;
        }
        .btn-close-header:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            background: #b02a37;
            color: #fff;
        }

        .container-neo { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

        /* TOOLBAR */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .toolbar-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1 1 320px; }
        .toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex-shrink: 0; }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            flex: 1 1 320px;
            max-width: 460px;
        }
        .search-box input {
            width: 100%;
            background: var(--card-bg);
            color: var(--text-color);
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 11px 46px 11px 46px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.15s;
        }
        .search-box input:focus {
            outline: none;
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }
        .search-box input::placeholder { color: var(--text-color); opacity: 0.5; font-weight: 500; }
        .search-box .icon {
            position: absolute;
            left: 16px;
            color: var(--text-color);
            opacity: 0.6;
            pointer-events: none;
            font-size: 14px;
        }
        .search-box:focus-within .icon { color: var(--primary); opacity: 1; }
        .search-box .clear {
            position: absolute;
            right: 10px;
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            border-radius: 50%;
            width: 26px;
            height: 26px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            padding: 0;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            transition: all 0.12s;
        }
        .search-box .clear.visible { display: flex; }
        .search-box .clear:hover { transform: scale(1.12); background: #b02a37; }

        .neo-switch {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            padding: 9px 14px;
            cursor: pointer;
            user-select: none;
            transition: all 0.15s;
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--text-color);
            white-space: nowrap;
        }
        .neo-switch:hover {
            transform: translate(-1px, -1px);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
        }
        .neo-switch input { display: none; }
        .neo-switch .switch-track {
            width: 40px; height: 22px;
            background: #d1d5db;
            border-radius: 999px;
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .neo-switch .switch-track::after {
            content: "";
            position: absolute;
            width: 18px; height: 18px;
            background: #fff;
            border-radius: 50%;
            top: 2px; left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .neo-switch input:checked + .switch-track { background: var(--primary); }
        .neo-switch input:checked + .switch-track::after { transform: translateX(18px); }
        .neo-switch i { color: var(--primary); }
        .neo-switch.colab i { color: var(--warning); }
        .neo-switch.inativo i { color: var(--inativo); }
        .neo-switch.inativo input:checked + .switch-track { background: var(--inativo); }
        .neo-switch.ativo i { color: var(--success); }
        .neo-switch.ativo input:checked + .switch-track { background: var(--success); }
        .neo-switch.todos i { color: #8b5cf6; }
        .neo-switch.todos input:checked + .switch-track { background: #8b5cf6; }

        /* DELETE BAR */
        .selected-info {
            background: #fff;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            padding: 8px 14px;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-color);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .btn-delete-batch {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 9px 18px;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .btn-delete-batch:hover:not(:disabled) {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            background: #b02a37;
        }
        .btn-delete-batch:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* TABLE */
        .table-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
            padding: 6px;
        }

        table.dataTable.table-neo {
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            background: var(--card-bg);
            color: var(--text-color);
            margin: 0 !important;
        }
        table.dataTable.table-neo thead th {
            background: var(--primary) !important;
            color: #fff !important;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 1px;
            padding: 14px 12px !important;
            border: none !important;
            border-bottom: 3px solid var(--shadow-color) !important;
        }
        table.dataTable.table-neo thead th:first-child { border-top-left-radius: 10px; }
        table.dataTable.table-neo thead th:last-child  { border-top-right-radius: 10px; }
        table.dataTable.table-neo tbody td {
            padding: 12px !important;
            font-size: 13.5px;
            border: none !important;
            border-bottom: 1px solid rgba(0,0,0,0.07) !important;
            vertical-align: middle !important;
        }
        table.dataTable.table-neo tbody tr:last-child td { border-bottom: none !important; }
        table.dataTable.table-neo tbody tr:hover td { background: rgba(0,0,0,0.025); }

        table.dataTable.table-neo tbody tr.row-lideranca td {
            background: rgba(59,130,246,0.06);
        }
        table.dataTable.table-neo tbody tr.row-lideranca:hover td {
            background: rgba(59,130,246,0.12);
        }
        table.dataTable.table-neo tbody tr.row-lideranca td:first-child {
            box-shadow: inset 4px 0 0 0 var(--info);
        }

        table.dataTable.table-neo tbody tr.row-tocca td:first-child {
            box-shadow: inset 4px 0 0 0 var(--warning);
        }
        table.dataTable.table-neo tbody tr.row-lideranca.row-tocca td:first-child {
            box-shadow: inset 4px 0 0 0 var(--info), inset 8px 0 0 0 var(--warning);
        }

        table.dataTable.table-neo tbody tr.row-inativo td {
            opacity: 0.68;
            background: rgba(107,114,128,0.05) !important;
        }
        table.dataTable.table-neo tbody tr.row-inativo:hover td {
            opacity: 0.85;
            background: rgba(107,114,128,0.11) !important;
        }
        table.dataTable.table-neo tbody tr.row-inativo td:first-child {
            box-shadow: inset 4px 0 0 0 var(--inativo) !important;
        }
        table.dataTable.table-neo tbody tr.row-lideranca.row-inativo td:first-child {
            box-shadow: inset 4px 0 0 0 var(--inativo), inset 8px 0 0 0 var(--info) !important;
        }
        table.dataTable.table-neo tbody tr.row-tocca.row-inativo td:first-child {
            box-shadow: inset 4px 0 0 0 var(--inativo), inset 8px 0 0 0 var(--warning) !important;
        }

        .user-checkbox, #selectAll {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
            vertical-align: middle;
        }
        table.dataTable.table-neo thead th.col-select,
        table.dataTable.table-neo tbody td.col-select {
            text-align: center;
            padding-left: 8px !important;
            padding-right: 8px !important;
        }
        .lock-icon {
            display: inline-block;
            font-size: 12px;
            color: var(--text-color);
            opacity: 0.35;
            cursor: help;
        }

        /* Célula do usuário */
        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1rem;
            text-transform: uppercase;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .user-info { min-width: 0; }
        .user-nome {
            font-weight: 800;
            font-size: 0.92rem;
            color: var(--text-color);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .user-real {
            font-size: 0.76rem;
            color: var(--text-color);
            opacity: 0.65;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Badges */
        .badge-rank {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            padding: 4px 10px;
            background: var(--shadow-color);
            color: #fff;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.72rem;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 rgba(0,0,0,0.15);
        }
        .badge-id {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            background: rgba(0,0,0,0.05);
            color: var(--text-color);
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.72rem;
            border: 1.5px solid rgba(0,0,0,0.1);
            font-family: 'SFMono-Regular', Consolas, monospace;
        }
        .badge-ativo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--success);
            color: #fff;
            font-size: 0.66rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .badge-ativo::before {
            content: "";
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #fff;
        }
        .badge-inativo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--inativo);
            color: #fff;
            font-size: 0.66rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .badge-inativo::before {
            content: "";
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #fff;
        }

        .badge-hier {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            font-size: 0.68rem;
            font-weight: 800;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            margin-right: 4px;
            margin-bottom: 2px;
        }
        .badge-hier.dir { background: var(--primary); color: #fff; }
        .badge-hier.ger { background: var(--success); color: #fff; }
        .badge-hier.ges { background: #6b7280; color: #fff; }

        .badge-grupo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            background: rgba(245,158,11,0.15);
            color: #b45309;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.72rem;
            border: 1.5px solid rgba(245,158,11,0.35);
        }

        /* DATATABLES */
        .dataTables_wrapper .dataTables_filter { display: none; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_info {
            padding: 12px 8px !important;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-color);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            padding: 4px 8px;
            background: #fff;
            color: var(--text-color);
            font-weight: 700;
            margin: 0 4px;
        }
        .dataTables_wrapper .dataTables_paginate { padding: 12px 8px !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-color) !important;
            border: 2px solid var(--shadow-color) !important;
            border-radius: 8px !important;
            padding: 5px 12px !important;
            margin: 0 2px !important;
            font-weight: 700 !important;
            background: #fff !important;
            transition: all 0.15s !important;
            font-size: 13px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary) !important;
            color: #fff !important;
            transform: translateY(-2px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--shadow-color) !important;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.4;
            transform: none;
            box-shadow: none;
        }

        /* MOBILE CARDS */
        .mobile-container { display: none; }
        .user-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            padding: 16px;
            margin-bottom: 14px;
            transition: all 0.15s;
            position: relative;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 7px 7px 0 0 var(--shadow-color);
        }
        .user-card.lider { border-left: 8px solid var(--info); }
        .user-card.tocca { background: linear-gradient(to right, rgba(245,158,11,0.04) 0%, var(--card-bg) 40%); }

        .user-card.inativo {
            opacity: 0.78;
            background: linear-gradient(to right, rgba(107,114,128,0.09) 0%, var(--card-bg) 40%);
            border-left: 8px solid var(--inativo);
        }
        .user-card.inativo:hover { opacity: 0.95; }
        .user-card.lider.inativo { border-left-color: var(--inativo); }

        .user-card .card-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .user-card .user-avatar { width: 50px; height: 50px; font-size: 1.15rem; }
        .user-card .card-top-info { min-width: 0; flex: 1; }
        .user-card .user-nome { font-size: 1rem; }
        .user-card .badges-row { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }

        .user-card .card-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px dashed rgba(0,0,0,0.12);
            font-size: 0.78rem;
            gap: 12px;
            flex-wrap: wrap;
        }
        .user-card .card-details .detail { display: flex; flex-direction: column; }
        .user-card .card-details .detail small { opacity: 0.6; font-weight: 600; text-transform: uppercase; font-size: 0.6rem; letter-spacing: 0.5px; }
        .user-card .card-details .detail strong { font-weight: 800; }
        .user-card .card-index {
            position: absolute;
            top: 8px; right: 10px;
            font-size: 0.65rem;
            font-weight: 800;
            background: var(--shadow-color);
            color: #fff;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* EMPTY STATE */
        .empty-state {
            display: none;
            text-align: center;
            padding: 50px 20px;
            background: var(--card-bg);
            border: 3px dashed var(--shadow-color);
            border-radius: 16px;
            color: var(--text-color);
        }
        .empty-state.visible { display: block; }
        .empty-state i { font-size: 3rem; color: var(--primary); opacity: 0.4; display: block; margin-bottom: 12px; }
        .empty-state h3 { font-weight: 900; margin: 0 0 6px; }
        .empty-state p { opacity: 0.65; font-weight: 500; margin: 0; }

        /* FOOTER */
        .custom-footer {
            text-align: center;
            padding: 26px 20px 10px;
            border-top: 3px solid var(--shadow-color);
            margin-top: 30px;
            font-size: 0.78rem;
            color: var(--text-color);
        }
        .custom-footer strong { color: var(--primary); }
        .custom-footer small { opacity: 0.7; }

        /* RESPONSIVO */
        @media (max-width: 992px) {
            .header-title h1 { font-size: 1.1rem; }
            .logo-img { height: 52px; max-width: 140px; }
        }
        @media (max-width: 767px) {
            .app-header { padding: 10px 0; }
            .header-inner { padding: 0 12px; gap: 12px; }
            .logo-img { height: 42px; max-width: 110px; padding: 3px 6px; }
            .header-title h1 { font-size: 0.95rem; }
            .header-title p { font-size: 0.7rem; }
            .header-stats { width: 100%; flex-wrap: wrap; }
            .stat-badge { flex: 1 1 calc(33.333% - 7px); min-width: 0; padding: 6px 10px; }
            .stat-badge .num { font-size: 1.1rem; }
            .stat-badge .label { font-size: 0.55rem; }
            .btn-close-header { width: 100%; justify-content: center; }
            .container-neo { padding: 0 12px; }
            .table-card { display: none; }
            .mobile-container { display: block; }
            .toolbar-left { flex-basis: 100%; }
            .search-box { max-width: 100%; flex-basis: 100%; }
            .neo-switch { font-size: 0.75rem; padding: 7px 11px; }
        }
        @media (min-width: 768px) {
            .mobile-container { display: none; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="app-header">
    <div class="header-inner">
        <div class="header-brand">
            <img src="img/logosunny.jpg" alt="Logo" class="logo-img">
            <div class="header-title">
                <h1><i class="fas fa-users me-2"></i> Gestão de Ativos</h1>
                <p>Grupo <strong>TOCCA_ONZE</strong> · Painel de equipe</p>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-badge">
                <span class="num"><?php echo $totalGeralAtivos; ?></span>
                <span class="label">Ativos</span>
            </div>
            <div class="stat-badge is-inativo">
                <span class="num"><?php echo $totalInativos; ?></span>
                <span class="label">Inativos</span>
            </div>
            <div class="stat-badge is-info">
                <span class="num"><?php echo $totalLideranca; ?></span>
                <span class="label">Liderança</span>
            </div>
            <div class="stat-badge is-success">
                <span class="num"><?php echo $totalColaboradores; ?></span>
                <span class="label">Colaboradores</span>
            </div>
            <div class="stat-badge is-warning">
                <span class="num"><?php echo $ativosTocca; ?></span>
                <span class="label">Grupo Tocca</span>
            </div>
        </div>

        <a href="#" class="btn-close-header" onclick="window.close(); return false;">
            <i class="fas fa-times"></i> Fechar
        </a>
    </div>
</header>

<!-- CONTEÚDO -->
<div class="container-neo">

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar por nome ou usuário…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <label class="neo-switch" title="Mostrar apenas diretores e gerentes">
                <input type="checkbox" id="filterLider">
                <span class="switch-track"></span>
                <span><i class="fas fa-crown"></i> Só liderança</span>
            </label>

            <label class="neo-switch colab" title="Mostrar apenas usuários que NÃO são diretores nem gerentes">
                <input type="checkbox" id="filterColab">
                <span class="switch-track"></span>
                <span><i class="fas fa-user-friends"></i> Só colaboradores</span>
            </label>

            <label class="neo-switch" title="Mostrar apenas usuários do grupo Tocca Onze">
                <input type="checkbox" id="filterTocca">
                <span class="switch-track"></span>
                <span><i class="fas fa-users-cog"></i> Só grupo Tocca</span>
            </label>

            <label class="neo-switch ativo" title="Mostrar apenas usuários ativos">
                <input type="checkbox" id="filterAtivo" checked>
                <span class="switch-track"></span>
                <span><i class="fas fa-check-circle"></i> Só ativos</span>
            </label>

            <label class="neo-switch inativo" title="Mostrar apenas usuários inativos">
                <input type="checkbox" id="filterInativo">
                <span class="switch-track"></span>
                <span><i class="fas fa-ban"></i> Só inativos</span>
            </label>

            <label class="neo-switch todos" title="Mostrar todos os usuários (ativos e inativos)">
                <input type="checkbox" id="filterTodos">
                <span class="switch-track"></span>
                <span><i class="fas fa-list-ul"></i> Listar tudo</span>
            </label>
        </div>

        <?php if ($isBarreto): ?>
        <div class="toolbar-right" id="deleteBar" style="display:none;">
            <span class="selected-info">
                <i class="fas fa-check-square"></i>
                <strong id="selectedCount">0</strong>&nbsp;selecionado(s)
            </span>
            <button type="button" class="btn-delete-batch" id="btnDelete">
                <i class="fas fa-trash-alt"></i> Excluir Selecionados
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- TABELA (desktop) -->
    <div class="table-card">
        <table id="tabelaAtivos" class="table table-neo w-100">
            <thead>
                <tr>
                    <th style="width: 70px;">#</th>
                    <th style="width: 100px;">ID</th>
                    <th>Colaborador</th>
                    <th style="width: 180px;">Hierarquia</th>
                    <th style="width: 200px;">Grupo</th>
                    <th style="width: 110px;">Status</th>
                    <?php if ($isBarreto): ?>
                    <th class="col-select" style="width: 50px;">
                        <input type="checkbox" id="selectAll" title="Selecionar todos visíveis">
                    </th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php $counter = 1; foreach ($usuariosFinal as $u):
                $uid     = $u[$userCol] ?? $u['UserId'] ?? $u['UserID'] ?? 0;
                $eDir    = in_array($uid, $idsDiretores);
                $eGer    = in_array($uid, $idsGerentes);
                $isLider = ($eDir || $eGer);
                $isTocca = ($toccaGroupId !== false && ($u['GrupoId'] ?? null) == $toccaGroupId);
                $isAtivo = (int)($u['Ativo'] ?? 0) === 1;
                $protegido = isBarretoUser($u['Usuario'] ?? '');

                $fotoPath = __DIR__ . "/fotos/" . (int)$uid . ".jpg";
                $hasFoto  = file_exists($fotoPath);
                $initial  = strtoupper(mb_substr($u['Usuario'] ?? '?', 0, 1));

                $cls = trim(
                    ($isLider ? 'row-lideranca ' : '') .
                    ($isTocca ? 'row-tocca ' : '') .
                    ($isAtivo ? '' : 'row-inativo')
                );
            ?>
                <tr class="<?php echo $cls; ?>">
                    <td><span class="badge-rank"><?php echo $counter++; ?>º</span></td>
                    <td><span class="badge-id">#<?php echo str_pad($uid, 3, '0', STR_PAD_LEFT); ?></span></td>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar">
                                <?php if ($hasFoto): ?>
                                    <img src="fotos/<?php echo (int)$uid; ?>.jpg" alt="">
                                <?php else: ?>
                                    <?php echo $initial; ?>
                                <?php endif; ?>
                            </div>
                            <div class="user-info">
                                <div class="user-nome"><?php echo htmlspecialchars(strtoupper($u['Usuario'])); ?></div>
                                <div class="user-real"><?php echo htmlspecialchars($u['Nome']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($eDir): ?><span class="badge-hier dir"><i class="fas fa-crown me-1"></i>Diretoria</span><?php endif; ?>
                        <?php if ($eGer): ?><span class="badge-hier ger"><i class="fas fa-user-tie me-1"></i>Gerência</span><?php endif; ?>
                        <?php if (!$eDir && !$eGer): ?><span class="badge-hier ges">Gestão</span><?php endif; ?>
                    </td>
                    <td>
                        <?php $grupoNome = $mapaGrupos[$u['GrupoId'] ?? 0] ?? null; ?>
                        <?php if ($grupoNome): ?>
                            <span class="badge-grupo"><i class="fas fa-users"></i> <?php echo htmlspecialchars($grupoNome); ?></span>
                        <?php else: ?>
                            <span style="opacity:0.4;font-weight:600;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isAtivo): ?>
                            <span class="badge-ativo">Ativo</span>
                        <?php else: ?>
                            <span class="badge-inativo">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($isBarreto): ?>
                    <td class="col-select">
                        <?php if ($protegido): ?>
                            <i class="fas fa-lock lock-icon" title="Usuário protegido — não pode ser excluído"></i>
                        <?php else: ?>
                            <input type="checkbox" class="user-checkbox table-checkbox" value="<?php echo (int)$uid; ?>">
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- CARDS (mobile) -->
    <div class="mobile-container">
        <div id="listaCards">
            <?php $counterM = 1; foreach ($usuariosFinal as $u):
                $uid     = $u[$userCol] ?? $u['UserId'] ?? $u['UserID'] ?? 0;
                $eDir    = in_array($uid, $idsDiretores);
                $eGer    = in_array($uid, $idsGerentes);
                $isLider = ($eDir || $eGer);
                $isTocca = ($toccaGroupId !== false && ($u['GrupoId'] ?? null) == $toccaGroupId);
                $isAtivo = (int)($u['Ativo'] ?? 0) === 1;
                $protegido = isBarretoUser($u['Usuario'] ?? '');

                $fotoPath = __DIR__ . "/fotos/" . (int)$uid . ".jpg";
                $hasFoto  = file_exists($fotoPath);
                $initial  = strtoupper(mb_substr($u['Usuario'] ?? '?', 0, 1));

                $grupoNome = $mapaGrupos[$u['GrupoId'] ?? 0] ?? null;

                $cardCls = trim(
                    ($isLider ? 'lider ' : '') .
                    ($isTocca ? 'tocca ' : '') .
                    ($isAtivo ? '' : 'inativo')
                );
            ?>
            <div class="user-card <?php echo $cardCls; ?>"
                 data-search="<?php echo htmlspecialchars(strtolower($u['Usuario'] . ' ' . $u['Nome'])); ?>"
                 data-lider="<?php echo $isLider ? '1' : '0'; ?>"
                 data-tocca="<?php echo $isTocca ? '1' : '0'; ?>"
                 data-ativo="<?php echo $isAtivo ? '1' : '0'; ?>">

                <span class="card-index"><?php echo $counterM++; ?>º</span>

                <div class="card-top">
                    <?php if ($isBarreto): ?>
                        <?php if ($protegido): ?>
                            <i class="fas fa-lock lock-icon" title="Usuário protegido — não pode ser excluído"></i>
                        <?php else: ?>
                            <input type="checkbox" class="user-checkbox card-checkbox" value="<?php echo (int)$uid; ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="user-avatar">
                        <?php if ($hasFoto): ?>
                            <img src="fotos/<?php echo (int)$uid; ?>.jpg" alt="">
                        <?php else: ?>
                            <?php echo $initial; ?>
                        <?php endif; ?>
                    </div>
                    <div class="card-top-info">
                        <div class="user-nome"><?php echo htmlspecialchars(strtoupper($u['Usuario'])); ?></div>
                        <div class="user-real"><?php echo htmlspecialchars($u['Nome']); ?></div>
                        <div class="badges-row">
                            <?php if ($eDir): ?><span class="badge-hier dir"><i class="fas fa-crown me-1"></i>Diretoria</span><?php endif; ?>
                            <?php if ($eGer): ?><span class="badge-hier ger"><i class="fas fa-user-tie me-1"></i>Gerência</span><?php endif; ?>
                            <?php if (!$eDir && !$eGer): ?><span class="badge-hier ges">Gestão</span><?php endif; ?>
                            <?php if ($isAtivo): ?>
                                <span class="badge-ativo" style="font-size:0.6rem;padding:2px 8px;">Ativo</span>
                            <?php else: ?>
                                <span class="badge-inativo" style="font-size:0.6rem;padding:2px 8px;">Inativo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-details">
                    <div class="detail">
                        <small>ID</small>
                        <strong>#<?php echo $uid; ?></strong>
                    </div>
                    <div class="detail" style="text-align:right;">
                        <small>Grupo</small>
                        <strong><?php echo $grupoNome ? htmlspecialchars($grupoNome) : '—'; ?></strong>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="empty-state" id="emptyCards">
            <i class="fas fa-search"></i>
            <h3>Nenhum resultado</h3>
            <p>Nenhum colaborador corresponde à busca ou aos filtros atuais.</p>
        </div>
    </div>

</div>

<!-- FOOTER -->
<footer class="custom-footer">
    <div style="margin-bottom: 5px;">
        <strong>Sistema SunnyImob</strong> — Valter Barreto
    </div>
    <div>
        <small>Cód.: <strong><?php echo $referencia; ?></strong> · <?php echo $data_hora; ?></small>
    </div>
</footer>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
(function () {
    'use strict';

    const IS_BARRETO = <?php echo $isBarreto ? 'true' : 'false'; ?>;
    // URL robusta: usa o href atual (sem fragmento), garantindo o mesmo endpoint da página
    const DELETE_URL = window.location.href.split('#')[0];

    $(document).ready(function () {

        // ============================================================
        // 1) DataTables
        // ============================================================
        const orderableTargets = [0, 1, 3, 4, 5];
        const searchableTargets = [0, 1, 5];
        if (IS_BARRETO) {
            orderableTargets.push(6);
            searchableTargets.push(6);
        }

        const table = $('#tabelaAtivos').DataTable({
            language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json" },
            pageLength: 50,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Todos"]],
            ordering: false,
            columnDefs: [
                { targets: orderableTargets, orderable: false },
                { targets: searchableTargets, searchable: false }
            ],
            drawCallback: function () {
                if (IS_BARRETO) {
                    applySelection();
                }
            }
        });

        // ============================================================
        // 2) Filtros customizados (DataTables)
        // ============================================================
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'tabelaAtivos') return true;
            const $lider   = $('#filterLider');
            const $colab   = $('#filterColab');
            const $tocca   = $('#filterTocca');
            const $ativo   = $('#filterAtivo');
            const $inativo = $('#filterInativo');
            const $todos   = $('#filterTodos');
            const tr = settings.aoData[dataIndex].nTr;

            if ($lider.is(':checked')   && !$(tr).hasClass('row-lideranca')) return false;
            if ($colab.is(':checked')   &&  $(tr).hasClass('row-lideranca')) return false;
            if ($tocca.is(':checked')   && !$(tr).hasClass('row-tocca'))     return false;

            if (!$todos.is(':checked')) {
                if ($ativo.is(':checked')   &&  $(tr).hasClass('row-inativo')) return false;
                if ($inativo.is(':checked') && !$(tr).hasClass('row-inativo')) return false;
            }
            return true;
        });

        // ============================================================
        // 3) Busca customizada
        // ============================================================
        const $search = $('#searchInput');
        const $clear  = $('#searchClear');
        let timer;

        $search.on('input', function () {
            const v = $(this).val();
            $clear.toggleClass('visible', v.length > 0);

            clearTimeout(timer);
            timer = setTimeout(function () {
                table.search(v).draw();
                filtrarCards(v);
            }, 150);
        });

        $search.on('keydown', function (e) {
            if (e.key === 'Escape') $(this).val().trigger('input');
        });

        $clear.on('click', function () {
            $search.val('').trigger('input').focus();
        });

        // ============================================================
        // 4) Filtros (switches)
        // ============================================================
        $('#filterLider').on('change', function () {
            if (this.checked) $('#filterColab').prop('checked', false);
            table.draw();
            filtrarCards($search.val());
        });
        $('#filterColab').on('change', function () {
            if (this.checked) $('#filterLider').prop('checked', false);
            table.draw();
            filtrarCards($search.val());
        });
        $('#filterTocca').on('change', function () {
            table.draw();
            filtrarCards($search.val());
        });

        // 3 opções mutuamente exclusivas de status
        $('#filterAtivo, #filterInativo, #filterTodos').on('change', function () {
            if (this.checked) {
                $('#filterAtivo, #filterInativo, #filterTodos').not(this).prop('checked', false);
            } else {
                if (!$('#filterAtivo').is(':checked') && !$('#filterInativo').is(':checked')) {
                    $('#filterTodos').prop('checked', true);
                }
            }
            table.draw();
            filtrarCards($search.val());
        });

        // ============================================================
        // 5) Cards mobile — filtro client-side
        // ============================================================
        function filtrarCards(termo) {
            termo = (termo || '').toLowerCase().trim();
            const onlyLider   = $('#filterLider').is(':checked');
            const onlyColab   = $('#filterColab').is(':checked');
            const onlyTocca   = $('#filterTocca').is(':checked');
            const onlyAtivo   = $('#filterAtivo').is(':checked');
            const onlyInativo = $('#filterInativo').is(':checked');
            const onlyTodos   = $('#filterTodos').is(':checked');

            let visiveis = 0;
            $('#listaCards .user-card').each(function () {
                const $c = $(this);
                const isAtivo = Number($c.data('ativo')) === 1;

                const matchTexto   = !termo || ($c.data('search') || '').indexOf(termo) !== -1;
                const matchLider   = !onlyLider   || $c.data('lider') === 1;
                const matchColab   = !onlyColab   || $c.data('lider') === 0;
                const matchTocca   = !onlyTocca   || $c.data('tocca') === 1;

                let matchStatus = true;
                if (!onlyTodos) {
                    matchStatus = (!onlyAtivo || isAtivo) && (!onlyInativo || !isAtivo);
                }

                const show = matchTexto && matchLider && matchColab && matchTocca && matchStatus;
                $c.toggle(show);
                if (show) visiveis++;
            });

            $('#emptyCards').toggleClass('visible', visiveis === 0);
        }

        filtrarCards('');

        // ============================================================
        // 6) SELEÇÃO EM LOTE
        // ============================================================
        const selectedIds = new Set();

        function updateDeleteBar() {
            if (!IS_BARRETO) return;
            const $bar = $('#deleteBar');
            if (!$bar.length) return;
            const n = selectedIds.size;
            $('#selectedCount').text(n);
            $bar.toggle(n > 0);
        }

        function syncSelectAllState() {
            if (!IS_BARRETO) return;
            const $selAll = $('#selectAll');
            if (!$selAll.length) return;
            const $visible = $('#tabelaAtivos tbody .table-checkbox:visible');
            if ($visible.length === 0) {
                $selAll.prop('checked', false).prop('indeterminate', false);
                return;
            }
            const checked = $visible.filter(':checked').length;
            $selAll.prop('checked', checked === $visible.length);
            $selAll.prop('indeterminate', checked > 0 && checked < $visible.length);
        }

        function applySelection() {
            if (!IS_BARRETO) return;
            $('#tabelaAtivos tbody .table-checkbox, #listaCards .card-checkbox').each(function () {
                this.checked = selectedIds.has(String(this.value));
            });
            syncSelectAllState();
            updateDeleteBar();
        }

        $(document).on('change', '.user-checkbox', function () {
            const id = String(this.value);
            if (this.checked) selectedIds.add(id);
            else selectedIds.delete(id);
            applySelection();
        });

        $(document).on('change', '#selectAll', function () {
            const isChecked = this.checked;
            $('#tabelaAtivos tbody .table-checkbox:visible').each(function () {
                if (isChecked) selectedIds.add(String(this.value));
                else selectedIds.delete(String(this.value));
            });
            applySelection();
        });

        // ============================================================
        // 7) EXCLUSÃO EM LOTE (AJAX)
        // ============================================================
        $('#btnDelete').on('click', function () {
            if (!IS_BARRETO) return;
            const ids = Array.from(selectedIds);
            if (ids.length === 0) return;

            const msg = 'Tem certeza que deseja EXCLUIR ' + ids.length +
                        ' usuário(s)?\n\nEsta ação NÃO pode ser desfeita.';
            if (!confirm(msg)) return;

            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> Excluindo...');

            const formData = new FormData();
            formData.append('action', 'delete_users');
            ids.forEach(function (id) { formData.append('ids[]', id); });

            fetch(DELETE_URL, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    console.log('[DELETE_USERS] response:', data);
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Erro: ' + (data.message || 'desconhecido'));
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                })
                .catch(function (err) {
                    console.error('[DELETE_USERS] catch:', err);
                    alert('Erro de comunicação: ' + err.message);
                    $btn.prop('disabled', false).html(originalHtml);
                });
        });

        // ============================================================
        // 8) Ajuste do DataTables ao redimensionar
        // ============================================================
        let resizeTimer;
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                table.columns.adjust();
            }, 200);
        });

    });
})();
</script>
</body>
</html>