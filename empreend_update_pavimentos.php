<?php
// ============================================================
// SESSION PRIMEIRO
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
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
if (empty($_SESSION['csrf_pav'])) {
    $_SESSION['csrf_pav'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_pav'];

// ============================================================
// AJAX HANDLER (POST) — CORREÇÃO CRÍTICA
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_pavimentos') {
    header('Content-Type: application/json; charset=utf-8');
    ob_start(); // captura warnings

    // CSRF
    if (!hash_equals($CSRF, $_POST['csrf_token'] ?? '')) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Falha de segurança (CSRF). Recarregue.']);
        exit;
    }

    $id  = (int)($_POST['id'] ?? 0);
    $pav = (int)($_POST['pavimentos'] ?? 0);

    if ($id <= 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit;
    }
    if ($pav < 0 || $pav > 100) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Valor deve estar entre 0 e 100.']);
        exit;
    }

    try {
        $chk = $db->prepare("SELECT 1 FROM empreendimento WHERE Empreend_ID = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Empreendimento não encontrado.']);
            exit;
        }

        $stmt = $db->prepare("UPDATE empreendimento SET Pavimentos = ? WHERE Empreend_ID = ?");
        $stmt->execute([$pav, $id]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Pavimentos atualizados!', 'id' => $id, 'pavimentos' => $pav]);
    } catch (PDOException $e) {
        error_log("[PAVIMENTOS] " . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar.']);
    }
    exit;
}

// ============================================================
// CARREGAR EMPREENDIMENTOS (com JOIN para NomeEmpresa)
// ============================================================
$empreendimentos = [];
$erro = null;

try {
    $stmt = $db->prepare("
        SELECT e.Empreend_ID, e.NomeEmpreendimento, e.Empresa_Id,
               e.Pavimentos, e.Localidade,
               COALESCE(emp.NomeEmpresa, '—') AS NomeEmpresa
        FROM empreendimento e
        LEFT JOIN empresa emp ON e.Empresa_Id = emp.Empresa_Id
        ORDER BY e.NomeEmpreendimento ASC
    ");
    $stmt->execute();
    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erro = $e->getMessage();
    error_log("[PAVIMENTOS_LIST] " . $e->getMessage());
}

// Estatísticas
$total      = count($empreendimentos);
$comPav     = 0;
$semPav     = 0;
foreach ($empreendimentos as $e) {
    $p = (int)($e['Pavimentos'] ?? 0);
    if ($p > 0) $comPav++;
    else        $semPav++;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Pavimentos · SunnyImob</title>

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

        .container-neo { max-width: 1500px; margin: 0 auto; padding: 0 20px; }

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
            max-width: 1500px;
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
        .btn-header.danger { background: var(--danger); }

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
        .stat-icon.i-warning  { background: var(--warning); color: #000; }
        .stat-icon.i-info     { background: var(--info); }
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
            padding: 8px 14px;
            font-weight: 800;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            white-space: nowrap;
        }
        .btn-neo:hover { transform: translate(-2px, -2px); box-shadow: 4px 4px 0 0 var(--shadow-color); }
        .btn-neo:active { transform: translate(1px, 1px); box-shadow: 1px 1px 0 0 var(--shadow-color); }
        .btn-neo-primary { background: var(--btn-bg); color: var(--btn-text); }
        .btn-neo-primary:hover { background: var(--btn-hover); color: #fff; }
        .btn-neo:disabled { opacity: 0.5; cursor: not-allowed; }

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
            font-size: 0.7rem;
            letter-spacing: 1px;
            padding: 12px 10px !important;
            border: none !important;
            border-bottom: 3px solid var(--shadow-color) !important;
            white-space: nowrap;
        }
        table.dataTable.table-neo thead th:first-child { border-top-left-radius: 10px; }
        table.dataTable.table-neo thead th:last-child  { border-top-right-radius: 10px; }
        table.dataTable.table-neo tbody td {
            padding: 10px !important;
            font-size: 13px;
            border: none !important;
            border-bottom: 1px solid rgba(0,0,0,0.07) !important;
            vertical-align: middle !important;
        }
        table.dataTable.table-neo tbody tr:last-child td { border-bottom: none !important; }
        table.dataTable.table-neo tbody tr:hover td { background: rgba(0,0,0,0.025); }

        /* Linha atualizada */
        table.dataTable.table-neo tbody tr.row-success td { background: rgba(16,185,129,0.12); }
        table.dataTable.table-neo tbody tr.row-error   td { background: rgba(239,68,68,0.12); }

        /* Célula ID */
        .cell-id {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 0.74rem;
            background: rgba(0,0,0,0.06);
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 800;
        }
        .cell-nome { font-weight: 800; font-size: 0.86rem; }
        .cell-empresa {
            font-size: 0.72rem;
            opacity: 0.6;
            font-weight: 600;
            margin-top: 2px;
        }

        /* Thumbnail */
        .thumb {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            cursor: pointer;
            transition: all 0.15s;
            display: block;
            background: #f8f9fb;
        }
        .thumb:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }

        /* Badge pavimentos */
        .badge-pav {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.4px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            min-width: 50px;
            justify-content: center;
        }
        .badge-pav.com-valor { background: var(--info); color: #fff; }
        .badge-pav.sem-valor { background: rgba(0,0,0,0.08); color: var(--text-color); opacity: 0.5; box-shadow: none; }

        /* Badge localidade */
        .badge-loc {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 800;
            background: rgba(0,0,0,0.06);
            color: var(--text-color);
            border: 1.5px solid rgba(0,0,0,0.1);
        }

        /* Input pavimentos */
        .input-pav {
            display: flex;
            align-items: stretch;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            transition: all 0.15s;
            max-width: 110px;
        }
        .input-pav:focus-within {
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }
        .input-pav input {
            border: none;
            background: transparent;
            text-align: center;
            font-family: inherit;
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--text-color);
            padding: 6px 4px;
            width: 60px;
            outline: none;
        }
        .input-pav input::-webkit-outer-spin-button,
        .input-pav input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .input-pav input[type=number] { -moz-appearance: textfield; }

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
        .alert-neo.danger  { background: var(--danger);  color: #fff; }
        .alert-neo.info    { background: var(--info);    color: #fff; }

        /* Image Modal */
        #image-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(0,0,0,0.9);
            justify-content: center;
            align-items: center;
            padding: 20px;
            cursor: zoom-out;
        }
        #image-modal img {
            max-width: 95vw;
            max-height: 95vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            cursor: default;
        }
        #image-modal .close-x {
            position: absolute;
            top: 20px;
            right: 30px;
            color: #fff;
            font-size: 2.5rem;
            font-weight: 900;
            cursor: pointer;
            line-height: 1;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            transition: transform 0.15s;
        }
        #image-modal .close-x:hover { transform: scale(1.2); }

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
            .toolbar-left { flex-basis: 100%; }
            .search-box { max-width: 100%; flex-basis: 100%; }
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
                <h1><i class="fas fa-layer-group me-2"></i> Atualizar Pavimentos</h1>
                <p>Edição rápida · <?= $total ?> empreendimentos</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="#" onclick="fecharJanela(event); return false;" class="btn-header danger">
                <i class="fas fa-times"></i> Fechar
            </a>
        </div>
    </div>
</header>

<div class="container-neo">

    <?php if ($erro): ?>
        <div class="alert-neo danger">
            <i class="fas fa-exclamation-triangle"></i>
            <div>Erro ao carregar: <?= htmlspecialchars($erro) ?></div>
        </div>
    <?php endif; ?>

    <!-- Info banner -->
    <div class="alert-neo info">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Como usar:</strong> digite o número de pavimentos e clique em <b>Atualizar</b>.
            Clique na imagem para ampliar. Alterações são salvas em tempo real.
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon i-primary"><i class="fas fa-building"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $total ?></div>
                <div class="stat-lbl">Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-success"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $comPav ?></div>
                <div class="stat-lbl">Com pavimentos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-warning"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $semPav ?></div>
                <div class="stat-lbl">Sem pavimentos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-info"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= date('d/m') ?></div>
                <div class="stat-lbl">Hoje</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar empreendimento, localidade, empresa…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="table-card">
        <table id="tabelaPavimentos" class="table table-neo" style="width:100%">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th style="width: 100px;">Imagem</th>
                    <th style="width: 130px;">Pavimentos Atual</th>
                    <th style="width: 130px;">Novos</th>
                    <th style="width: 110px;">Ação</th>
                    <th style="width: 160px;">Localidade</th>
                    <th>Empreendimento</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($empreendimentos) && !$erro): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px 20px; opacity:0.6; font-weight:700; font-style:italic;">
                        <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                        Nenhum empreendimento cadastrado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($empreendimentos as $e):
                    $id       = (int)$e['Empreend_ID'];
                    $pav      = (int)($e['Pavimentos'] ?? 0);
                    $loc      = $e['Localidade'] ?? '';
                    $nomeEmp  = $e['NomeEmpreendimento'] ?? '—';
                    $empresa  = $e['NomeEmpresa'] ?? '—';
                    $imgSrc   = "card/{$id}.jpg";
                ?>
                    <tr id="linha_<?= $id ?>" data-id="<?= $id ?>">
                        <td><span class="cell-id">#<?= str_pad($id, 3, '0', STR_PAD_LEFT) ?></span></td>
                        <td>
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                 alt="Card <?= $id ?>"
                                 class="thumb"
                                 onclick="showImageModal(this.src)"
                                 onerror="this.onerror=null;this.src='https://placehold.co/80x60/cccccc/333333?text=?';">
                        </td>
                        <td>
                            <span class="badge-pav <?= $pav > 0 ? 'com-valor' : 'sem-valor' ?>"
                                  data-badge-pav="<?= $id ?>">
                                <?= $pav ?>
                            </span>
                        </td>
                        <td>
                            <div class="input-pav">
                                <input type="number"
                                       id="pavimentos_<?= $id ?>"
                                       value="<?= $pav ?>"
                                       min="0" max="100" step="1">
                            </div>
                        </td>
                        <td>
                            <button type="button"
                                    class="btn-neo btn-neo-primary btn-atualizar"
                                    data-id="<?= $id ?>">
                                <i class="fas fa-sync-alt"></i> Atualizar
                            </button>
                        </td>
                        <td>
                            <?php if ($loc): ?>
                                <span class="badge-loc"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($loc) ?></span>
                            <?php else: ?>
                                <span style="opacity:0.4;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="cell-nome"><?= htmlspecialchars($nomeEmp) ?></div>
                            <div class="cell-empresa"><?= htmlspecialchars($empresa) ?></div>
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

