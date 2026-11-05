<?php
// ============================================================
// session_start() PRIMEIRO — antes de qualquer output
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'theme_config.php';

// --- Conexão: aceita $conn, $pdo ou $db ---
if (isset($conn) && $conn instanceof PDO) {
    $db = $conn;
} elseif (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
} elseif (isset($db) && $db instanceof PDO) {
    // já ok
} else {
    die("Erro: conexão com o banco não encontrada (verifique conn.php).");
}

// --- Tema seguro (defaults se faltar alguma chave) ---
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
$total          = 0;
$comTipologia   = 0;
$semTipologia   = 0;
try {
    $total        = (int) $db->query("SELECT COUNT(*) FROM empreendimento")->fetchColumn();
    $comTipologia = (int) $db->query("SELECT COUNT(*) FROM empreendimento WHERE Tipologia IS NOT NULL AND Tipologia <> ''")->fetchColumn();
    $semTipologia = $total - $comTipologia;
} catch (PDOException $e) {
    // stats opcionais
}

// Sugestões de tipologia (edite conforme o negócio)
$sugestoesTipologia = [
    'BANG', 'STUDIO', 'CASA',
    '1Q', '2Q', '3Q', '4Q', '5Q+',
    '1 QUARTO', '2 QUARTOS', '3 QUARTOS', '4 QUARTOS', '5 QUARTOS',
    'COBERTURA', 'GARDEN', 'LOJA', 'SALA COMERCIAL',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Tipologia</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
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
            max-width: 1300px;
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
        .container-neo { max-width: 1300px; margin: 0 auto; padding: 0 20px; }

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
        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            flex: 1 1 320px;
        }
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

        /* Switch "só sem tipologia" */
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
            font-size: 0.85rem;
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
        table.dataTable.table-neo tbody tr.row-empty td { background: rgba(23,162,184,0.06); }

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

        /* Chip de tipologia atual (quando tem valor) */
        .tipologia-chip {
            display: inline-block;
            background: var(--info);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.72rem;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            border: 2px solid var(--shadow-color);
        }
        .tipologia-chip.empty {
            background: #e5e7eb;
            color: #6b7280;
            font-style: italic;
            text-transform: none;
            box-shadow: none;
            border-color: #d1d5db;
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
            text-transform: uppercase;
            transition: all 0.15s;
        }
        .input-edit:focus {
            outline: none;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }

        .btn-neo {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 9px 18px;
            font-weight: 800;
            font-size: 0.82rem;
            transition: all 0.15s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
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
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
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
            .btn-neo { padding: 8px 14px; font-size: 0.78rem; }
            .toolbar-left { flex-basis: 100%; }
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
                <h1><i class="fas fa-layer-group mr-2"></i> Atualizar Tipologia</h1>
                <p>SunnyImob · Edição rápida</p>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-badge">
                <span class="num"><?php echo $total; ?></span>
                <span class="label">Total</span>
            </div>
            <div class="stat-badge is-success">
                <span class="num"><?php echo $comTipologia; ?></span>
                <span class="label">Com tipologia</span>
            </div>
            <div class="stat-badge is-warning">
                <span class="num"><?php echo $semTipologia; ?></span>
                <span class="label">Sem tipologia</span>
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
                <input type="text" id="searchInput" placeholder="Buscar por empresa ou empreendimento…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <label class="neo-switch" title="Mostra apenas empreendimentos sem tipologia">
                <input type="checkbox" id="filterEmpty">
                <span class="switch-track"></span>
                <span><i class="fas fa-filter"></i> Só sem tipologia</span>
            </label>
        </div>

        <div class="unsaved-hint" id="unsavedHint">
            <i class="fas fa-exclamation-circle"></i>
            <span><span id="unsavedCount">0</span> alteração(ões) não salva(s)</span>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="tabelaTipologia" class="table table-neo">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 180px;">Empresa</th>
                    <th>Empreendimento</th>
                    <th style="width: 160px;">Atual</th>
                    <th style="width: 34%;">Nova Tipologia</th>
                    <th style="width: 140px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    // ✅ CORREÇÃO: JOIN com empresa para trazer NomeEmpresa
                    $sql = "SELECT e.Empreend_ID, e.NomeEmpreendimento, e.Empresa_Id, e.Tipologia,
                                   COALESCE(emp.NomeEmpresa, '—') AS NomeEmpresa
                            FROM empreendimento e
                            LEFT JOIN empresa emp ON e.Empresa_Id = emp.Empresa_Id
                            ORDER BY e.NomeEmpreendimento";

                    $stmt = $db->prepare($sql);
                    $stmt->execute();
                    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($empreendimentos as $emp):
                        $id        = $emp['Empreend_ID'];
                        $nome      = $emp['NomeEmpreendimento'];
                        $empresa   = $emp['NomeEmpresa'];
                        $tipologia = $emp['Tipologia'] ?? '';
                        $temTip    = ($tipologia !== '' && $tipologia !== null);
                ?>
                <tr id="linha_<?php echo htmlspecialchars($id); ?>"
                    data-id="<?php echo htmlspecialchars($id); ?>"
                    class="<?php echo $temTip ? '' : 'row-empty'; ?>">
                    <td><span class="cell-id">#<?php echo htmlspecialchars($id); ?></span></td>
                    <td><span class="cell-empresa"><?php echo htmlspecialchars(strtoupper($empresa)); ?></span></td>
                    <td><span class="cell-nome"><?php echo htmlspecialchars($nome); ?></span></td>
                    <td>
                        <span class="tipologia-chip <?php echo $temTip ? '' : 'empty'; ?>">
                            <?php echo $temTip ? htmlspecialchars($tipologia) : 'sem tipologia'; ?>
                        </span>
                    </td>
                    <td>
                        <input type="text"
                               class="input-edit"
                               id="tipologia_<?php echo htmlspecialchars($id); ?>"
                               data-id="<?php echo htmlspecialchars($id); ?>"
                               data-original="<?php echo htmlspecialchars($tipologia); ?>"
                               value="<?php echo htmlspecialchars($tipologia); ?>"
                               list="sugestoesTipologia"
                               placeholder="ex.: 2Q, STUDIO, COBERTURA…"
                               autocomplete="off">
                    </td>
                    <td>
                        <button class="btn-neo btn-update"
                                data-id="<?php echo htmlspecialchars($id); ?>"
                                disabled
                                title="Salvar alteração">
                            <i class="fas fa-check"></i> Salvar
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

<!-- Datalist global de sugestões -->
<datalist id="sugestoesTipologia">
    <?php foreach ($sugestoesTipologia as $s): ?>
        <option value="<?php echo htmlspecialchars($s); ?>"></option>
    <?php endforeach; ?>
</datalist>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

<script>
(function () {
    'use strict';

    var THEME_PRIMARY = document.body.getAttribute('data-theme-primary') || '#800020';

    // ============================================================
    // Toast
    // ============================================================
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

    // ============================================================
    // Handler de beforeunload nomeado
    // ============================================================
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
        var table = $('#tabelaTipologia').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json" },
            "pageLength": 100,
            "lengthMenu": [[50, 100, 200, -1], [50, 100, 200, "Todos"]],
            "order": [],
            "columnDefs": [
                { "orderable": false, "targets": [4, 5] }
            ],
            "drawCallback": function () {
                // Reaplica estado dos botões após redraw
                $('.btn-update').each(function () {
                    var id = $(this).data('id');
                    var $input = $('#tipologia_' + id);
                    if ($input.length) {
                        var original = String($input.data('original') || '');
                        var atual = String($input.val() || '');
                        $(this).prop('disabled', original === atual);
                    }
                });
            }
        });

        // ============================================================
        // 2) Filtro customizado: busca + "só sem tipologia"
        // ============================================================
        var $search      = $('#searchInput');
        var $clear       = $('#searchClear');
        var $filterEmpty = $('#filterEmpty');
        var searchTimer;

        // Registra filtro customizado no DataTables
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'tabelaTipologia') return true;
            if (!$filterEmpty.is(':checked')) return true;

            // Se "só sem tipologia" está ligado, só mostra linhas com a 4ª coluna vazia/"sem tipologia"
            var chipText = (data[3] || '').toLowerCase().trim();
            return chipText === 'sem tipologia' || chipText === '';
        });

        function redesenhar() {
            table.draw();
            atualizarContadorNaoSalvos();
        }

        $search.on('input', function () {
            var v = $(this).val();
            $clear.toggleClass('visible', v.length > 0);
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                table.search(v).draw();
            }, 150);
        });

        $search.on('keydown', function (e) {
            if (e.key === 'Escape') $(this).val('').trigger('input');
        });

        $clear.on('click', function () {
            $search.val('').trigger('input').focus();
        });

        $filterEmpty.on('change', redesenhar);

        // ============================================================
        // 3) Estado "modificado"
        // ============================================================
        function normalizarTipologia(valor) {
            return String(valor || '').trim().toUpperCase();
        }

        function atualizarEstadoLinha($input) {
            var id        = $input.data('id');
            var original  = normalizarTipologia($input.data('original'));
            var atual     = normalizarTipologia($input.val());
            var $row      = $('#linha_' + id);
            var $btn      = $row.find('.btn-update');

            if (atual !== original) {
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

        // Delegação
        $(document).on('input', '.input-edit', function () {
            atualizarEstadoLinha($(this));
        });

        // Enter salva / Esc cancela
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

        // ============================================================
        // 4) Salvamento via AJAX
        // ============================================================
        function salvarLinha(id) {
            var $input   = $('#tipologia_' + id);
            var $row     = $('#linha_' + id);
            var $btn     = $row.find('.btn-update');
            var novaTip  = normalizarTipologia($input.val());
            var original = normalizarTipologia($input.data('original'));

            if (!novaTip) {
                Toast.fire({ icon: 'warning', title: 'A tipologia não pode estar vazia.' });
                $input.focus();
                return;
            }
            if (novaTip === original) {
                Toast.fire({ icon: 'info', title: 'Nenhuma alteração para salvar.' });
                return;
            }

            var btnHtmlOriginal = $btn.html();
            $btn.prop('disabled', true)
                .removeClass('saved')
                .html('<i class="fas fa-spinner fa-spin"></i> Salvando…');
            $input.prop('disabled', true);

            $.ajax({
                url: "atualizar_tipologia.php",
                type: "POST",
                data: { id: id, tipologia: novaTip },
                dataType: "text"
            }).done(function (raw) {
                console.log("[DEBUG] Resposta bruta:", raw);

                var result = null;
                try { result = JSON.parse(raw); } catch (e) { /* mantém null */ }

                if (result && result.success) {
                    finalizarSucesso(id, novaTip, result.message, btnHtmlOriginal);
                    return;
                }
                if (result && result.success === false) {
                    $btn.html(btnHtmlOriginal);
                    $input.prop('disabled', false);
                    Toast.fire({ icon: 'error', title: result.message || 'Erro ao atualizar.' });
                    return;
                }

                // Resposta não-JSON
                $btn.html(btnHtmlOriginal);
                $input.prop('disabled', false);

                var dica = '';
                var rawTrim = String(raw || '').trim();
                if (rawTrim.charAt(0) === '<') {
                    dica = 'O servidor retornou <b>HTML</b>. Provavelmente o arquivo <code>atualizar_tipologia.php</code> não existe, tem <code>include</code> imprimindo algo, ou redireciona para login.';
                } else if (rawTrim === '') {
                    dica = 'O servidor retornou <b>vazio</b>. Erro fatal silencioso — verifique <code>erros_tipologia.log</code>.';
                } else {
                    dica = 'Resposta não é JSON válido.';
                }

                Swal.fire({
                    title: 'Resposta inesperada do servidor',
                    html:
                        '<p style="font-size:13px;text-align:left;margin-bottom:8px;">' + dica + '</p>' +
                        '<pre style="text-align:left;font-size:11px;max-height:280px;overflow:auto;background:#111;color:#0f0;padding:10px;border-radius:6px;white-space:pre-wrap;word-break:break-all;">' +
                        String(raw).replace(/[&<>]/g, function (c) {
                            return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c];
                        }) +
                        '</pre>',
                    icon: 'error',
                    confirmButtonColor: THEME_PRIMARY,
                    width: 720
                });
            }).fail(function (xhr) {
                $btn.html(btnHtmlOriginal);
                $input.prop('disabled', false);
                console.error("[DEBUG] AJAX falhou:", xhr.status, xhr.responseText);

                Swal.fire({
                    title: 'Falha na requisição',
                    html:
                        '<p style="text-align:left;"><b>HTTP:</b> ' + xhr.status + '</p>' +
                        '<pre style="text-align:left;font-size:11px;max-height:240px;overflow:auto;background:#111;color:#0f0;padding:10px;border-radius:6px;white-space:pre-wrap;word-break:break-all;">' +
                        String(xhr.responseText || '(vazio)').replace(/[&<>]/g, function (c) {
                            return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c];
                        }) +
                        '</pre>',
                    icon: 'error',
                    confirmButtonColor: THEME_PRIMARY,
                    width: 720
                });
            });

            function finalizarSucesso(rowId, novaTipologia, message, btnHtmlOriginal) {
                // Atualiza o input e o "data-original"
                $input.val(novaTipologia).data('original', novaTipologia);

                // Atualiza o chip "Atual"
                var $chip = $row.find('.tipologia-chip');
                if (novaTipologia) {
                    $chip.removeClass('empty').text(novaTipologia);
                    $row.removeClass('row-empty');
                } else {
                    $chip.addClass('empty').text('sem tipologia');
                    $row.addClass('row-empty');
                }

                $row.removeClass('row-modified');
                $btn.prop('disabled', true)
                    .addClass('saved')
                    .html('<i class="fas fa-check-circle"></i> Salvo');

                atualizarContadorNaoSalvos();
                Toast.fire({ icon: 'success', title: message || 'Tipologia atualizada com sucesso.' });

                setTimeout(function () {
                    $btn.removeClass('saved').html(btnHtmlOriginal);
                    $input.prop('disabled', false);
                }, 1500);
            }
        }

        // ============================================================
        // 5) beforeunload
        // ============================================================
        window.addEventListener('beforeunload', beforeUnloadHandler);

        // ============================================================
        // 6) Fechar com confirmação
        // ============================================================
        window.tentarFechar = function (event) {
            if (event) event.preventDefault();

            var pendentes = $('.row-modified').length;

            if (pendentes > 0) {
                Swal.fire({
                    title: 'Sair sem salvar?',
                    text: 'Você tem ' + pendentes + ' alteração(ões) não salva(s). Elas serão perdidas.',
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