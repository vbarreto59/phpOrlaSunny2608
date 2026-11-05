<?php
// session_start() DEVE ser o primeiro — antes de qualquer include
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'theme_config.php';

// --- Conexão: aceita $conn, $pdo ou $db ---
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
elseif (isset($db) && $db instanceof PDO)      { /* ok */ }
else die("Erro: conexão com o banco não encontrada.");

// --- Tema seguro (defaults se faltar chave) ---
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

// --- Estatísticas do header ---
$total = 0; $comLoc = 0; $comMap = 0;
try {
    $total  = (int) $db->query("SELECT COUNT(*) FROM empreendimento")->fetchColumn();
    $comLoc = (int) $db->query("SELECT COUNT(*) FROM empreendimento WHERE Localizacao IS NOT NULL AND Localizacao <> ''")->fetchColumn();
    $comMap = (int) $db->query("SELECT COUNT(*) FROM empreendimento WHERE LinkMapa IS NOT NULL AND LinkMapa <> ''")->fetchColumn();
} catch (PDOException $e) { /* opcional */ }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Localização e Mapa</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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

        /* Switches customizados */
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

        /* Botão "Salvar tudo" */
        .btn-save-all {
            background: var(--success);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 9px 18px;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            display: none;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            transition: all 0.15s;
        }
        .btn-save-all.visible { display: inline-flex; }
        .btn-save-all:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: #218838;
        }

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
        table.dataTable.table-neo tbody tr.row-no-localizacao td { box-shadow: inset 3px 0 0 0 var(--warning); }
        table.dataTable.table-neo tbody tr.row-no-linkmapa td { box-shadow: inset 3px 0 0 0 var(--info); }
        table.dataTable.table-neo tbody tr.row-no-localizacao.row-no-linkmapa td { box-shadow: inset 3px 0 0 0 var(--danger); }

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
        .cell-nome { font-weight: 800; color: var(--text-color); display: block; line-height: 1.3; }
        .cell-localidade {
            display: inline-block;
            background: rgba(23,162,184,0.12);
            color: var(--info);
            padding: 2px 8px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.7rem;
            letter-spacing: 0.3px;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .input-edit {
            width: 100%;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 8px 12px;
            background: #fff;
            color: var(--text-color);
            font-family: inherit;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.15s;
            min-width: 180px;
        }
        .input-edit:focus {
            outline: none;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }
        .input-edit.input-localizacao { text-transform: uppercase; }
        .input-edit.input-linkmapa { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.82rem; }

        /* Ações */
        .action-buttons { white-space: nowrap; }
        .btn-neo {
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 9px 14px;
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
        .btn-neo-info    { background: var(--info); color: #fff; }
        .btn-neo:hover:not(:disabled) {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
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

        /* Botões de exportar */
        .dataTables_wrapper .dt-buttons { padding: 12px 8px; }
        .dataTables_wrapper .dt-buttons .dt-button {
            background: var(--card-bg) !important;
            color: var(--text-color) !important;
            border: 2px solid var(--shadow-color) !important;
            box-shadow: 2px 2px 0 0 var(--shadow-color) !important;
            border-radius: 8px !important;
            padding: 6px 14px !important;
            font-weight: 700 !important;
            font-size: 0.8rem !important;
            transition: all 0.15s !important;
            margin-right: 6px !important;
            font-family: inherit !important;
        }
        .dataTables_wrapper .dt-buttons .dt-button:hover {
            transform: translate(-1px, -1px) !important;
            box-shadow: 3px 3px 0 0 var(--shadow-color) !important;
            background: var(--primary) !important;
            color: #fff !important;
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
            .input-edit { min-width: 120px; }
            .btn-neo { padding: 8px 10px; font-size: 0.72rem; }
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
                <h1><i class="fas fa-map-marked-alt mr-2"></i> Editar Localização</h1>
                <p>SunnyImob · Localização e Link do Mapa</p>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-badge">
                <span class="num"><?php echo $total; ?></span>
                <span class="label">Total</span>
            </div>
            <div class="stat-badge is-success">
                <span class="num"><?php echo $comLoc; ?></span>
                <span class="label">Com localização</span>
            </div>
            <div class="stat-badge is-info">
                <span class="num"><?php echo $comMap; ?></span>
                <span class="label">Com link mapa</span>
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

    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar por empresa, empreendimento ou localidade…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <label class="neo-switch" title="Mostra apenas empreendimentos sem Localização">
                <input type="checkbox" id="filterNoLoc">
                <span class="switch-track"></span>
                <span><i class="fas fa-map-marker-slash"></i> Sem localização</span>
            </label>

            <label class="neo-switch" title="Mostra apenas empreendimentos sem Link do Mapa">
                <input type="checkbox" id="filterNoMap">
                <span class="switch-track"></span>
                <span><i class="fas fa-link-slash"></i> Sem link mapa</span>
            </label>
        </div>

        <div class="toolbar-right">
            <button type="button" class="btn-save-all" id="btnSaveAll">
                <i class="fas fa-save"></i> Salvar tudo (<span id="saveAllCount">0</span>)
            </button>

            <div class="unsaved-hint" id="unsavedHint">
                <i class="fas fa-exclamation-circle"></i>
                <span><span id="unsavedCount">0</span> alteração(ões) não salva(s)</span>
            </div>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="tabelaLocalizacao" class="table table-neo" style="width:100%">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th style="width: 140px;">Empresa</th>
                    <th style="width: 22%;">Empreendimento</th>
                    <th>Localização</th>
                    <th>Link do Mapa</th>
                    <th style="width: 210px;">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php
            try {
                // ✅ CORREÇÃO: JOIN com empresa
                $sql = "SELECT e.Empreend_ID, e.NomeEmpreendimento, e.Empresa_Id,
                               e.Localidade, e.Localizacao, e.LinkMapa,
                               COALESCE(emp.NomeEmpresa, '—') AS NomeEmpresa
                        FROM empreendimento e
                        LEFT JOIN empresa emp ON e.Empresa_Id = emp.Empresa_Id
                        ORDER BY e.NomeEmpreendimento";

                $stmt = $db->prepare($sql);
                $stmt->execute();
                $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($empreendimentos as $emp):
                    $id          = $emp['Empreend_ID'];
                    $nome        = $emp['NomeEmpreendimento'];
                    $empresa     = $emp['NomeEmpresa'];
                    $localidade  = $emp['Localidade']   ?? '';
                    $localizacao = $emp['Localizacao']  ?? '';
                    $linkmapa    = $emp['LinkMapa']     ?? '';

                    $noLoc = ($localizacao === '');
                    $noMap = ($linkmapa === '');
                    $cls = '';
                    if ($noLoc) $cls .= ' row-no-localizacao';
                    if ($noMap) $cls .= ' row-no-linkmapa';
            ?>
                <tr id="linha_<?php echo htmlspecialchars($id); ?>"
                    data-id="<?php echo htmlspecialchars($id); ?>"
                    class="<?php echo trim($cls); ?>">
                    <td><span class="cell-id">#<?php echo htmlspecialchars($id); ?></span></td>
                    <td><span class="cell-empresa"><?php echo htmlspecialchars(strtoupper($empresa)); ?></span></td>
                    <td>
                        <span class="cell-nome"><?php echo htmlspecialchars($nome); ?></span>
                        <?php if ($localidade !== ''): ?>
                            <span class="cell-localidade">📍 <?php echo htmlspecialchars($localidade); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="text"
                               class="input-edit input-localizacao"
                               id="localizacao_<?php echo htmlspecialchars($id); ?>"
                               data-id="<?php echo htmlspecialchars($id); ?>"
                               data-original="<?php echo htmlspecialchars($localizacao); ?>"
                               value="<?php echo htmlspecialchars($localizacao); ?>"
                               placeholder="Ex.: RUA DAS FLORES, 123 - CENTRO">
                    </td>
                    <td>
                        <input type="text"
                               class="input-edit input-linkmapa"
                               id="linkmapa_<?php echo htmlspecialchars($id); ?>"
                               data-id="<?php echo htmlspecialchars($id); ?>"
                               data-original="<?php echo htmlspecialchars($linkmapa); ?>"
                               value="<?php echo htmlspecialchars($linkmapa); ?>"
                               placeholder="https://maps.google.com/…">
                    </td>
                    <td class="action-buttons">
                        <button class="btn-neo btn-neo-primary btn-update"
                                data-id="<?php echo htmlspecialchars($id); ?>"
                                disabled
                                title="Salvar alteração">
                            <i class="fas fa-check"></i> Salvar
                        </button>
                        <button class="btn-neo btn-neo-info btn-map"
                                data-id="<?php echo htmlspecialchars($id); ?>"
                                title="Abrir no Google Maps ou no link salvo">
                            <i class="fas fa-map-marked-alt"></i> Mapa
                        </button>
                    </td>
                </tr>
            <?php
                endforeach;

                if (empty($empreendimentos)) {
                    echo "<tr><td colspan='6' style='text-align:center; font-weight:700; opacity:0.6;'>Nenhum empreendimento encontrado</td></tr>";
                }
            } catch (PDOException $e) {
                echo "<tr><td colspan='6' style='color: var(--danger); font-weight: 700;'>";
                echo "Erro ao carregar dados: " . htmlspecialchars($e->getMessage());
                echo "</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>

</div>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap4.min.js"></script>

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
        var table = $('#tabelaLocalizacao').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json" },
            "pageLength": 50,
            "lengthMenu": [[25, 50, 100, 200, -1], [25, 50, 100, 200, "Todos"]],
            "order": [],
            "columnDefs": [
                { "orderable": false, "targets": [3, 4, 5] }
            ],
            "dom": 'Blrtip',
            "buttons": [
               
            ],
            "drawCallback": function () {
                $('.btn-update').each(function () {
                    var id = $(this).data('id');
                    var $loc = $('#localizacao_' + id);
                    var $map = $('#linkmapa_' + id);
                    if ($loc.length && $map.length) {
                        var origLoc = String($loc.data('original') || '');
                        var origMap = String($map.data('original') || '');
                        var atualLoc = String($loc.val() || '').trim().toUpperCase();
                        var atualMap = String($map.val() || '').trim();
                        $(this).prop('disabled', (origLoc.toUpperCase() === atualLoc) && (origMap === atualMap));
                    }
                });
            }
        });

        // ============================================================
        // 2) Filtros customizados
        // ============================================================
        var $search     = $('#searchInput');
        var $clear      = $('#searchClear');
        var $filterLoc  = $('#filterNoLoc');
        var $filterMap  = $('#filterNoMap');
        var searchTimer;

        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'tabelaLocalizacao') return true;
            var tr = settings.aoData[dataIndex].nTr;
            if ($filterLoc.is(':checked') && !$(tr).hasClass('row-no-localizacao')) return false;
            if ($filterMap.is(':checked') && !$(tr).hasClass('row-no-linkmapa'))   return false;
            return true;
        });

        $search.on('input', function () {
            var v = $(this).val();
            $clear.toggleClass('visible', v.length > 0);
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.search(v).draw(); }, 150);
        });
        $search.on('keydown', function (e) { if (e.key === 'Escape') $(this).val('').trigger('input'); });
        $clear.on('click', function () { $search.val('').trigger('input').focus(); });

        $filterLoc.on('change', function () { table.draw(); });
        $filterMap.on('change', function () { table.draw(); });

        // ============================================================
        // 3) Estado "modificado"
        // ============================================================
        function atualizarEstadoLinha(id) {
            var $loc  = $('#localizacao_' + id);
            var $map  = $('#linkmapa_' + id);
            var $row  = $('#linha_' + id);
            var $btn  = $row.find('.btn-update');

            var origLoc = String($loc.data('original') || '');
            var origMap = String($map.data('original') || '');
            var atualLoc = String($loc.val() || '').trim().toUpperCase();
            var atualMap = String($map.val() || '').trim();

            var mudou = (origLoc.toUpperCase() !== atualLoc) || (origMap !== atualMap);

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
            $('#saveAllCount').text(count);
            $('#btnSaveAll').toggleClass('visible', count > 0);
        }

        $(document).on('input', '.input-edit', function () {
            atualizarEstadoLinha($(this).data('id'));
        });

        $(document).on('keydown', '.input-edit', function (e) {
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

        $(document).on('click', '.btn-map', function () {
            abrirMapa($(this).data('id'));
        });

        // ============================================================
        // 4) Salvar linha
        // ============================================================
        function salvarLinha(id, callbackFinal) {
            var $loc = $('#localizacao_' + id);
            var $map = $('#linkmapa_' + id);
            var $row = $('#linha_' + id);
            var $btn = $row.find('.btn-update');

            var locVal = String($loc.val() || '').trim().toUpperCase();
            var mapVal = String($map.val() || '').trim();
            var origLoc = String($loc.data('original') || '');
            var origMap = String($map.data('original') || '');

            if (locVal === origLoc.toUpperCase() && mapVal === origMap) {
                Toast.fire({ icon: 'info', title: 'Nenhuma alteração para salvar.' });
                if (typeof callbackFinal === 'function') callbackFinal();
                return;
            }

            var btnHtmlOriginal = $btn.html();
            $btn.prop('disabled', true).removeClass('saved')
                .html('<i class="fas fa-spinner fa-spin"></i> Salvando…');
            $loc.prop('disabled', true);
            $map.prop('disabled', true);

            $.ajax({
                url: "atualizar_localizacao.php",
                type: "POST",
                data: { id: id, localizacao: locVal, linkmapa: mapVal },
                dataType: "text",
                headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" }
            }).done(function (raw) {
                var result = null;
                try { result = JSON.parse(raw); } catch (e) { /* null */ }

                if (result && (result.success === true || result.success === "OK" || result.status === "OK")) {
                    // Sucesso
                    $loc.val(result.localizacao_nova || locVal).data('original', result.localizacao_nova || locVal);
                    $map.val(result.linkmapa_novo    || mapVal).data('original', result.linkmapa_novo    || mapVal);

                    $row.removeClass('row-modified row-no-localizacao row-no-linkmapa');
                    if (!locVal) $row.addClass('row-no-localizacao');
                    if (!mapVal) $row.addClass('row-no-linkmapa');

                    $btn.prop('disabled', true).addClass('saved')
                        .html('<i class="fas fa-check-circle"></i> Salvo');

                    atualizarContadorNaoSalvos();
                    Toast.fire({ icon: 'success', title: result.message || 'Atualizado com sucesso!' });

                    setTimeout(function () {
                        $btn.removeClass('saved').html(btnHtmlOriginal).prop('disabled', true);
                        $loc.prop('disabled', false);
                        $map.prop('disabled', false);
                        if (typeof callbackFinal === 'function') callbackFinal(true);
                    }, 1200);
                    return;
                }

                if (result && result.success === false) {
                    finalizarErro(result.message || 'Erro ao atualizar.');
                    return;
                }

                // Resposta não-JSON — mostra diagnóstico
                finalizarErroComDiagnostico(raw);
            }).fail(function (xhr) {
                console.error('AJAX falhou:', xhr.status, xhr.responseText);
                finalizarErroComDiagnostico(xhr.responseText || '(HTTP ' + xhr.status + ')');
            });

            function finalizarErro(msg) {
                $btn.html(btnHtmlOriginal).prop('disabled', false);
                $loc.prop('disabled', false);
                $map.prop('disabled', false);
                Toast.fire({ icon: 'error', title: msg });
                if (typeof callbackFinal === 'function') callbackFinal(false);
            }

            function finalizarErroComDiagnostico(raw) {
                $btn.html(btnHtmlOriginal).prop('disabled', false);
                $loc.prop('disabled', false);
                $map.prop('disabled', false);

                var dica = '';
                var rawTrim = String(raw || '').trim();
                if (rawTrim.charAt(0) === '<') {
                    dica = 'Servidor retornou HTML. Provavelmente <code>atualizar_localizacao.php</code> não existe, ou há output parasita em <code>conn.php</code>.';
                } else if (rawTrim === '') {
                    dica = 'Servidor retornou vazio. Erro fatal silencioso — verifique <code>erros_localizacao.log</code> ou o error_log do Apache.';
                } else {
                    dica = 'Resposta não é JSON válido.';
                }

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
                if (typeof callbackFinal === 'function') callbackFinal(false);
            }
        }

        // ============================================================
        // 5) Salvar tudo (sequencial)
        // ============================================================
        $('#btnSaveAll').on('click', function () {
            var $mod = $('.row-modified');
            if ($mod.length === 0) {
                Toast.fire({ icon: 'info', title: 'Nenhuma alteração pendente.' });
                return;
            }

            Swal.fire({
                title: 'Salvar ' + $mod.length + ' linha(s)?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Salvar tudo',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: THEME_PRIMARY,
                cancelButtonColor: '#6b7280'
            }).then(function (r) {
                if (!r.isConfirmed) return;

                var ids = [];
                $mod.each(function () { ids.push($(this).data('id')); });

                var sucesso = 0, erro = 0;

                (function proximo(i) {
                    if (i >= ids.length) {
                        if (erro === 0) {
                            Toast.fire({ icon: 'success', title: sucesso + ' linha(s) salva(s)!' });
                        } else {
                            Swal.fire({
                                title: 'Concluído com erros',
                                html: '<b>' + sucesso + '</b> salva(s), <b>' + erro + '</b> com erro.',
                                icon: erro > 0 ? 'warning' : 'success',
                                confirmButtonColor: THEME_PRIMARY
                            });
                        }
                        return;
                    }
                    salvarLinha(ids[i], function (ok) {
                        if (ok === true) sucesso++;
                        else if (ok === false) erro++;
                        proximo(i + 1);
                    });
                })(0);
            });
        });

        // ============================================================
        // 6) Abrir mapa
        // ============================================================
        function abrirMapa(id) {
            var link = String($('#linkmapa_' + id).val() || '').trim();
            var loc  = String($('#localizacao_' + id).val() || '').trim();
            var nome = $('#linha_' + id).find('.cell-nome').text().trim();

            if (link) {
                window.open(link, '_blank');
                return;
            }
            if (loc) {
                var url = "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(loc);
                window.open(url, '_blank');
                return;
            }
            Toast.fire({
                icon: 'warning',
                title: 'Sem localização nem link de mapa para "' + (nome || 'este empreendimento') + '".'
            });
        }

        // ============================================================
        // 7) Fechar com confirmação
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