<!-- Image Modal -->
<div id="image-modal" onclick="if (event.target === this) closeImageModal();">
    <span class="close-x" onclick="closeImageModal();">&times;</span>
    <img id="modal-image" alt="Ampliada">
</div>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>

<script>
(function () {
    'use strict';

    const CSRF = <?= json_encode($CSRF) ?>;

    // ============================================================
    // i18n embutido
    // ============================================================
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

    let table = null;
    if (document.getElementById('tabelaPavimentos')) {
        table = new DataTable('#tabelaPavimentos', {
            language: ptBR,
            pageLength: 50,
            lengthMenu: [[25, 50, 100, 200, -1], [25, 50, 100, 200, "Todos"]],
            order: [[0, 'asc']],
            autoWidth: false,
            columnDefs: [
                { orderable: false, targets: [1, 3, 4] },
                { searchable: false, targets: [1, 3, 4] }
            ]
        });
    }

    // ============================================================
    // Busca customizada
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
    // Atualizar pavimentos (delegação)
    // ============================================================
    function atualizarPavimentos(id, buttonEl) {
        const $btn  = $(buttonEl);
        const $inp  = $('#pavimentos_' + id);
        const $row  = $('#linha_' + id);
        const valor = parseInt($inp.val(), 10);

        if (isNaN(valor) || valor < 0 || valor > 100) {
            Swal.fire({
                toast: true, position: 'top-end', icon: 'warning',
                title: 'Informe um valor entre 0 e 100.',
                timer: 3000, showConfirmButton: false
            });
            $inp.focus();
            return;
        }

        const htmlOriginal = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando…');
        $inp.prop('disabled', true);
        $row.removeClass('row-success row-error');

        fetch(window.location.pathname, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({
                action: 'update_pavimentos',
                csrf_token: CSRF,
                id: id,
                pavimentos: valor
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Atualiza o badge CORRETO (por data-attribute)
                const $badge = $('[data-badge-pav="' + id + '"]');
                $badge.text(res.pavimentos);
                $badge.removeClass('sem-valor').addClass('com-valor');

                $row.removeClass('row-error').addClass('row-success');
                setTimeout(() => $row.removeClass('row-success'), 2000);

                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: res.message,
                    timer: 2500, timerProgressBar: true, showConfirmButton: false
                });
            } else {
                $row.removeClass('row-success').addClass('row-error');
                setTimeout(() => $row.removeClass('row-error'), 3000);
                Swal.fire({
                    icon: 'error', title: 'Erro',
                    text: res.message || 'Erro ao atualizar.',
                    confirmButtonColor: '<?= $themeSafe['primary'] ?>'
                });
            }
        })
        .catch(err => {
            console.error(err);
            $row.removeClass('row-success').addClass('row-error');
            setTimeout(() => $row.removeClass('row-error'), 3000);
            Swal.fire({
                icon: 'error', title: 'Falha na comunicação',
                text: 'Verifique o console ou tente novamente.',
                confirmButtonColor: '<?= $themeSafe['primary'] ?>'
            });
        })
        .finally(() => {
            $btn.prop('disabled', false).html(htmlOriginal);
            $inp.prop('disabled', false);
        });
    }

    // Delegação — funciona com paginação
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-atualizar');
        if (!btn) return;
        const id = parseInt(btn.dataset.id, 10);
        if (id > 0) atualizarPavimentos(id, btn);
    });

    // Enter no input também salva
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const inp = e.target;
        if (!inp.id || !inp.id.startsWith('pavimentos_')) return;
        const id = parseInt(inp.id.replace('pavimentos_', ''), 10);
        if (id <= 0) return;
        e.preventDefault();
        const btn = document.querySelector('.btn-atualizar[data-id="' + id + '"]');
        if (btn) atualizarPavimentos(id, btn);
    });

    // ============================================================
    // Image modal
    // ============================================================
    window.showImageModal = function (src) {
        if (!src) return;
        document.getElementById('modal-image').src = src;
        document.getElementById('image-modal').style.display = 'flex';
    };

    window.closeImageModal = function () {
        document.getElementById('image-modal').style.display = 'none';
        document.getElementById('modal-image').src = '';
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeImageModal();
    });

    // ============================================================
    // Fechar janela com confirmação
    // ============================================================
    window.fecharJanela = function (event) {
        if (event) event.preventDefault();
        Swal.fire({
            title: 'Fechar janela?',
            text: 'Você pode perder alterações não salvas.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, fechar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '<?= $themeSafe['primary'] ?>',
            cancelButtonColor: '#6b7280'
        }).then(r => {
            if (r.isConfirmed) window.close();
        });
    };

    // ============================================================
    // Enter também submete — chave para inputs numéricos
    // ============================================================
    // (já coberto acima)

})();
</script>
</body>
</html>