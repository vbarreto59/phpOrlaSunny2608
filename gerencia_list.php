<?php
// ============================================================
// SESSION PRIMEIRO
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'usr_acoes.php';
require_once 'theme_config.php';

// ============================================================
// LOGIN
// ============================================================
if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}

// ============================================================
// CONEXÃO
// ============================================================
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

// ============================================================
// TEMA SEGURO
// ============================================================
$themeDefaults = [
    'primary' => '#800020', 'primary_dark' => '#4B0C1D',
    'secondary' => '#6c757d', 'accent' => '#ffc107',
    'bg_body' => '#f0f2f5', 'text_color' => '#1a1a2e',
    'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e',
    'btn_bg' => '#800020', 'btn_hover' => '#4B0C1D', 'btn_text' => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);
if (!isset($theme['bg_body']) && isset($theme['body_bg'])) {
    $themeSafe['bg_body'] = $theme['body_bg'];
}

function hexToRgb($hex) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    if (!ctype_xdigit($hex) || strlen($hex) !== 6) return '0,0,0';
    return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
}
$primaryRgb = hexToRgb($themeSafe['primary']);

// ============================================================
// CSRF
// ============================================================
if (empty($_SESSION['csrf_ger'])) {
    $_SESSION['csrf_ger'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_ger'];

// ============================================================
// PROCESSAR POST (CREATE / UPDATE / DELETE)
// ============================================================
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($CSRF, $_POST['csrf_token'] ?? '')) {
        $_SESSION['MsgErro'] = "Falha de segurança (CSRF). Recarregue a página.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ========================= CREATE / UPDATE =========================
    if ($action === 'create' || $action === 'update') {
        $nome      = trim($_POST['NomeGerencia']  ?? '');
        $diretoria = (int)($_POST['DiretoriaID']  ?? 0);
        $userId    = (int)($_POST['UserId']       ?? 0);
        $estado    = strtoupper(trim($_POST['EstadoGerencia'] ?? 'ATIVO'));

        if (empty($nome))                        $erros[] = "O nome da gerência é obrigatório.";
        if ($diretoria <= 0)                     $erros[] = "Selecione uma diretoria.";
        if (!in_array($estado, ['ATIVO','INATIVO'], true)) $erros[] = "Estado inválido.";

        // Valida diretoria
        if (empty($erros) && $diretoria > 0) {
            $chk = $db->prepare("SELECT 1 FROM diretorias WHERE DiretoriaID = ?");
            $chk->execute([$diretoria]);
            if (!$chk->fetch()) $erros[] = "Diretoria selecionada não existe.";
        }

        if (empty($erros)) {
            try {
                if ($action === 'create') {
                    // Estado=ATIVO → DataEncerramento NULL
                    // Estado=INATIVO → DataEncerramento = NOW()
                    $dataEnc = $estado === 'INATIVO' ? date('Y-m-d H:i:s') : null;

                    $stmt = $db->prepare("
                        INSERT INTO gerencias
                            (NomeGerencia, DiretoriaID, UserId, DataCriacao, DataEncerramento)
                        VALUES (?, ?, ?, NOW(), ?)
                    ");
                    $stmt->execute([
                        $nome,
                        $diretoria,
                        $userId > 0 ? $userId : null,
                        $dataEnc
                    ]);
                    $_SESSION['MsgSucesso'] = "Gerência <strong>" . htmlspecialchars($nome) . "</strong> criada!";
                } else {
                    $id = (int)($_POST['GerenciaID'] ?? 0);
                    if ($id <= 0) throw new Exception("ID inválido.");

                    // Buscar estado atual
                    $stmt = $db->prepare("SELECT DataEncerramento FROM gerencias WHERE GerenciaID = ?");
                    $stmt->execute([$id]);
                    $currentEnc = $stmt->fetchColumn();

                    // Lógica do status:
                    // - ATIVO: DataEncerramento = NULL
                    // - INATIVO: DataEncerramento = NOW() (só se ainda não estava inativo)
                    if ($estado === 'ATIVO') {
                        $dataEnc = null;
                    } else {
                        $dataEnc = $currentEnc ?: date('Y-m-d H:i:s');
                    }

                    $stmt = $db->prepare("
                        UPDATE gerencias
                        SET NomeGerencia = ?, DiretoriaID = ?, UserId = ?, DataEncerramento = ?
                        WHERE GerenciaID = ?
                    ");
                    $stmt->execute([
                        $nome,
                        $diretoria,
                        $userId > 0 ? $userId : null,
                        $dataEnc,
                        $id
                    ]);
                    $_SESSION['MsgSucesso'] = "Gerência <strong>" . htmlspecialchars($nome) . "</strong> atualizada!";
                }

                header("Location: " . $_SERVER['PHP_SELF']);
                exit;

            } catch (Exception $e) {
                $erros[] = "Erro ao salvar: " . $e->getMessage();
                error_log("[GERENCIA_CRUD] " . $e->getMessage());
            }
        }

        if (!empty($erros)) {
            $_SESSION['MsgErro'] = implode("<br>", $erros);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // ========================= DELETE =========================
    if ($action === 'delete') {
        $id = (int)($_POST['GerenciaID'] ?? 0);

        if ($id > 0) {
            try {
                // Bloqueia se houver usuários vinculados
                $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE GerenciaID = ?");
                $stmt->execute([$id]);
                $count = (int)$stmt->fetchColumn();

                if ($count > 0) {
                    $_SESSION['MsgErro'] = "Não é possível excluir: existe(m) <b>$count</b> usuário(s) vinculado(s) a esta gerência.";
                } else {
                    $stmt = $db->prepare("SELECT NomeGerencia FROM gerencias WHERE GerenciaID = ?");
                    $stmt->execute([$id]);
                    $nomeExcluido = $stmt->fetchColumn();

                    $db->prepare("DELETE FROM gerencias WHERE GerenciaID = ?")->execute([$id]);
                    $_SESSION['MsgSucesso'] = "Gerência <strong>" . htmlspecialchars($nomeExcluido ?: "#$id") . "</strong> excluída.";
                }
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;

            } catch (Exception $e) {
                $_SESSION['MsgErro'] = "Erro ao excluir: " . $e->getMessage();
                error_log("[GERENCIA_CRUD] " . $e->getMessage());
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
}

// ============================================================
// CARREGAR DADOS
// ============================================================
$gerencias  = [];
$diretorias = [];
$usuarios   = [];
$erro       = null;

try {
    // Lista TODAS as gerências (ativas e encerradas), com nome da diretoria e usuário
    $stmt = $db->prepare("
        SELECT G.*,
               D.NomeDiretoria,
               U.Nome AS NomeUsuario,
               U.Usuario
        FROM gerencias G
        LEFT JOIN diretorias D ON G.DiretoriaID = D.DiretoriaID
        LEFT JOIN usuarios   U ON G.UserId = U.UserId
        ORDER BY D.NomeDiretoria ASC, G.NomeGerencia ASC
    ");
    $stmt->execute();
    $gerencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Diretorias para os selects (todas, mesmo inativas, para não quebrar registros antigos)
    $diretorias = $db->query("
        SELECT DiretoriaID, NomeDiretoria
        FROM diretorias
        ORDER BY NomeDiretoria ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Usuários ativos
    $usuarios = $db->query("
        SELECT UserId, Usuario, Nome
        FROM usuarios
        WHERE Ativo = 1
        ORDER BY Nome ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $erro = $e->getMessage();
    error_log("[GERENCIA_LIST] " . $e->getMessage());
}

// ============================================================
// ESTATÍSTICAS
// ============================================================
$total     = count($gerencias);
$ativas    = 0;
$inativas  = 0;
$comUser   = 0;

foreach ($gerencias as $g) {
    $isAtiva = empty($g['DataEncerramento']);
    if ($isAtiva) $ativas++;
    else          $inativas++;
    if (!empty($g['UserId']) && (int)$g['UserId'] > 0) $comUser++;
}

$gerenciasJson = json_encode($gerencias, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if (json_last_error() !== JSON_ERROR_NONE) $gerenciasJson = '[]';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerências · SunnyImob</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: <?= $themeSafe['primary'] ?>;
            --primary-dark: <?= $themeSafe['primary_dark'] ?>;
            --primary-rgb: <?= $primaryRgb ?>;
            --bg-body: <?= $themeSafe['bg_body'] ?>;
            --text-color: <?= $themeSafe['text_color'] ?>;
            --card-bg: <?= $themeSafe['card_bg'] ?>;
            --shadow-color: <?= $themeSafe['shadow_color'] ?>;
            --btn-bg: <?= $themeSafe['btn_bg'] ?>;
            --btn-hover: <?= $themeSafe['btn_hover'] ?>;
            --btn-text: <?= $themeSafe['btn_text'] ?>;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            padding-bottom: 40px;
            -webkit-font-smoothing: antialiased;
        }

        .container-neo { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

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
            gap: 16px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .header-brand { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .header-logo {
            height: 54px; width: auto; max-width: 150px; min-width: 54px;
            object-fit: contain;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #fff;
            padding: 4px 8px;
            flex-shrink: 0;
        }
        .header-title h1 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 900;
            letter-spacing: -0.3px;
            line-height: 1.15;
        }
        .header-title p {
            margin: 4px 0 0;
            font-size: 0.74rem;
            font-weight: 600;
            opacity: 0.9;
        }

        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; flex-shrink: 0; }
        .btn-header {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            white-space: nowrap;
            font-family: inherit;
        }
        .btn-header:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: rgba(255,255,255,0.25);
            color: #fff;
        }
        .btn-header.primary { background: var(--primary-dark); }
        .btn-header.danger  { background: var(--danger); }

        /* STATS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.15s;
        }
        .stat-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            flex-shrink: 0;
        }
        .stat-icon.i-primary  { background: var(--primary); }
        .stat-icon.i-success  { background: var(--success); }
        .stat-icon.i-danger   { background: var(--danger); }
        .stat-icon.i-warning  { background: var(--warning); color: #000; }
        .stat-info { min-width: 0; }
        .stat-num {
            font-size: 1.5rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.8px;
        }
        .stat-lbl {
            font-size: 0.64rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            opacity: 0.6;
            margin-top: 4px;
        }

        /* TOOLBAR */
        .toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; flex-wrap: wrap; margin-bottom: 16px;
        }
        .toolbar-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1 1 320px; }
        .toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex-shrink: 0; }

        .search-box {
            position: relative;
            display: flex; align-items: center;
            flex: 1 1 320px;
            max-width: 480px;
        }
        .search-box input {
            width: 100%;
            background: var(--card-bg);
            color: var(--text-color);
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 10px 44px 10px 44px;
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
            position: absolute; left: 14px;
            color: var(--text-color); opacity: 0.6;
            pointer-events: none; font-size: 14px;
        }
        .search-box:focus-within .icon { color: var(--primary); opacity: 1; }
        .search-box .clear {
            position: absolute; right: 10px;
            background: var(--danger); color: #fff;
            border: 2px solid var(--shadow-color);
            border-radius: 50%;
            width: 24px; height: 24px;
            cursor: pointer;
            display: none; align-items: center; justify-content: center;
            font-size: 10px; padding: 0;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .search-box .clear.visible { display: flex; }

        /* BOTÕES */
        .btn-neo {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 18px;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            text-decoration: none;
        }
        .btn-neo:hover { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 0 var(--shadow-color); }
        .btn-neo:active { transform: translate(1px, 1px); box-shadow: 1px 1px 0 0 var(--shadow-color); }
        .btn-neo-primary { background: var(--btn-bg); color: var(--btn-text); }
        .btn-neo-primary:hover { background: var(--btn-hover); color: #fff; }
        .btn-neo-secondary { background: #fff; color: var(--text-color); }
        .btn-neo-secondary:hover { background: #f8f9fb; color: var(--text-color); }

        .btn-icon {
            width: 32px; height: 32px;
            padding: 0;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            display: inline-flex;
            align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            font-size: 0.78rem;
            text-decoration: none;
            background: #fff;
            color: var(--text-color);
        }
        .btn-icon:hover { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 0 var(--shadow-color); color: var(--text-color); }
        .btn-icon.edit   { background: var(--warning); color: #000; }
        .btn-icon.delete { background: var(--danger);  color: #fff; }

        /* TABELA */
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
            padding: 11px 12px !important;
            font-size: 13px;
            border: none !important;
            border-bottom: 1px solid rgba(0,0,0,0.07) !important;
            vertical-align: middle !important;
        }
        table.dataTable.table-neo tbody tr:last-child td { border-bottom: none !important; }
        table.dataTable.table-neo tbody tr:hover td { background: rgba(0,0,0,0.025); }
        table.dataTable.table-neo tbody tr.row-inativa td { opacity: 0.55; }

        .cell-id {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 0.74rem;
            background: rgba(0,0,0,0.06);
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 800;
        }
        .cell-nome { font-weight: 800; font-size: 0.86rem; }
        .cell-user {
            font-family: 'SFMono-Regular', Consolas, monospace;
            background: rgba(0,0,0,0.06);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 700;
        }
        .cell-date {
            font-size: 0.74rem;
            opacity: 0.55;
            font-weight: 600;
        }

        /* Badge ATIVO / INATIVO */
        .badge-estado {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .badge-estado.ativo   { background: var(--success); color: #fff; }
        .badge-estado.inativo { background: var(--danger);  color: #fff; }

        /* Badge Diretoria */
        .badge-diretoria {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            background: rgba(59,130,246,0.12);
            color: var(--info);
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            border: 1.5px solid rgba(59,130,246,0.35);
        }
        .badge-diretoria.empty {
            background: rgba(0,0,0,0.08);
            color: var(--text-color);
            opacity: 0.5;
            border-color: transparent;
        }

        /* DataTables */
        .dataTables_wrapper .dataTables_filter { display: none; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_info {
            padding: 12px 8px !important;
            font-size: 13px; font-weight: 600;
            color: var(--text-color);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            padding: 4px 8px;
            background: #fff; color: var(--text-color);
            font-weight: 700; margin: 0 4px;
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
            opacity: 0.4; transform: none; box-shadow: none;
        }
        .dataTables_wrapper .dataTables_empty {
            padding: 40px 20px !important;
            font-weight: 700 !important;
            opacity: 0.6 !important;
            font-style: italic;
        }

        /* Alert */
        .alert-neo {
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            padding: 14px 18px;
            font-weight: 700; font-size: 0.88rem;
            margin-bottom: 18px;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .alert-neo.success { background: var(--success); color: #fff; }
        .alert-neo.danger  { background: var(--danger);  color: #fff; }

        /* MODAL */
        .modal-content {
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 8px 8px 0 0 var(--shadow-color);
            overflow: hidden;
        }
        .modal-header {
            background: var(--primary);
            color: #fff;
            border-bottom: 3px solid var(--shadow-color);
            padding: 16px 22px;
        }
        .modal-title {
            font-weight: 900;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header .btn-close {
            filter: invert(1) brightness(2);
            opacity: 1;
        }
        .modal-body { padding: 22px; background: var(--card-bg); }
        .modal-footer {
            background: var(--card-bg);
            border-top: 3px solid var(--shadow-color);
            padding: 16px 22px;
            gap: 10px;
        }

        /* Form */
        .field-label {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.74rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.4px;
            color: var(--text-color); opacity: 0.85;
            margin-bottom: 6px;
        }
        .field-label i { color: var(--primary); font-size: 0.82rem; }
        .field-label .req { color: var(--danger); }

        .form-control, .form-select {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 14px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-color);
            background: #fff;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .form-control::placeholder { color: #b8bcc4; font-weight: 400; }

        /* FOOTER */
        .page-footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px 10px;
            font-size: 0.78rem;
            color: var(--text-color);
            opacity: 0.6;
            font-weight: 500;
        }
        .page-footer strong { color: var(--primary); opacity: 1; }

        /* Responsivo */
        @media (max-width: 768px) {
            .app-header { padding: 10px 0; }
            .header-inner { padding: 0 12px; }
            .header-logo { height: 40px; max-width: 100px; }
            .header-title h1 { font-size: 0.95rem; }
            .header-title p { font-size: 0.68rem; }
            .header-actions { width: 100%; }
            .btn-header { flex: 1; justify-content: center; }
            .container-neo { padding: 0 12px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .toolbar-left, .toolbar-right { width: 100%; }
            .search-box { max-width: 100%; flex-basis: 100%; }
            .btn-neo { flex: 1; justify-content: center; }
            .modal-body { padding: 16px; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="app-header">
    <div class="header-inner">
        <div class="header-brand">
            <img src="img/logosunny.jpg" alt="Logo" class="header-logo" onerror="this.style.display='none'">
            <div class="header-title">
                <h1><i class="fas fa-sitemap me-2"></i> Gerências</h1>
                <p>Estrutura organizacional · <?= $total ?> registros</p>
            </div>
        </div>
        <div class="header-actions">
            <button type="button" class="btn-header primary" onclick="abrirCriacao()">
                <i class="fas fa-plus"></i> Nova Gerência
            </button>
            <a href="#" onclick="window.close(); return false;" class="btn-header danger">
                <i class="fas fa-times"></i> Fechar
            </a>
        </div>
    </div>
</header>

<div class="container-neo">

    <?php if (isset($_SESSION['MsgSucesso'])): ?>
        <div class="alert-neo success">
            <i class="fas fa-check-circle"></i>
            <div><?= $_SESSION['MsgSucesso']; unset($_SESSION['MsgSucesso']); ?></div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['MsgErro'])): ?>
        <div class="alert-neo danger">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= $_SESSION['MsgErro']; unset($_SESSION['MsgErro']); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert-neo danger">
            <i class="fas fa-exclamation-triangle"></i>
            <div>Erro ao carregar: <?= htmlspecialchars($erro) ?></div>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon i-primary"><i class="fas fa-sitemap"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $total ?></div>
                <div class="stat-lbl">Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-success"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $ativas ?></div>
                <div class="stat-lbl">Ativas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-danger"><i class="fas fa-ban"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $inativas ?></div>
                <div class="stat-lbl">Inativas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-warning"><i class="fas fa-user-check"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $comUser ?></div>
                <div class="stat-lbl">Com responsável</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar gerência, diretoria, usuário…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="toolbar-right">
            <button type="button" class="btn-neo btn-neo-primary" onclick="abrirCriacao()">
                <i class="fas fa-plus"></i> Nova Gerência
            </button>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-card">
        <table id="tabelaGerencias" class="table table-neo" style="width:100%">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Diretoria</th>
                    <th>Gerência</th>
                    <th style="width: 180px;">Responsável</th>
                    <th style="width: 110px;">Estado</th>
                    <th style="width: 100px;">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($gerencias) && !$erro): ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding:40px 20px; opacity:0.6; font-weight:700; font-style:italic;">
                        <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                        Nenhuma gerência cadastrada. Clique em "Nova Gerência".
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($gerencias as $g):
                    $id        = (int)$g['GerenciaID'];
                    $nomeGer   = $g['NomeGerencia'] ?? '—';
                    $nomeDir   = $g['NomeDiretoria'] ?? '';
                    $usuario   = $g['Usuario'] ?? '';
                    $nomeUsr   = $g['NomeUsuario'] ?? '';
                    $isAtiva   = empty($g['DataEncerramento']);

                    $dataCriacao = '';
                    if (!empty($g['DataCriacao']) && $g['DataCriacao'] !== '0000-00-00 00:00:00') {
                        $dataCriacao = date('d/m/Y', strtotime($g['DataCriacao']));
                    }
                ?>
                    <tr data-id="<?= $id ?>" class="<?= $isAtiva ? '' : 'row-inativa' ?>">
                        <td><span class="cell-id">#<?= str_pad($id, 3, '0', STR_PAD_LEFT) ?></span></td>
                        <td>
                            <?php if ($nomeDir): ?>
                                <span class="badge-diretoria"><i class="fas fa-building"></i> <?= htmlspecialchars($nomeDir) ?></span>
                            <?php else: ?>
                                <span class="badge-diretoria empty">Sem diretoria</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="cell-nome"><?= htmlspecialchars($nomeGer) ?></span>
                            <?php if ($dataCriacao): ?>
                                <div class="cell-date"><i class="fas fa-calendar-alt me-1"></i><?= $dataCriacao ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($nomeUsr): ?>
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <i class="fas fa-user-circle" style="color:var(--primary);"></i>
                                    <div>
                                        <div style="font-weight:700;font-size:0.82rem;"><?= htmlspecialchars(strtoupper($nomeUsr)) ?></div>
                                        <?php if ($usuario): ?>
                                            <span class="cell-user" style="font-size:0.7rem;"><?= htmlspecialchars(strtolower($usuario)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span style="opacity:0.4;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isAtiva): ?>
                                <span class="badge-estado ativo"><i class="fas fa-check-circle"></i> Ativo</span>
                            <?php else: ?>
                                <span class="badge-estado inativo"><i class="fas fa-ban"></i> Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <button type="button" class="btn-icon edit" title="Editar"
                                        onclick="abrirEdicao(<?= $id ?>)">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="btn-icon delete" title="Excluir"
                                        onclick="confirmarExclusao(<?= $id ?>, <?= json_encode($nomeGer) ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <footer class="page-footer">
        <p style="margin:0;">
            &copy; <?= date('Y') ?> <strong>SunnyImob</strong> · Todos os direitos reservados
        </p>
    </footer>
</div>

<!-- ============================================================ -->
<!-- MODAL CRIAR / EDITAR                                        -->
<!-- ============================================================ -->
<div class="modal fade" id="modalGerencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="formGerencia">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="GerenciaID" id="formId" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="fas fa-plus-circle"></i> Nova Gerência
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="field-label" for="fieldNomeGerencia">
                                <i class="fas fa-sitemap"></i> Nome da Gerência <span class="req">*</span>
                            </label>
                            <input type="text" class="form-control" id="fieldNomeGerencia"
                                   name="NomeGerencia" required maxlength="255"
                                   placeholder="Ex.: Gerência Comercial">
                        </div>

                        <div class="col-md-6">
                            <label class="field-label" for="fieldDiretoria">
                                <i class="fas fa-building"></i> Diretoria <span class="req">*</span>
                            </label>
                            <select class="form-select" id="fieldDiretoria" name="DiretoriaID" required>
                                <option value="">— Selecione —</option>
                                <?php foreach ($diretorias as $d): ?>
                                    <option value="<?= (int)$d['DiretoriaID'] ?>">
                                        <?= htmlspecialchars($d['NomeDiretoria']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="field-label" for="fieldUserId">
                                <i class="fas fa-user"></i> Responsável
                            </label>
                            <select class="form-select" id="fieldUserId" name="UserId">
                                <option value="">— Nenhum —</option>
                                <?php foreach ($usuarios as $u): ?>
                                    <option value="<?= (int)$u['UserId'] ?>">
                                        <?= htmlspecialchars($u['Nome'] ?: $u['Usuario']) ?>
                                        (<?= htmlspecialchars($u['Usuario']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="field-label" for="fieldEstado">
                                <i class="fas fa-toggle-on"></i> Estado <span class="req">*</span>
                            </label>
                            <select class="form-select" id="fieldEstado" name="EstadoGerencia" required>
                                <option value="ATIVO">Ativo</option>
                                <option value="INATIVO">Inativo</option>
                            </select>
                            <small style="display:block;margin-top:6px;font-size:0.72rem;opacity:0.6;font-weight:500;">
                                <i class="fas fa-info-circle"></i>
                                Ao marcar <b>Inativo</b>, a gerência recebe uma data de encerramento.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-neo btn-neo-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-neo btn-neo-primary" id="btnSubmit">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- FORM OCULTO PARA DELETE                                     -->
<!-- ============================================================ -->
<form method="POST" id="formDelete" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="GerenciaID" id="deleteId" value="">
</form>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>

<script>
(function () {
    'use strict';

    const GERENCIAS = <?= $gerenciasJson ?>;

    // i18n embutido
    const ptBR = {
        emptyTable:     "Nenhum registro encontrado",
        info:           "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty:      "Mostrando 0 a 0 de 0 registros",
        infoFiltered:   "(filtrado de _MAX_ registros no total)",
        infoThousands:  ".",
        lengthMenu:     "Exibir _MENU_ por página",
        loadingRecords: "Carregando...",
        processing:     "Processando...",
        search:         "Pesquisar:",
        zeroRecords:    "Nenhum registro corresponde à busca",
        paginate: {
            first: "Primeiro", last: "Último",
            next: "Próximo", previous: "Anterior"
        },
        aria: {
            sortAscending:  ": ordenar crescente",
            sortDescending: ": ordenar decrescente"
        }
    };

    // ============================================================
    // DATATABLES
    // ============================================================
    let table = null;
    if (document.getElementById('tabelaGerencias')) {
        table = new DataTable('#tabelaGerencias', {
            language: ptBR,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            order: [[0, 'asc']],
            autoWidth: false,
            columnDefs: [
                { orderable: false, targets: [5] },
                { searchable: false, targets: [5] }
            ]
        });
    }

    // ============================================================
    // BUSCA
    // ============================================================
    const $search = document.getElementById('searchInput');
    const $clear  = document.getElementById('searchClear');
    let timer;

    if ($search) {
        $search.addEventListener('input', function () {
            const v = this.value;
            $clear.classList.toggle('visible', v.length > 0);
            clearTimeout(timer);
            timer = setTimeout(() => {
                if (table) table.search(v).draw();
            }, 150);
        });

        $search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { this.value = ''; this.dispatchEvent(new Event('input')); }
        });

        $clear.addEventListener('click', () => {
            $search.value = '';
            $search.dispatchEvent(new Event('input'));
            $search.focus();
        });
    }

    // ============================================================
    // MODAL
    // ============================================================
    const modalEl = document.getElementById('modalGerencia');
    const modal   = new bootstrap.Modal(modalEl);

    // ============================================================
    // ABRIR CRIAÇÃO
    // ============================================================
    window.abrirCriacao = function () {
        document.getElementById('modalTitle').innerHTML =
            '<i class="fas fa-plus-circle"></i> Nova Gerência';
        document.getElementById('formAction').value = 'create';
        document.getElementById('formId').value = '';
        document.getElementById('formGerencia').reset();
        document.getElementById('fieldEstado').value = 'ATIVO';
        document.getElementById('btnSubmit').disabled = false;
        document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Salvar';
        modal.show();
        setTimeout(() => document.getElementById('fieldNomeGerencia').focus(), 300);
    };

    // ============================================================
    // ABRIR EDIÇÃO
    // ============================================================
    window.abrirEdicao = function (id) {
        const g = GERENCIAS.find(x => Number(x.GerenciaID) === Number(id));
        if (!g) return;

        document.getElementById('modalTitle').innerHTML =
            '<i class="fas fa-pen"></i> Editar Gerência';
        document.getElementById('formAction').value = 'update';
        document.getElementById('formId').value = g.GerenciaID;

        document.getElementById('fieldNomeGerencia').value = g.NomeGerencia || '';
        document.getElementById('fieldDiretoria').value = g.DiretoriaID || '';
        document.getElementById('fieldUserId').value = g.UserId || '';

        // Estado derivado de DataEncerramento
        const isAtiva = !g.DataEncerramento || g.DataEncerramento === '0000-00-00 00:00:00';
        document.getElementById('fieldEstado').value = isAtiva ? 'ATIVO' : 'INATIVO';

        document.getElementById('btnSubmit').disabled = false;
        document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Salvar';

        modal.show();
    };

    // ============================================================
    // EXCLUSÃO
    // ============================================================
    window.confirmarExclusao = function (id, nome) {
        Swal.fire({
            title: 'Excluir gerência?',
            html: `A gerência <b>${nome}</b> será excluída permanentemente.<br>` +
                  `<span style="color:#ef4444;font-weight:700;font-size:0.85rem;">` +
                  `Gerências com usuários vinculados não podem ser excluídas.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280'
        }).then(r => {
            if (r.isConfirmed) {
                document.getElementById('deleteId').value = id;
                document.getElementById('formDelete').submit();
            }
        });
    };

    // ============================================================
    // SUBMIT
    // ============================================================
    document.getElementById('formGerencia')?.addEventListener('submit', function (e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            this.reportValidity();
            return;
        }
        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando…';
    });

    // ============================================================
    // TOAST pós-ação
    // ============================================================
    <?php if (isset($_SESSION['MsgSucesso']) && !empty($_SESSION['MsgSucesso'])): ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: <?= json_encode(strip_tags($_SESSION['MsgSucesso'])) ?>,
            timer: 4000,
            timerProgressBar: true,
            showConfirmButton: false
        });
    <?php endif; ?>

})();
</script>
</body>
</html>