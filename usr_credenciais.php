<?php
// usr_credenciais.php
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

// ============================================================
// USUÁRIO LOGADO
// ============================================================
$usuarioLogado = $_SESSION['Usuario'] ?? '';
$ehBarreto     = strtolower($usuarioLogado) === 'barreto';
$podeVerSenha  = $ehBarreto;

// ============================================================
// QUERY — TODOS OS USUÁRIOS + EMPRESA
// ============================================================
$empresas = [];
$erro     = null;
$totalUsuarios = 0;
$totalAtivos   = 0;

try {
    $sql = "
        SELECT 
            u.UserId,
            u.Usuario,
            u.Nome,
            u.SenhaAtual,
            u.Ativo,
            u.IdEmp,
            COALESCE(NULLIF(e.EmpresaSunny, ''), NULLIF(u.Empresa, ''), 'Sem empresa') AS EmpresaNome
        FROM usuarios u
        LEFT JOIN sunnyempresas e ON u.IdEmp = e.IdEmp
        ORDER BY EmpresaNome ASC, u.Nome ASC, u.Usuario ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $emp = $r['EmpresaNome'] ?: 'Sem empresa';
        if (!isset($empresas[$emp])) {
            $empresas[$emp] = [
                'nome'     => $emp,
                'usuarios' => [],
                'ativos'   => 0,
                'inativos' => 0,
            ];
        }
        $empresas[$emp]['usuarios'][] = $r;
        $totalUsuarios++;
        if ((int)$r['Ativo'] === 1) {
            $empresas[$emp]['ativos']++;
            $totalAtivos++;
        } else {
            $empresas[$emp]['inativos']++;
        }
    }

    // "Sem empresa" sempre por último
    uksort($empresas, function ($a, $b) {
        if ($a === 'Sem empresa') return 1;
        if ($b === 'Sem empresa') return -1;
        return strcasecmp($a, $b);
    });

} catch (PDOException $e) {
    $erro = $e->getMessage();
    error_log("[CRED_LIST] " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credenciais · SunnyImob</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: <?= $themeSafe['primary'] ?>;
            --primary-dark: <?= $themeSafe['primary_dark'] ?>;
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
            --purple: #8b5cf6;
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
        .header-actions { display: flex; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }
        .btn-header {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 8px 14px;
            font-weight: 800;
            font-size: 0.78rem;
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
        .btn-header.success { background: var(--success); }

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
        .stat-icon.i-info     { background: var(--info); }
        .stat-icon.i-success  { background: var(--success); }
        .stat-icon.i-warning  { background: var(--warning); color: #000; }
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

        /* ALERT */
        .alert-neo {
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            padding: 14px 18px;
            font-weight: 700; font-size: 0.88rem;
            margin-bottom: 18px;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .alert-neo.danger  { background: var(--danger); color: #fff; }
        .alert-neo.warning { background: var(--warning); color: #000; }

        /* TOOLBAR */
        .toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; flex-wrap: wrap; margin-bottom: 18px;
        }
        .toolbar-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1 1 320px; }
        .toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex-shrink: 0; }

        .search-box {
            position: relative;
            display: flex; align-items: center;
            flex: 1 1 320px;
            max-width: 520px;
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

        .neo-switch {
            display: inline-flex; align-items: center; gap: 8px;
            background: #fff;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            padding: 8px 12px;
            cursor: pointer;
            user-select: none;
            transition: all 0.15s;
            font-weight: 700; font-size: 0.78rem;
            color: var(--text-color);
            white-space: nowrap;
        }
        .neo-switch:hover { transform: translate(-1px, -1px); box-shadow: 4px 4px 0 0 var(--shadow-color); }
        .neo-switch input { display: none; }
        .neo-switch .switch-track {
            width: 36px; height: 20px;
            background: #d1d5db;
            border-radius: 999px;
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .neo-switch .switch-track::after {
            content: "";
            position: absolute;
            width: 16px; height: 16px;
            background: #fff;
            border-radius: 50%;
            top: 2px; left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .neo-switch input:checked + .switch-track { background: var(--primary); }
        .neo-switch input:checked + .switch-track::after { transform: translateX(16px); }

        .btn-tool {
            background: #fff;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            padding: 8px 14px;
            font-weight: 800;
            font-size: 0.78rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            color: var(--text-color);
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-tool:hover {
            transform: translate(-1px, -1px);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #f8f9fb;
        }
        .btn-tool.primary { background: var(--primary); color: #fff; }
        .btn-tool.primary:hover { background: var(--primary-dark); }

        /* EMPRESA CARD */
        .empresa-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            margin-bottom: 22px;
            overflow: hidden;
        }
        .empresa-card[data-oculta="1"] { display: none; }

        .empresa-header {
            background: var(--primary);
            color: #fff;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            border-bottom: 3px solid var(--shadow-color);
            cursor: pointer;
            user-select: none;
            list-style: none;
        }
        .empresa-header::-webkit-details-marker { display: none; }
        .empresa-header::-moz-list-bullet { display: none; }

        .empresa-title {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            font-weight: 900;
        }
        .empresa-title .chevron {
            font-size: 0.75rem;
            transition: transform 0.2s;
            opacity: 0.9;
        }
        details:not([open]) .chevron { transform: rotate(-90deg); }
        .empresa-title h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: -0.2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .empresa-count {
            background: rgba(0,0,0,0.22);
            border: 2px solid var(--shadow-color);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .empresa-meta {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
            align-items: center;
        }
        .empresa-meta .chip {
            background: rgba(255,255,255,0.18);
            border: 2px solid var(--shadow-color);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .empresa-meta .chip.ativo   { background: rgba(16,185,129,0.85); }
        .empresa-meta .chip.inativo { background: rgba(107,114,128,0.7); }

        .btn-copy-emp {
            background: rgba(255,255,255,0.18);
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            padding: 6px 10px;
            color: #fff;
            cursor: pointer;
            font-size: 0.72rem;
            font-weight: 800;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: inherit;
        }
        .btn-copy-emp:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            background: rgba(255,255,255,0.3);
        }

        .empresa-body { padding: 0; }
        .empresa-card .cred-table { margin: 0; }

        /* TABELA */
        .cred-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
        }
        .cred-table thead th {
            background: #f1f3f6;
            color: var(--text-color);
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 2px solid var(--shadow-color);
            white-space: nowrap;
        }
        .cred-table tbody td {
            padding: 11px 14px;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(0,0,0,0.07);
            vertical-align: middle;
        }
        .cred-table tbody tr:last-child td { border-bottom: none; }
        .cred-table tbody tr:hover td { background: rgba(0,0,0,0.025); }
        .cred-table tbody tr.inativo { opacity: 0.55; }

        .cell-nome { font-weight: 800; color: var(--text-color); }
        .cell-usuario {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-weight: 700;
            background: rgba(0,0,0,0.05);
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.78rem;
            display: inline-block;
            color: var(--primary);
        }
        .cell-senha {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--purple);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .pwd-mask {
            letter-spacing: 3px;
            opacity: 0.5;
        }
        .pwd-real {
            color: var(--purple);
            background: rgba(139,92,246,0.08);
            padding: 2px 6px;
            border-radius: 4px;
        }
        .pwd-lock {
            font-size: 0.7rem;
            color: #94a3b8;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 700;
        }

        .row-actions {
            display: inline-flex;
            gap: 4px;
            justify-content: flex-end;
        }
        .btn-mini {
            width: 30px; height: 30px;
            border: 2px solid var(--shadow-color);
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            font-size: 0.75rem;
            background: #fff;
            color: var(--text-color);
            padding: 0;
        }
        .btn-mini:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .btn-mini.eye   { background: var(--warning); color: #000; }
        .btn-mini.copy  { background: var(--info);    color: #fff; }
        .btn-mini.copys { background: var(--purple);  color: #fff; }

        /* EMPTY */
        .empty-state {
            background: var(--card-bg);
            border: 3px dashed var(--shadow-color);
            border-radius: 16px;
            padding: 50px 20px;
            text-align: center;
            color: var(--text-color);
        }
        .empty-state i {
            font-size: 2.5rem;
            opacity: 0.4;
            margin-bottom: 12px;
            display: block;
        }
        .empty-state h3 {
            font-weight: 900;
            font-size: 1.05rem;
            margin: 0 0 6px;
        }
        .empty-state p {
            opacity: 0.6;
            font-weight: 500;
            font-size: 0.85rem;
            margin: 0;
        }

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

        /* PRINT */
        @media print {
            body {
                background: #fff !important;
                background-image: none !important;
                padding: 0 !important;
            }
            .app-header, .stats-grid, .toolbar, .no-print, .row-actions, .page-footer {
                display: none !important;
            }
            .container-neo { max-width: 100%; padding: 0; }
            .empresa-card {
                box-shadow: none !important;
                border: 1px solid #333 !important;
                margin-bottom: 12px !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .empresa-header {
                background: #eee !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .empresa-title h2 { color: #000 !important; }
            .empresa-count, .empresa-meta .chip {
                background: #fff !important;
                color: #000 !important;
                border: 1px solid #999 !important;
            }
            details { display: block !important; }
            details > .empresa-body { display: block !important; }
            .pwd-mask { display: none !important; }
            .pwd-real { display: inline !important; }
            .cred-table thead th { background: #f4f4f4 !important; }
        }

        /* RESPONSIVO */
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
            .empresa-header { padding: 12px 14px; }
            .empresa-title h2 { font-size: 0.9rem; }
            .empresa-meta .chip { display: none; }
            .cred-table thead th,
            .cred-table tbody td { padding: 8px 10px; font-size: 0.78rem; }
            .cell-usuario, .cell-senha { font-size: 0.74rem; }
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
                <h1><i class="fas fa-key me-2"></i> Credenciais de Usuários</h1>
                <p><?= $totalUsuarios ?> usuário(s) · <?= count($empresas) ?> empresa(s)</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="usr_listar.php" class="btn-header">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <button type="button" class="btn-header success no-print" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimir
            </button>
            <button type="button" class="btn-header primary no-print" onclick="exportarCSV()">
                <i class="fas fa-file-csv"></i> CSV
            </button>
        </div>
    </div>
</header>

<div class="container-neo">

    <?php if ($erro): ?>
        <div class="alert-neo danger">
            <i class="fas fa-exclamation-circle"></i>
            <div>Erro ao carregar: <?= htmlspecialchars($erro) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!$podeVerSenha): ?>
        <div class="alert-neo warning no-print">
            <i class="fas fa-lock"></i>
            <div>
                Você não tem permissão para visualizar as senhas.
                A coluna <strong>Senha</strong> está mascarada.
            </div>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon i-primary"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-num"><?= $totalUsuarios ?></div>
                <div class="stat-lbl">Total de usuários</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-info"><i class="fas fa-building"></i></div>
            <div>
                <div class="stat-num"><?= count($empresas) ?></div>
                <div class="stat-lbl">Empresas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-success"><i class="fas fa-user-check"></i></div>
            <div>
                <div class="stat-num"><?= $totalAtivos ?></div>
                <div class="stat-lbl">Usuários ativos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-warning"><i class="fas fa-user-slash"></i></div>
            <div>
                <div class="stat-num"><?= $totalUsuarios - $totalAtivos ?></div>
                <div class="stat-lbl">Inativos</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar no-print">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput"
                       placeholder="Buscar nome, usuário ou empresa…"
                       autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <label class="neo-switch" title="Mostrar apenas usuários ativos">
                <input type="checkbox" id="filterAtivo">
                <span class="switch-track"></span>
                <span><i class="fas fa-user-check"></i> Só ativos</span>
            </label>
        </div>
        <div class="toolbar-right">
            <?php if ($podeVerSenha): ?>
                <button type="button" class="btn-tool" id="btnToggleAll" data-state="hidden">
                    <i class="fas fa-eye"></i> <span>Mostrar todas as senhas</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lista agrupada -->
    <?php if (empty($empresas)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Nenhum usuário cadastrado</h3>
            <p>Cadastre um usuário em <code>usr_novo.php</code> para vê-lo aqui.</p>
        </div>
    <?php else: ?>
        <?php foreach ($empresas as $emp): ?>
            <?php
            $empNome = $emp['nome'];
            $empUsuarios = $emp['usuarios'];
            $empAtivos   = $emp['ativos'];
            $empInativos = $emp['inativos'];
            ?>
            <details class="empresa-card"
                     open
                     data-empresa="<?= htmlspecialchars($empNome) ?>"
                     data-search="<?= htmlspecialchars(mb_strtolower($empNome)) ?>">

                <summary class="empresa-header">
                    <div class="empresa-title">
                        <i class="fas fa-chevron-down chevron"></i>
                        <i class="fas fa-building"></i>
                        <h2><?= htmlspecialchars($empNome) ?></h2>
                        <span class="empresa-count"><?= count($empUsuarios) ?> usuário(s)</span>
                    </div>
                    <div class="empresa-meta">
                        <span class="chip ativo">
                            <i class="fas fa-check-circle"></i> <?= $empAtivos ?>
                        </span>
                        <span class="chip inativo">
                            <i class="fas fa-ban"></i> <?= $empInativos ?>
                        </span>
                        <button type="button" class="btn-copy-emp no-print"
                                onclick="event.preventDefault(); event.stopPropagation(); copiarEmpresa(this);"
                                title="Copiar todas as credenciais desta empresa">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                    </div>
                </summary>

                <div class="empresa-body">
                    <table class="cred-table">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Nome completo</th>
                                <th style="width: 28%;">Usuário</th>
                                <th style="width: 22%;">Senha</th>
                                <th style="width: 10%; text-align:right;" class="no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($empUsuarios as $u):
                                $ativo = (int)$u['Ativo'] === 1;
                                $nome  = trim($u['Nome'] ?? '') ?: '—';
                                $login = $u['Usuario'] ?? '';
                                $senha = $u['SenhaAtual'] ?? '';
                                $uid   = (int)$u['UserId'];
                                $rowSearch = mb_strtolower($nome . ' ' . $login . ' ' . $empNome);
                                $dataCred  = $login . ' | ' . ($podeVerSenha ? $senha : '***');
                            ?>
                                <tr class="<?= $ativo ? '' : 'inativo' ?>"
                                    data-ativo="<?= $ativo ? '1' : '0' ?>"
                                    data-search="<?= htmlspecialchars($rowSearch) ?>"
                                    data-cred="<?= htmlspecialchars($dataCred) ?>"
                                    data-usuario="<?= htmlspecialchars($login) ?>"
                                    data-senha="<?= htmlspecialchars($senha) ?>">
                                    <td class="cell-nome"><?= htmlspecialchars($nome) ?></td>
                                    <td>
                                        <span class="cell-usuario"><?= htmlspecialchars($login) ?></span>
                                    </td>
                                    <td>
                                        <div class="cell-senha">
                                            <?php if ($podeVerSenha && $senha !== ''): ?>
                                                <span class="pwd-mask" data-uid="<?= $uid ?>">••••••••</span>
                                                <span class="pwd-real" data-uid="<?= $uid ?>" style="display:none;"><?= htmlspecialchars($senha) ?></span>
                                            <?php elseif ($podeVerSenha): ?>
                                                <span style="opacity:0.4;">—</span>
                                            <?php else: ?>
                                                <span class="pwd-lock"><i class="fas fa-lock"></i> restrito</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="no-print" style="text-align:right;">
                                        <div class="row-actions">
                                            <?php if ($podeVerSenha && $senha !== ''): ?>
                                                <button type="button" class="btn-mini eye"
                                                        onclick="toggleSenhaLinha(<?= $uid ?>, this)"
                                                        title="Mostrar/ocultar senha">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn-mini copy"
                                                    onclick="copiarTexto(this.closest('tr').dataset.usuario, 'Usuário')"
                                                    title="Copiar usuário">
                                                <i class="fas fa-user"></i>
                                            </button>
                                            <?php if ($podeVerSenha && $senha !== ''): ?>
                                                <button type="button" class="btn-mini copys"
                                                        onclick="copiarTexto(this.closest('tr').dataset.senha, 'Senha')"
                                                        title="Copiar senha">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>

    <footer class="page-footer">
        <p style="margin:0;">
            &copy; <?= date('Y') ?> <strong>SunnyImob</strong> · Todos os direitos reservados
        </p>
    </footer>
</div>

<script>
(function () {
    'use strict';

    const PODE_VER_SENHA = <?= $podeVerSenha ? 'true' : 'false' ?>;

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1800,
        timerProgressBar: true
    });

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => (
            { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]
        ));
    }

    // ============================================================
    // COPIAR
    // ============================================================
    async function copiarTexto(texto, rotulo) {
        if (!texto) { return; }
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(texto);
            } else {
                const ta = document.createElement('textarea');
                ta.value = texto;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
            }
            Toast.fire({ icon: 'success', title: (rotulo || 'Texto') + ' copiado!' });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Falha ao copiar', text: e.message || '' });
        }
    }
    window.copiarTexto = copiarTexto;

    // ============================================================
    // SENHA — TOGGLE POR LINHA
    // ============================================================
    function toggleSenhaLinha(uid, btn) {
        const mask = document.querySelector('.pwd-mask[data-uid="' + uid + '"]');
        const real = document.querySelector('.pwd-real[data-uid="' + uid + '"]');
        if (!mask || !real) return;

        const visivel = real.style.display !== 'none';
        if (visivel) {
            real.style.display = 'none';
            mask.style.display = 'inline';
            btn.querySelector('i').className = 'fas fa-eye';
            btn.title = 'Mostrar senha';
        } else {
            real.style.display = 'inline';
            mask.style.display = 'none';
            btn.querySelector('i').className = 'fas fa-eye-slash';
            btn.title = 'Ocultar senha';
        }
    }
    window.toggleSenhaLinha = toggleSenhaLinha;

    // ============================================================
    // SENHA — TOGGLE GLOBAL (só para quem pode ver)
    // ============================================================
    const btnToggleAll = document.getElementById('btnToggleAll');
    btnToggleAll?.addEventListener('click', function () {
        const mostrar = this.dataset.state !== 'shown';
        document.querySelectorAll('.pwd-mask').forEach(el => {
            el.style.display = mostrar ? 'none' : 'inline';
        });
        document.querySelectorAll('.pwd-real').forEach(el => {
            el.style.display = mostrar ? 'inline' : 'none';
        });
        document.querySelectorAll('.btn-mini.eye i').forEach(icon => {
            icon.className = mostrar ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
        this.dataset.state = mostrar ? 'shown' : 'hidden';
        this.innerHTML = mostrar
            ? '<i class="fas fa-eye-slash"></i> <span>Ocultar todas as senhas</span>'
            : '<i class="fas fa-eye"></i> <span>Mostrar todas as senhas</span>';
    });

    // ============================================================
    // COPIAR CREDENCIAIS DE UMA EMPRESA
    // ============================================================
    window.copiarEmpresa = function (btn) {
        const card = btn.closest('.empresa-card');
        if (!card) return;
        const empresa = card.dataset.empresa || '';
        const linhas = card.querySelectorAll('tbody tr');

        const partes = ['=== ' + empresa + ' ==='];
        linhas.forEach(tr => {
            if (tr.style.display === 'none') return;
            const nome = tr.querySelector('.cell-nome')?.textContent.trim() || '';
            const usuario = tr.dataset.usuario || '';
            const senha = PODE_VER_SENHA ? (tr.dataset.senha || '') : '';
            partes.push(nome + '\t' + usuario + (senha ? '\t' + senha : ''));
        });

        if (partes.length <= 1) {
            Toast.fire({ icon: 'info', title: 'Nada visível para copiar' });
            return;
        }
        copiarTexto(partes.join('\n'), 'Credenciais da empresa');
    };

    // ============================================================
    // BUSCA
    // ============================================================
    const searchInput = document.getElementById('searchInput');
    const searchClear = document.getElementById('searchClear');
    let timer;

    function aplicarFiltros() {
        const q       = (searchInput?.value || '').toLowerCase().trim();
        const soAtivo = document.getElementById('filterAtivo')?.checked || false;

        document.querySelectorAll('.empresa-card').forEach(card => {
            const empSearch = card.dataset.search || '';
            const empMatch  = !q || empSearch.includes(q);
            const rows      = card.querySelectorAll('tbody tr');
            let visiveis    = 0;

            rows.forEach(tr => {
                const rowSearch = tr.dataset.search || '';
                const ativo     = tr.dataset.ativo === '1';
                const qMatch    = !q || rowSearch.includes(q) || empMatch;
                const aMatch    = !soAtivo || ativo;
                const show      = qMatch && aMatch;
                tr.style.display = show ? '' : 'none';
                if (show) visiveis++;
            });

            card.dataset.oculta = visiveis === 0 ? '1' : '0';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            searchClear?.classList.toggle('visible', this.value.length > 0);
            clearTimeout(timer);
            timer = setTimeout(aplicarFiltros, 120);
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.dispatchEvent(new Event('input'));
            }
        });
    }
    searchClear?.addEventListener('click', () => {
        if (searchInput) {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.focus();
        }
    });

    document.getElementById('filterAtivo')?.addEventListener('change', aplicarFiltros);

    // ============================================================
    // EXPORTAR CSV
    // ============================================================
    function csvEscape(v) {
        const s = String(v ?? '');
        return /[",\n;]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    }

    window.exportarCSV = function () {
        const linhas = [['Empresa', 'Nome', 'Usuario'].concat(PODE_VER_SENHA ? ['Senha'] : []).concat(['Ativo'])];

        document.querySelectorAll('.empresa-card').forEach(card => {
            if (card.dataset.oculta === '1') return;
            const empresa = card.dataset.empresa || '';
            card.querySelectorAll('tbody tr').forEach(tr => {
                if (tr.style.display === 'none') return;
                const nome    = tr.querySelector('.cell-nome')?.textContent.trim() || '';
                const usuario = tr.dataset.usuario || '';
                const ativo   = tr.dataset.ativo === '1' ? 'Sim' : 'Não';
                const linha = [empresa, nome, usuario];
                if (PODE_VER_SENHA) linha.push(tr.dataset.senha || '');
                linha.push(ativo);
                linhas.push(linha);
            });
        });

        if (linhas.length <= 1) {
            Swal.fire({ icon: 'info', title: 'Nada para exportar', text: 'Ajuste os filtros e tente novamente.' });
            return;
        }

        const csv = '\uFEFF' + linhas.map(l => l.map(csvEscape).join(',')).join('\r\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        const hoje = new Date().toISOString().slice(0, 10);
        a.href     = url;
        a.download = 'credenciais_' + hoje + '.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        Toast.fire({ icon: 'success', title: 'CSV exportado!' });
    };

    // ============================================================
    // INICIALIZAÇÃO
    // ============================================================
    aplicarFiltros();
})();
</script>

</body>
</html>