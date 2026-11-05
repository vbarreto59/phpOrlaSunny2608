<?php
// session_start() primeiro
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'theme_config.php';

// --- Conexão ---
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
elseif (isset($db) && $db instanceof PDO)      { /* ok */ }
else die("Erro: conexão com o banco não encontrada (verifique conn.php).");

// --- Tema seguro ---
$themeDefaults = [
    'primary'       => '#800020',
    'primary_dark'  => '#4B0C1D',
    'secondary'     => '#6c757d',
    'accent'        => '#ffc107',
    'bg_body'       => '#f0f2f5',
    'text_color'    => '#1a1a2e',
    'card_bg'       => '#ffffff',
    'shadow_color'  => '#1a1a2e',
    'btn_bg'        => '#800020',
    'btn_hover'     => '#4B0C1D',
    'btn_text'      => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// --- Carrega empreendimentos ---
$empreendimentos = [];
$error_message   = null;

try {
    // ✅ CORREÇÃO: JOIN com empresa
    $sql = "SELECT e.Empreend_ID, e.NomeEmpreendimento, e.Empresa_Id, e.ComissaoVenda,
                   COALESCE(emp.NomeEmpresa, '—') AS NomeEmpresa
            FROM empreendimento e
            LEFT JOIN empresa emp ON e.Empresa_Id = emp.Empresa_Id
            ORDER BY e.NomeEmpreendimento";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erro na consulta SQL: " . $e->getMessage();
    error_log("Erro ao carregar empreendimentos (comissão): " . $e->getMessage());
}

// --- Estatísticas do header ---
$total          = count($empreendimentos);
$comComissao    = 0;
$semComissao    = 0;
$comissaoMedia  = 0;
$somaComissoes  = 0;
foreach ($empreendimentos as $e) {
    $c = (float)($e['ComissaoVenda'] ?? 0);
    if ($c > 0) { $comComissao++; $somaComissoes += $c; }
    else        { $semComissao++; }
}
$comissaoMedia = $comComissao > 0 ? round($somaComissoes / $comComissao, 2) : 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Comissão de Venda</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            padding: 0;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================================ */
        /* HEADER                                                       */
        /* ============================================================ */
        .header-neo {
            background: var(--primary);
            color: #fff;
            padding: 14px 0;
            border-bottom: 5px solid var(--shadow-color);
            box-shadow: 0 6px 0 0 var(--shadow-color);
            margin-bottom: 26px;
            position: relative;
        }
        .header-neo::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .header-brand { display: flex; align-items: center; gap: 16px; min-width: 0; }
        .header-neo .logo-img {
            height: 62px;
            width: auto;
            max-width: 180px;
            min-width: 62px;
            object-fit: contain;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #fff;
            padding: 5px 8px;
            flex-shrink: 0;
        }
        .header-brand-text h1 {
            font-weight: 900;
            font-size: 1.35rem;
            margin: 0;
            line-height: 1.15;
            letter-spacing: -0.4px;
        }
        .header-brand-text p {
            margin: 4px 0 0;
            font-size: 0.78rem;
            opacity: 0.9;
            font-weight: 600;
            letter-spacing: 0.3px;
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
            min-width: 82px;
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
        .stat-badge.is-success { background: var(--success); }
        .stat-badge.is-warning { background: var(--warning); color: #000; }
        .stat-badge.is-info    { background: var(--info); }

        .btn-neo-close {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 800;
            font-size: 0.88rem;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-neo-close:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            background: #b02a37;
            color: #fff;
            text-decoration: none;
        }

        /* ============================================================ */
        /* CONTAINER                                                    */
        /* ============================================================ */
        .container-neo { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

        /* ============================================================ */
        /* ALERTAS                                                      */
        /* ============================================================ */
        .alert-neo {
            border: 3px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            border-radius: 14px;
            font-weight: 700;
            padding: 16px 22px;
            margin-bottom: 20px;
        }
        .alert-neo.alert-danger  { background: var(--danger);  color: #fff; }
        .alert-neo.alert-warning { background: var(--warning); color: #000; }
        .alert-neo.alert-info    { background: var(--info);    color: #fff; }

        /* ============================================================ */
        /* TOOLBAR                                                      */
        /* ============================================================ */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .toolbar-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1 1 320px; }
        .toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex-shrink: 0; }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
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
            width: 40px;
            height: 22px;
            background: #d1d5db;
            border-radius: 999px;
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .neo-switch .switch-track::after {
            content: "";
            position: absolute;
            width: 18px;
            height: 18px;
            background: #fff;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .neo-switch input:checked + .switch-track { background: var(--warning); }
        .neo-switch input:checked + .switch-track::after { transform: translateX(18px); }
        .neo-switch i { color: var(--warning); }

        .unsaved-hint {
            background: var(--warning);
            color: #000;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 800;
            font-size: 0.85rem;
            display: none;
            align-items: center;
            gap: 8px;
        }
        .unsaved-hint.visible { display: inline-flex; }

        /* ============================================================ */
        /* TABELA                                                       */
        /* ============================================================ */
        .table-wrapper {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
            padding: 6px;
            margin-bottom: 24px;
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
        table.dataTable.table-neo tbody tr.row-modified td { background: rgba(255,193,7,0.15); }
        table.dataTable.table-neo tbody tr.row-modified:hover td { background: rgba(255,193,7,0.22); }
        table.dataTable.table-neo tbody tr.row-zero td { box-shadow: inset 3px 0 0 0 var(--warning); }

        .cell-id { font-weight: 800; color: var(--text-color); opacity: 0.55; font-size: 0.8rem; }
        .cell-empresa {
            display: inline-block;
            background: var(--shadow-color);
            color: #fff;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.7rem;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .cell-nome { font-weight: 800; color: var(--text-color); }

        /* Input de comissão */
        .comissao-wrap {
            display: inline-flex;
            align-items: stretch;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            transition: all 0.15s;
            max-width: 160px;
        }
        .comissao-wrap:focus-within {
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }
        .comissao-wrap input {
            border: none;
            background: transparent;
            color: var(--text-color);
            font-family: inherit;
            font-weight: 800;
            font-size: 0.95rem;
            text-align: right;
            padding: 8px 4px 8px 12px;
            width: 80px;
            outline: none;
            -moz-appearance: textfield;
        }
        .comissao-wrap input::-webkit-outer-spin-button,
        .comissao-wrap input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .comissao-wrap .suffix {
            display: flex;
            align-items: center;
            padding: 0 12px 0 6px;
            font-weight: 800;
            font-size: 0.85rem;
            color: var(--primary);
            background: rgba(0,0,0,0.04);
            border-left: 1px solid rgba(0,0,0,0.08);
        }

        /* Botões de ação */
        .action-buttons { white-space: nowrap; }
        .btn-neo {
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 9px 16px;
            font-weight: 800;
            font-size: 0.78rem;
            transition: all 0.15s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .btn-neo-primary { background: var(--btn-bg); color: var(--btn-text); }
        .btn-neo:hover:not(:disabled) {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo:active:not(:disabled) { transform: translate(1px, 1px); box-shadow: 1px 1px 0 0 var(--shadow-color); }
        .btn-neo:disabled { opacity: 0.5; cursor: not-allowed; background: #9ca3af; color: #fff; }
        .btn-neo.saved { background: var(--success); color: #fff; opacity: 1; }

        /* ============================================================ */
        /* DATATABLES                                                   */
        /* ============================================================ */
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
            font-family: inherit;
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

        /* ============================================================ */
        /* RESPONSIVO                                                   */
        /* ============================================================ */
        @media (max-width: 992px) {
            .header-brand-text h1 { font-size: 1.15rem; }
            .header-neo .logo-img { height: 52px; max-width: 140px; }
        }
        @media (max-width: 768px) {
            .header-brand-text h1 { font-size: 1rem; }
            .header-brand-text p { font-size: 0.7rem; }
            .header-neo .logo-img { height: 44px; max-width: 110px; padding: 3px 6px; }
            .header-stats { width: 100%; }
            .stat-badge { flex: 1; min-width: 0; }
            .btn-neo-close { width: 100%; justify-content: center; }
            .container-neo { padding: 0 12px; }
            .search-box { max-width: 100%; flex-basis: 100%; }
            .toolbar-left { flex-basis: 100%; }
            .toolbar-right { width: 100%; }
            .toolbar-right > * { flex: 1; justify-content: center; }
            .btn-neo { padding: 8px 12px; font-size: 0.72rem; }
        }
    </style>
</head>
<body data-theme-primary="<?php echo htmlspecialchars($themeSafe['primary']); ?>">

<!-- ============================================================ -->
<!-- HEADER                                                       -->
<!-- ============================================================ -->
<header class="header-neo">
    <div class="header-inner">

        <div class="header-brand">
            <img src="img/logosunny.jpg" alt="Logotipo" class="logo-img">
            <div class="header-brand-text">
                <h1><i class="fas fa-percentage mr-2"></i> Editar Comissão de Venda</h1>
                <p>SunnyImob · Percentual por empreendimento</p>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-badge">
                <span class="num"><?php echo $total; ?></span>
                <span class="label">Total</span>
            </div>
            <div class="stat-badge is-success">
                <span class="num"><?php echo $comComissao; ?></span>
                <span class="label">Com comissão</span>
            </div>
            <div class="stat-badge is-warning">
                <span class="num"><?php echo $semComissao; ?></span>
                <span class="label">Sem comissão</span>
            </div>
            <div class="stat-badge is-info">
                <span class="num"><?php echo number_format($comissaoMedia, 1, ',', '.'); ?>%</span>
                <span class="label">Média</span>
            </div>
        </div>

        <a href="#" class="btn-neo-close" onclick="tentarFechar(event)">
            <i class="fas fa-times"></i> Fechar
        </a>

    </div>
</header>

<!-- ============================================================ -->
<!-- CONTEÚDO                                                     -->
<!-- ============================================================ -->
<div class="container-neo">

    <?php if ($error_message): ?>
        <div class="alert-neo alert-danger">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($empreendimentos) && !$error_message): ?>
        <div class="alert-neo alert-warning">
            <i class="fas fa-info-circle mr-2"></i>
            Nenhum empreendimento encontrado no banco de dados.
        </div>
    <?php endif; ?>

    <?php if (!empty($empreendimentos)): ?>

    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar por empresa ou empreendimento…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <label class="neo-switch" title="Mostra apenas empreendimentos sem comissão definida">
                <input type="checkbox" id="filterZero">
                <span class="switch-track"></span>
                <span><i class="fas fa-filter"></i> Só sem comissão</span>
            </label>
        </div>

        <div class="toolbar-right">
            <div class="unsaved-hint" id="unsavedHint">
                <i class="fas fa-exclamation-circle"></i>
                <span><span id="unsavedCount">0</span> alteração(ões) não salva(s)</span>
            </div>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="tabelaComissao" class="table table-neo" style="width:100%">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th style="width: 180px;">Empresa</th>
                    <th>Empreendimento</th>
                    <th style="width: 200px;">Comissão Venda (%)</th>
                    <th style="width: 160px;">Ação</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($empreendimentos as $e):
                $id        = $e['Empreend_ID'];
                $nome      = $e['NomeEmpreendimento'];
                $empresa   = $e['NomeEmpresa'];
                $comissao  = $e['ComissaoVenda'] ?? 0;
                $comissaoF = number_format((float)$comissao, 2, '.', '');
                $isZero    = ((float)$comissao) <= 0;
            ?>
                <tr id="linha_<?php echo htmlspecialchars($id); ?>"
                    data-id="<?php echo htmlspecialchars($id); ?>"
                    class="<?php echo $isZero ? 'row-zero' : ''; ?>">
                    <td><span class="cell-id">#<?php echo htmlspecialchars($id); ?></span></td>
                    <td><span class="cell-empresa"><?php echo htmlspecialchars(strtoupper($empresa)); ?></span></td>
                    <td><span class="cell-nome"><?php echo htmlspecialchars($nome); ?></span></td>
                    <td>
                        <div class="comissao-wrap">
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   max="100"
                                   id="comissao_<?php echo htmlspecialchars($id); ?>"
                                   data-id="<?php echo htmlspecialchars($id); ?>"
                                   data-original="<?php echo $comissaoF; ?>"
                                   value="<?php echo $comissaoF; ?>">
                            <span class="suffix">%</span>
                        </div>
                    </td>
                    <td class="action-buttons">
                        <button class="btn-neo btn-neo-primary btn-update"
                                data-id="<?php echo htmlspecialchars($id); ?>"
                                disabled
                                title="Salvar alteração">
                            <i class="fas fa-save"></i> Salvar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="alert-neo alert-info">
        <i class="fas fa-info-circle mr-2"></i>
        <strong>Dica:</strong> Altere o percentual e pressione <kbd>Enter</kbd> (ou clique em Salvar). <kbd>Esc</kbd> restaura o valor original.
    </div>

    <?php endif; ?>

</div>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<script>
(function () {
    'use strict';

    var THEME_PRIMARY = document.body.getAttribute('data-theme-primary') || '#800020';

    var Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: function (toast) {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    function beforeUnloadHandler(e) {
        if (document.querySelectorAll('tr.row-modified').length > 0) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    }

    $(document).ready(function () {

        // ============================================================
        // 1) DataTables
        // ============================================================
        var table = $('#tabelaComissao').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json" },
            "pageLength": 100,
            "lengthMenu": [[25, 50, 100, 200, -1], [25, 50, 100, 200, "Todos"]],
            "order": [[1, "asc"]],
            "columnDefs": [
                { "orderable": false, "targets": [3, 4] }
            ],
            "drawCallback": function () {
                $('.btn-update').each(function () {
                    var id = $(this).data('id');
                    var $inp = $('#comissao_' + id);
                    if ($inp.length) {
                        var orig  = parseFloat($inp.data('original')) || 0;
                        var atual = parseFloat($inp.val()) || 0;
                        $(this).prop('disabled', Math.abs(orig - atual) < 0.001);
                    }
                });
            }
        });

        // ============================================================
        // 2) Busca e filtro "só sem comissão"
        // ============================================================
        var $search      = $('#searchInput');
        var $clear       = $('#searchClear');
        var $filterZero  = $('#filterZero');
        var searchTimer;

        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'tabelaComissao') return true;
            if (!$filterZero.is(':checked')) return true;
            var tr = settings.aoData[dataIndex].nTr;
            return $(tr).hasClass('row-zero');
        });

        $search.on('input', function () {
            var v = $(this).val();
            $clear.toggleClass('visible', v.length > 0);
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.search(v).draw(); }, 150);
        });
        $search.on('keydown', function (e) { if (e.key === 'Escape') $(this).val('').trigger('input'); });
        $clear.on('click', function () { $search.val('').trigger('input').focus(); });
        $filterZero.on('change', function () { table.draw(); });

        // ============================================================
        // 3) Estado "modificado"
        // ============================================================
        function atualizarEstadoLinha(id) {
            var $inp = $('#comissao_' + id);
            var $row = $('#linha_' + id);
            var $btn = $row.find('.btn-update');

            var orig  = parseFloat($inp.data('original')) || 0;
            var atual = parseFloat($inp.val()) || 0;

            var mudou = Math.abs(orig - atual) >= 0.001;

            if (mudou) {
                $row.addClass('row-modified');
                $btn.prop('disabled', false);
            } else {
                $row.removeClass('row-modified');
                $btn.prop('disabled', true);
            }

            atualizarContadorNaoSalvos();
        }

        function atualizarContadorNaoSalvos() {
            var count = $('.row-modified').length;
            $('#unsavedCount').text(count);
            $('#unsavedHint').toggleClass('visible', count > 0);
        }

        $(document).on('input', '#tabelaComissao input[type=number]', function () {
            atualizarEstadoLinha($(this).data('id'));
        });

        $(document).on('keydown', '#tabelaComissao input[type=number]', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).blur();
                salvarLinha($(this).data('id'));
            } else if (e.key === 'Escape') {
                $(this).val($(this).data('original')).trigger('input');
            }
        });

        $(document).on('click', '.btn-update', function () {
            salvarLinha($(this).data('id'));
        });

        // ============================================================
        // 4) Salvar linha
        // ============================================================
        function salvarLinha(id) {
            var $inp = $('#comissao_' + id);
            var $row = $('#linha_' + id);
            var $btn = $row.find('.btn-update');

            var raw  = String($inp.val() || '').replace(',', '.').trim();
            var num  = parseFloat(raw);
            var orig = parseFloat($inp.data('original')) || 0;

            if (isNaN(num)) {
                Toast.fire({ icon: 'warning', title: 'Informe um número válido.' });
                $inp.focus();
                return;
            }
            if (num < 0 || num > 100) {
                Toast.fire({ icon: 'warning', title: 'O valor deve estar entre 0 e 100.' });
                $inp.focus();
                return;
            }
            if (Math.abs(num - orig) < 0.001) {
                Toast.fire({ icon: 'info', title: 'Nenhuma alteração para salvar.' });
                return;
            }

            var btnHtmlOriginal = $btn.html();
            $btn.prop('disabled', true).removeClass('saved')
                .html('<i class="fas fa-spinner fa-spin"></i> Salvando…');
            $inp.prop('disabled', true);

            $.ajax({
                url: "atualizar_comissao.php",
                type: "POST",
                data: { id: id, comissaoVenda: num },
                dataType: "text",
                headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" }
            }).done(function (raw) {
                var result = null;
                try { result = JSON.parse(raw); } catch (e) { /* null */ }

                if (result && (result.success === true || result.success === 'success' || result.success === "OK")) {
                    finalizarSucesso(id, num, result.message, btnHtmlOriginal);
                    return;
                }
                if (result && result.success === false) {
                    finalizarErro(result.message || 'Erro ao atualizar.');
                    return;
                }

                // Fallback legado: texto "success"
                if (String(raw).trim().toLowerCase() === 'success') {
                    finalizarSucesso(id, num, 'Comissão atualizada!', btnHtmlOriginal);
                    return;
                }

                // Diagnóstico
                var dica = '';
                var rawTrim = String(raw || '').trim();
                if (rawTrim.charAt(0) === '<') {
                    dica = 'Servidor retornou HTML. Provavelmente <code>atualizar_comissao.php</code> não existe, ou há output parasita.';
                } else if (rawTrim === '') {
                    dica = 'Servidor retornou vazio. Erro fatal silencioso.';
                } else {
                    dica = 'Resposta não é JSON válido.';
                }
                finalizarErroComDiagnostico(raw, dica);
            }).fail(function (xhr) {
                console.error('AJAX falhou:', xhr.status, xhr.responseText);
                finalizarErroComDiagnostico(xhr.responseText || '(HTTP ' + xhr.status + ')',
                    'Falha de rede ou HTTP ' + xhr.status + '.');
            });

            function finalizarSucesso(rowId, novoValor, msg, btnHtmlOriginal) {
                var formatado = Number(novoValor).toFixed(2);
                $inp.val(formatado).data('original', formatado);

                $row.removeClass('row-modified row-zero');
                if (novoValor <= 0) $row.addClass('row-zero');

                $btn.prop('disabled', true).addClass('saved')
                    .html('<i class="fas fa-check-circle"></i> Salvo');

                atualizarContadorNaoSalvos();
                Toast.fire({ icon: 'success', title: msg || 'Comissão atualizada!' });

                setTimeout(function () {
                    $btn.removeClass('saved').html(btnHtmlOriginal).prop('disabled', true);
                    $inp.prop('disabled', false);
                }, 1200);
            }

            function finalizarErro(msg) {
                $btn.html(btnHtmlOriginal).prop('disabled', false);
                $inp.prop('disabled', false);
                Toast.fire({ icon: 'error', title: msg });
            }

            function finalizarErroComDiagnostico(raw, dica) {
                $btn.html(btnHtmlOriginal).prop('disabled', false);
                $inp.prop('disabled', false);

                Swal.fire({
                    title: 'Resposta inesperada do servidor',
                    html:
                        '<p style="font-size:13px;text-align:left;margin-bottom:8px;">' + dica + '</p>' +
                        '<pre style="text-align:left;font-size:11px;max-height:280px;overflow:auto;background:#111;color:#0f0;padding:10px;border-radius:6px;white-space:pre-wrap;word-break:break-all;">' +
                        String(raw).replace(/[&<>]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c]; }) +
                        '</pre>',
                    icon: 'error',
                    confirmButtonColor: THEME_PRIMARY,
                    width: 720
                });
            }
        }

        // ============================================================
        // 5) Fechar com confirmação
        // ============================================================
        window.addEventListener('beforeunload', beforeUnloadHandler);

        window.tentarFechar = function (event) {
            if (event) event.preventDefault();
            var pendentes = $('.row-modified').length;

            if (pendentes > 0) {
                Swal.fire({
                    title: 'Sair sem salvar?',
                    text: 'Você tem ' + pendentes + ' alteração(ões) não salva(s).',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sair mesmo assim',
                    cancelButtonText: 'Ficar',
                    confirmButtonColor: THEME_PRIMARY,
                    cancelButtonColor: '#6b7280'
                }).then(function (r) {
                    if (r.isConfirmed) {
                        window.removeEventListener('beforeunload', beforeUnloadHandler);
                        window.close();
                    }
                });
            } else {
                window.removeEventListener('beforeunload', beforeUnloadHandler);
                window.close();
            }
        };
    });
})();
</script>
</body>
</html>