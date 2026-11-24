<?php
// ============================================================
// session_start() PRIMEIRO
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

// --- Tema seguro ---
$themeDefaults = [
    'primary' => '#800020', 'primary_dark' => '#4B0C1D',
    'secondary' => '#6c757d', 'accent' => '#ffc107',
    'bg_body' => '#f0f2f5', 'text_color' => '#1a1a2e',
    'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e',
    'btn_bg' => '#800020', 'btn_hover' => '#4B0C1D', 'btn_text' => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// ============================================================
// REGRA DE NEGÓCIO
// ============================================================
const LIMITE_BEIRA_MAR_METROS = 10.0;

// --- Estatísticas do header ---
$total = 0; $beiraMar = 0; $semDistancia = 0;
try {
    $total        = (int) $db->query("SELECT COUNT(*) FROM empreendimento")->fetchColumn();
    $beiraMar     = (int) $db->query("SELECT COUNT(*) FROM empreendimento WHERE BeiraMar = 1")->fetchColumn();
    $semDistancia = (int) $db->query("SELECT COUNT(*) FROM empreendimento WHERE DistanciaMar IS NULL OR DistanciaMar = ''")->fetchColumn();
} catch (PDOException $e) { /* stats opcionais */ }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Distância do Mar</title>

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
            --sea: #0d6efd;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            padding: 0; min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* HEADER */
        .header-neo {
            background: var(--primary); color: #fff;
            padding: 14px 0;
            border-bottom: 5px solid var(--shadow-color);
            box-shadow: 0 6px 0 0 var(--shadow-color);
            margin-bottom: 26px; position: relative;
        }
        .header-neo::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px; pointer-events: none;
        }
        .header-inner {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; flex-wrap: wrap; position: relative; z-index: 1;
            max-width: 1400px; margin: 0 auto; padding: 0 20px;
        }
        .header-brand { display: flex; align-items: center; gap: 16px; min-width: 0; }
        .header-neo .logo-img {
            height: 62px; width: auto; max-width: 180px; min-width: 62px;
            object-fit: contain; border: 3px solid var(--shadow-color);
            border-radius: 12px; box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #fff; padding: 5px 8px; flex-shrink: 0;
        }
        .header-brand-text h1 {
            font-weight: 900; font-size: 1.35rem; margin: 0;
            line-height: 1.15; letter-spacing: -0.4px;
        }
        .header-brand-text p {
            margin: 4px 0 0; font-size: 0.78rem; opacity: 0.9;
            font-weight: 600; letter-spacing: 0.3px;
        }
        .header-stats { display: flex; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }
        .stat-badge {
            background: rgba(0,0,0,0.18);
            border: 2px solid var(--shadow-color);
            border-radius: 12px; padding: 8px 16px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            display: flex; flex-direction: column; align-items: center;
            min-width: 92px;
        }
        .stat-badge .num { font-size: 1.3rem; font-weight: 900; line-height: 1; letter-spacing: -0.5px; }
        .stat-badge .label {
            font-size: 0.6rem; text-transform: uppercase; letter-spacing: 1px;
            margin-top: 4px; font-weight: 700; opacity: 0.9;
        }
        .stat-badge.is-success { background: var(--success); }
        .stat-badge.is-warning { background: var(--warning); color: #000; }

        .btn-neo-close {
            background: var(--danger); color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            border-radius: 12px; padding: 10px 20px;
            font-weight: 800; font-size: 0.88rem;
            transition: all 0.15s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            white-space: nowrap; flex-shrink: 0;
        }
        .btn-neo-close:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            background: #b02a37; color: #fff; text-decoration: none;
        }

        /* CONTAINER */
        .container-neo { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

        /* TOOLBAR */
        .toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap; margin-bottom: 16px;
        }
        .toolbar-left {
            display: flex; align-items: center; gap: 12px;
            flex-wrap: wrap; flex: 1 1 320px;
        }
        .search-box {
            position: relative; display: flex; align-items: center;
            flex: 1 1 320px; max-width: 480px;
        }
        .search-box input {
            width: 100%; background: var(--card-bg); color: var(--text-color);
            border: 3px solid var(--shadow-color); border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 11px 46px 11px 46px;
            font-family: inherit; font-size: 14px; font-weight: 600;
            transition: all 0.15s;
        }
        .search-box input:focus {
            outline: none; transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }
        .search-box input::placeholder { color: var(--text-color); opacity: 0.5; font-weight: 500; }
        .search-box .icon {
            position: absolute; left: 16px; color: var(--text-color);
            opacity: 0.6; pointer-events: none; font-size: 14px;
        }
        .search-box:focus-within .icon { color: var(--primary); opacity: 1; }
        .search-box .clear {
            position: absolute; right: 10px;
            background: var(--danger); color: #fff;
            border: 2px solid var(--shadow-color); border-radius: 50%;
            width: 26px; height: 26px; cursor: pointer;
            display: none; align-items: center; justify-content: center;
            font-size: 11px; padding: 0;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            transition: all 0.12s;
        }
        .search-box .clear.visible { display: flex; }
        .search-box .clear:hover { transform: scale(1.12); background: #b02a37; }

        .neo-switch {
            display: inline-flex; align-items: center; gap: 10px;
            background: #fff; border: 2px solid var(--shadow-color);
            border-radius: 10px; box-shadow: 3px 3px 0 0 var(--shadow-color);
            padding: 9px 14px; cursor: pointer; user-select: none;
            transition: all 0.15s; font-weight: 700; font-size: 0.85rem;
            color: var(--text-color); white-space: nowrap;
        }
        .neo-switch:hover { transform: translate(-1px, -1px); box-shadow: 4px 4px 0 0 var(--shadow-color); }
        .neo-switch input { display: none; }
        .neo-switch .switch-track {
            width: 40px; height: 22px; background: #d1d5db;
            border-radius: 999px; position: relative; transition: background 0.2s;
            flex-shrink: 0;
        }
        .neo-switch .switch-track::after {
            content: ""; position: absolute; width: 18px; height: 18px;
            background: #fff; border-radius: 50%; top: 2px; left: 2px;
            transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .neo-switch input:checked + .switch-track { background: var(--warning); }
        .neo-switch input:checked + .switch-track::after { transform: translateX(18px); }
        .neo-switch i { color: var(--warning); }

        .unsaved-hint {
            background: var(--warning); color: #000;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px; padding: 8px 16px;
            font-weight: 800; font-size: 0.85rem;
            display: none; align-items: center; gap: 8px;
        }
        .unsaved-hint.visible { display: inline-flex; }

        /* TABELA */
        .table-wrapper {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden; padding: 6px;
        }
        table.dataTable.table-neo {
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            background: var(--card-bg); color: var(--text-color); margin: 0 !important;
        }
        table.dataTable.table-neo thead th {
            background: var(--primary) !important; color: #fff !important;
            font-weight: 800; text-transform: uppercase; font-size: 0.72rem;
            letter-spacing: 1px; padding: 14px 12px !important;
            border: none !important;
            border-bottom: 3px solid var(--shadow-color) !important;
        }
        table.dataTable.table-neo thead th:first-child { border-top-left-radius: 10px; }
        table.dataTable.table-neo thead th:last-child  { border-top-right-radius: 10px; }
        table.dataTable.table-neo tbody td {
            padding: 12px !important; font-size: 13.5px;
            border: none !important;
            border-bottom: 1px solid rgba(0,0,0,0.07) !important;
            vertical-align: middle !important;
        }
        table.dataTable.table-neo tbody tr:last-child td { border-bottom: none !important; }
        table.dataTable.table-neo tbody tr:hover td { background: rgba(0,0,0,0.025); }
        table.dataTable.table-neo tbody tr.row-modified td { background: rgba(255,193,7,0.15); }
        table.dataTable.table-neo tbody tr.row-modified:hover td { background: rgba(255,193,7,0.22); }
        table.dataTable.table-neo tbody tr.row-empty td { background: rgba(23,162,184,0.06); }
        table.dataTable.table-neo tbody tr.row-divergent td { background: rgba(255,193,7,0.10); }

        .cell-id { font-weight: 800; color: var(--text-color); opacity: 0.55; font-size: 0.8rem; }
        .cell-empresa {
            display: inline-block; background: var(--shadow-color); color: #fff;
            padding: 3px 10px; border-radius: 20px;
            font-weight: 800; font-size: 0.7rem;
            letter-spacing: 0.4px; text-transform: uppercase;
        }
        .cell-nome { font-weight: 800; color: var(--text-color); }

        .tipologia-chip {
            display: inline-block; background: var(--info); color: #fff;
            padding: 4px 12px; border-radius: 20px;
            font-weight: 800; font-size: 0.72rem;
            letter-spacing: 0.4px; text-transform: uppercase;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            border: 2px solid var(--shadow-color);
            white-space: nowrap;
        }
        .tipologia-chip.empty {
            background: #e5e7eb; color: #6b7280; font-style: italic;
            text-transform: none; box-shadow: none; border-color: #d1d5db;
        }
        .tipologia-chip.bm-yes   { background: var(--sea); color: #fff; }
        .tipologia-chip.bm-no    { background: #9ca3af; color: #fff; }
        .tipologia-chip.bm-divergent {
            background: var(--warning); color: #000;
            border-color: #b45309;
        }

        .atual-stack, .nova-stack {
            display: flex; flex-direction: column; gap: 6px;
            align-items: flex-start;
        }
        .nova-stack { align-items: stretch; }

        .input-edit {
            width: 100%;
            border: 2px solid var(--shadow-color); border-radius: 10px;
            padding: 8px 12px; background: #fff; color: var(--text-color);
            font-family: inherit; font-weight: 600; font-size: 0.9rem;
            transition: all 0.15s;
        }
        .input-edit:focus {
            outline: none; box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }
        .input-distancia { text-transform: none; }
        .input-detalhe   { text-transform: none; }

        .btn-neo {
            background: var(--btn-bg); color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px; padding: 9px 18px;
            font-weight: 800; font-size: 0.82rem;
            transition: all 0.15s; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
            white-space: nowrap;
        }
        .btn-neo:hover:not(:disabled) {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: var(--btn-hover); color: #fff;
        }
        .btn-neo:active:not(:disabled) { transform: translate(1px, 1px); box-shadow: 1px 1px 0 0 var(--shadow-color); }
        .btn-neo:disabled { opacity: 0.5; cursor: not-allowed; background: #9ca3af; color: #fff; }
        .btn-neo.saved { background: var(--success); color: #fff; opacity: 1; }

        /* DATATABLES */
        .dataTables_wrapper .dataTables_filter { display: none; }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_info {
            padding: 12px 8px !important; font-size: 13px;
            font-weight: 600; color: var(--text-color);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--shadow-color); border-radius: 8px;
            padding: 4px 8px; background: #fff; color: var(--text-color);
            font-weight: 700; font-family: inherit; margin: 0 4px;
        }
        .dataTables_wrapper .dataTables_paginate { padding: 12px 8px !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-color) !important;
            border: 2px solid var(--shadow-color) !important;
            border-radius: 8px !important; padding: 5px 12px !important;
            margin: 0 2px !important; font-weight: 700 !important;
            background: #fff !important; transition: all 0.15s !important;
            font-size: 13px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary) !important; color: #fff !important;
            transform: translateY(-2px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary) !important; color: #fff !important;
            border-color: var(--shadow-color) !important;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            opacity: 0.4; transform: none; box-shadow: none;
        }

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
<body data-theme-primary="<?php echo htmlspecialchars($themeSafe['primary']); ?>"
      data-limite-bm="<?php echo LIMITE_BEIRA_MAR_METROS; ?>">

<!-- HEADER -->
<?php
$pageTitle    = 'Empreendimentos - Distância do Mar';           // OBRIGATÓRIO
$pageSubtitle = 'SunnyImob';  // opcional
$pageIcon     = 'fa-city';                   // opcional (default: fa-building)
include 'header.php';
?>

<!-- CONTEÚDO -->
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

            <label class="neo-switch" title="Mostra apenas empreendimentos sem distância cadastrada">
                <input type="checkbox" id="filterEmpty">
                <span class="switch-track"></span>
                <span><i class="fas fa-filter"></i> Só sem distância</span>
            </label>
        </div>

        <div class="unsaved-hint" id="unsavedHint">
            <i class="fas fa-exclamation-circle"></i>
            <span><span id="unsavedCount">0</span> alteração(ões) não salva(s)</span>
        </div>
    </div>

    <div class="table-wrapper" style="overflow-x: auto;">
        <table id="tabelaDistancia" class="table table-neo">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th style="width: 140px;">Empresa</th>
                    <th>Empreendimento</th>
                    <th style="width: 150px;">Atual</th>
                    <th style="width: 190px;">Nova Distância</th>
                    <th style="width: 24%;">Próximo Mar (Detalhe)</th>
                    <th style="width: 130px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $sql = "SELECT e.Empreend_ID, e.NomeEmpreendimento, e.Empresa_Id,
                                   e.DistanciaMar, e.BeiraMar, e.ProximoMarDetalhe,
                                   COALESCE(emp.NomeEmpresa, '—') AS NomeEmpresa
                            FROM empreendimento e
                            LEFT JOIN empresa emp ON e.Empresa_Id = emp.Empresa_Id
                            ORDER BY e.NomeEmpreendimento";

                    $stmt = $db->prepare($sql);
                    $stmt->execute();
                    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($empreendimentos as $emp):
                        $id      = $emp['Empreend_ID'];
                        $nome    = $emp['NomeEmpreendimento'];
                        $empresa = $emp['NomeEmpresa'];

                        $distRaw = $emp['DistanciaMar'] ?? '';
                        $temDist = ($distRaw !== '' && $distRaw !== null);
                        $dist    = $temDist ? (float)$distRaw : null;

                        $isBM    = (int)($emp['BeiraMar'] ?? 0) === 1;
                        $detalhe = (string)($emp['ProximoMarDetalhe'] ?? '');

                        // Divergência: valor salvo no banco não bate com a regra
                        $regraBM    = $temDist && $dist <= LIMITE_BEIRA_MAR_METROS;
                        $divergente = $temDist
                            ? ($isBM !== $regraBM)
                            : $isBM; // sem distância mas BeiraMar=1 → divergente

                        $rowClasses = [];
                        if (!$temDist)  $rowClasses[] = 'row-empty';
                        if ($divergente) $rowClasses[] = 'row-divergent';

                        $distFormatada = $temDist
                            ? (($dist === floor($dist)) ? (int)$dist : $dist) . 'm'
                            : '';
                ?>
                <tr id="linha_<?php echo htmlspecialchars($id); ?>"
                    data-id="<?php echo htmlspecialchars($id); ?>"
                    data-original-beiramar="<?php echo $isBM ? 1 : 0; ?>"
                    data-original-empty="<?php echo $temDist ? 0 : 1; ?>"
                    class="<?php echo implode(' ', $rowClasses); ?>">

                    <td><span class="cell-id">#<?php echo htmlspecialchars($id); ?></span></td>
                    <td><span class="cell-empresa"><?php echo htmlspecialchars(strtoupper($empresa)); ?></span></td>
                    <td><span class="cell-nome"><?php echo htmlspecialchars($nome); ?></span></td>

                    <!-- COLUNA: Atual -->
                    <td>
                        <div class="atual-stack">
                            <span class="chip-atual-distancia tipologia-chip <?php echo $temDist ? '' : 'empty'; ?>">
                                <?php echo $temDist ? htmlspecialchars($distFormatada) : 'sem distância'; ?>
                            </span>
                            <span class="chip-atual-beira-mar tipologia-chip <?php
                                echo $divergente ? 'bm-divergent' : ($isBM ? 'bm-yes' : 'bm-no'); ?>"
                                title="<?php echo $divergente
                                    ? 'Divergente: o valor no banco não confere com a regra (≤ ' . LIMITE_BEIRA_MAR_METROS . 'm). Salve para corrigir.'
                                    : ''; ?>">
                                <?php if ($divergente): ?><i class="fas fa-exclamation-triangle"></i> <?php endif; ?>
                                <?php echo $isBM ? 'Beira Mar' : 'Não Beira Mar'; ?>
                            </span>
                        </div>
                    </td>

                    <!-- COLUNA: Nova Distância + preview -->
                    <td>
                        <div class="nova-stack">
                            <input type="number"
                                   class="input-edit input-distancia"
                                   id="distancia_<?php echo htmlspecialchars($id); ?>"
                                   data-id="<?php echo htmlspecialchars($id); ?>"
                                   data-original-distancia="<?php echo $temDist ? htmlspecialchars((string)$dist) : ''; ?>"
                                   value="<?php echo $temDist ? htmlspecialchars((string)$dist) : ''; ?>"
                                   min="0" step="0.1"
                                   placeholder="ex.: 10"
                                   autocomplete="off">
                            <span class="beira-mar-preview tipologia-chip <?php
                                echo $temDist ? ($isBM ? 'bm-yes' : 'bm-no') : 'empty'; ?>">
                                <?php if ($temDist): ?>
                                    <?php echo $isBM ? '→ Beira Mar' : '→ Não Beira Mar'; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </span>
                        </div>
                    </td>

                    <!-- COLUNA: ProximoMarDetalhe -->
                    <td>
                        <input type="text"
                               class="input-edit input-detalhe"
                               id="detalhe_<?php echo htmlspecialchars($id); ?>"
                               data-id="<?php echo htmlspecialchars($id); ?>"
                               data-original-detalhe="<?php echo htmlspecialchars($detalhe); ?>"
                               value="<?php echo htmlspecialchars($detalhe); ?>"
                               placeholder="ex.: Praia do Forte, Orla, Lagoa…"
                               maxlength="255"
                               autocomplete="off">
                    </td>

                    <!-- COLUNA: Ação -->
                    <td>
                        <button class="btn-neo btn-update"
                                data-id="<?php echo htmlspecialchars($id); ?>"
                                disabled
                                title="Salvar alterações">
                            <i class="fas fa-check"></i> Salvar
                        </button>
                    </td>
                </tr>
                <?php
                    endforeach;

                    if (empty($empreendimentos)) {
                        echo "<tr><td colspan='7' style='text-align:center; font-weight:700; opacity:0.6;'>Nenhum empreendimento encontrado</td></tr>";
                    }
                } catch (PDOException $e) {
                    echo "<tr><td colspan='7' style='color: var(--danger); font-weight: 700;'>";
                    echo "Erro ao carregar dados: " . htmlspecialchars($e->getMessage());
                    echo "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

</div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

<script>
(function () {
    'use strict';

    var THEME_PRIMARY = document.body.getAttribute('data-theme-primary') || '#800020';
    var LIMITE_BM     = parseFloat(document.body.getAttribute('data-limite-bm')) || 10;

    var Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 3000, timerProgressBar: true,
        didOpen: function (toast) {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    function beforeUnloadHandler(e) {
        if (document.querySelectorAll('tr.row-modified').length > 0) {
            e.preventDefault(); e.returnValue = ''; return '';
        }
    }

    // ============================================================
    // Helpers de normalização / formatação de distância
    // ============================================================
    function normalizeDistancia(v) {
        if (v === null || v === undefined) return '';
        var s = String(v).trim();
        if (s === '') return '';
        var n = parseFloat(s);
        if (isNaN(n) || n < 0) return '';
        return String(n);
    }

    function formatDistancia(v) {
        var n = normalizeDistancia(v);
        if (n === '') return '';
        var f = parseFloat(n);
        if (f === Math.floor(f)) return f + 'm';
        return f.toFixed(2).replace(/\.?0+$/, '').replace('.', ',') + 'm';
    }

    function computeBeiraMar(v) {
        var n = normalizeDistancia(v);
        if (n === '') return null;
        return parseFloat(n) <= LIMITE_BM;
    }

    function atualizarPreviewChip($chip, val) {
        var bm = computeBeiraMar(val);
        $chip.removeClass('bm-yes bm-no empty');
        if (bm === null) {
            $chip.addClass('empty').text('—');
        } else if (bm) {
            $chip.addClass('bm-yes').text('→ Beira Mar');
        } else {
            $chip.addClass('bm-no').text('→ Não Beira Mar');
        }
    }

    // ============================================================
    // ready
    // ============================================================
    $(document).ready(function () {

        // ---- DataTables ----
        var table = $('#tabelaDistancia').DataTable({
           "language": {
    "emptyTable": "Nenhum registro encontrado",
    "info": "Mostrando _START_ até _END_ de _TOTAL_ registros",
    "infoEmpty": "Mostrando 0 até 0 de 0 registros",
    "infoFiltered": "(Filtrados de _MAX_ registros)",
    "infoThousands": ".",
    "loadingRecords": "Carregando...",
    "processing": "Processando...",
    "search": "Pesquisar",
    "zeroRecords": "Nenhum registro encontrado",
    "paginate": {
        "first": "Primeiro",
        "last": "Último",
        "next": "Próximo",
        "previous": "Anterior"
    },
    "lengthMenu": "Exibir _MENU_ por página",
    "aria": {
        "sortAscending": ": Ordenar colunas de forma ascendente",
        "sortDescending": ": Ordenar colunas de forma descendente"
    }
},
            "pageLength": 100,
            "lengthMenu": [[50, 100, 200, -1], [50, 100, 200, "Todos"]],
            "order": [],
            "columnDefs": [
                { "orderable": false, "targets": [3, 4, 5, 6] }
            ],
            "drawCallback": function () {
                $('.btn-update').each(function () {
                    var id = $(this).data('id');
                    if ($('#distancia_' + id).length) atualizarEstadoLinha($('#linha_' + id));
                });
            }
        });

        // ---- Filtro "só sem distância" (usa o valor real do input) ----
        var $search      = $('#searchInput');
        var $clear       = $('#searchClear');
        var $filterEmpty = $('#filterEmpty');
        var searchTimer;

        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'tabelaDistancia') return true;
            if (!$filterEmpty.is(':checked')) return true;
            var rowNode = settings.aoData[dataIndex] && settings.aoData[dataIndex].nTr;
            if (!rowNode) return true;
            var input = rowNode.querySelector('.input-distancia');
            return input && String(input.value || '').trim() === '';
        });

        function redesenhar() { table.draw(); atualizarContadorNaoSalvos(); }

        $search.on('input', function () {
            var v = $(this).val();
            $clear.toggleClass('visible', v.length > 0);
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { table.search(v).draw(); }, 150);
        });
        $search.on('keydown', function (e) { if (e.key === 'Escape') $(this).val('').trigger('input'); });
        $clear.on('click', function () { $search.val('').trigger('input').focus(); });
        $filterEmpty.on('change', redesenhar);

        // ============================================================
        // Estado da linha
        // ============================================================
        function atualizarEstadoLinha($row) {
            var id = $row.data('id');
            var $distInput = $('#distancia_' + id);
            var $detInput  = $('#detalhe_' + id);

            var origDist = normalizeDistancia($distInput.data('original-distancia'));
            var currDist = normalizeDistancia($distInput.val());
            var origDet  = String($detInput.data('original-detalhe') || '').trim();
            var currDet  = String($detInput.val() || '').trim();

            var changed = (origDist !== currDist) || (origDet !== currDet);
            $row.toggleClass('row-modified', changed);
            $row.find('.btn-update').prop('disabled', !changed);
            atualizarContadorNaoSalvos();
        }

        function atualizarContadorNaoSalvos() {
            var count = $('.row-modified').length;
            $('#unsavedCount').text(count);
            $('#unsavedHint').toggleClass('visible', count > 0);
        }

        // ============================================================
        // Inputs
        // ============================================================
        $(document).on('input', '.input-distancia', function () {
            var $row = $(this).closest('tr');
            atualizarPreviewChip($row.find('.beira-mar-preview'), $(this).val());
            atualizarEstadoLinha($row);
        });

        $(document).on('input', '.input-detalhe', function () {
            atualizarEstadoLinha($(this).closest('tr'));
        });

        // Enter salva / Esc cancela
        $(document).on('keydown', '.input-edit', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault(); $(this).blur();
                var $row = $(this).closest('tr');
                if ($row.hasClass('row-modified')) salvarLinha($row.data('id'));
            } else if (e.key === 'Escape') {
                var id = $(this).data('id');
                var $row = $(this).closest('tr');
                if ($(this).hasClass('input-distancia')) {
                    $(this).val($(this).data('original-distancia') || '');
                    atualizarPreviewChip($row.find('.beira-mar-preview'), $(this).val());
                } else {
                    $(this).val($(this).data('original-detalhe') || '');
                }
                atualizarEstadoLinha($row);
            }
        });

        $(document).on('click', '.btn-update', function () {
            salvarLinha($(this).data('id'));
        });

        // ============================================================
        // Salvamento
        // ============================================================
        function salvarLinha(id) {
            var $row      = $('#linha_' + id);
            var $distIn   = $('#distancia_' + id);
            var $detIn    = $('#detalhe_' + id);
            var $btn      = $row.find('.btn-update');

            var novaDistRaw = String($distIn.val() || '').trim();
            var novoDet     = String($detIn.val() || '').trim();
            var normDist    = normalizeDistancia(novaDistRaw);
            var origDist    = normalizeDistancia($distIn.data('original-distancia'));
            var origDet     = String($detIn.data('original-detalhe') || '').trim();

            // Validação
            if (novaDistRaw !== '' && (isNaN(parseFloat(novaDistRaw)) || parseFloat(novaDistRaw) < 0)) {
                Toast.fire({ icon: 'warning', title: 'Distância inválida. Informe um número ≥ 0.' });
                $distIn.focus();
                return;
            }

            if (normDist === origDist && novoDet === origDet) {
                Toast.fire({ icon: 'info', title: 'Nenhuma alteração para salvar.' });
                return;
            }

            var btnHtmlOriginal = $btn.html();
            $btn.prop('disabled', true).removeClass('saved')
                .html('<i class="fas fa-spinner fa-spin"></i> Salvando…');
            $distIn.prop('disabled', true);
            $detIn.prop('disabled', true);

            $.ajax({
                url: "atualizar_distanciamar.php",
                type: "POST",
                data: { id: id, distancia: normDist, detalhe: novoDet },
                dataType: "text"
            }).done(function (raw) {
                console.log("[DEBUG] Resposta bruta:", raw);
                var result = null;
                try { result = JSON.parse(raw); } catch (e) { /* mantém null */ }

                if (result && result.success) {
                    finalizarSucesso(id, normDist, novoDet, result.message, btnHtmlOriginal);
                    return;
                }
                if (result && result.success === false) {
                    $btn.html(btnHtmlOriginal);
                    $distIn.prop('disabled', false);
                    $detIn.prop('disabled', false);
                    Toast.fire({ icon: 'error', title: result.message || 'Erro ao atualizar.' });
                    return;
                }

                // Resposta não-JSON
                $btn.html(btnHtmlOriginal);
                $distIn.prop('disabled', false);
                $detIn.prop('disabled', false);

                var dica = '';
                var rawTrim = String(raw || '').trim();
                if (rawTrim.charAt(0) === '<') {
                    dica = 'O servidor retornou <b>HTML</b>. Verifique se <code>atualizar_distanciamar.php</code> existe, se algum include imprime algo, ou se há redirect para login.';
                } else if (rawTrim === '') {
                    dica = 'O servidor retornou <b>vazio</b>. Erro fatal silencioso — verifique o log de erros do PHP.';
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
                        }) + '</pre>',
                    icon: 'error', confirmButtonColor: THEME_PRIMARY, width: 720
                });
            }).fail(function (xhr) {
                $btn.html(btnHtmlOriginal);
                $distIn.prop('disabled', false);
                $detIn.prop('disabled', false);
                console.error("[DEBUG] AJAX falhou:", xhr.status, xhr.responseText);

                Swal.fire({
                    title: 'Falha na requisição',
                    html:
                        '<p style="text-align:left;"><b>HTTP:</b> ' + xhr.status + '</p>' +
                        '<pre style="text-align:left;font-size:11px;max-height:240px;overflow:auto;background:#111;color:#0f0;padding:10px;border-radius:6px;white-space:pre-wrap;word-break:break-all;">' +
                        String(xhr.responseText || '(vazio)').replace(/[&<>]/g, function (c) {
                            return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c];
                        }) + '</pre>',
                    icon: 'error', confirmButtonColor: THEME_PRIMARY, width: 720
                });
            });
        }

        // ============================================================
        // Sucesso
        // ============================================================
        function finalizarSucesso(id, normDist, novoDet, message, btnHtmlOriginal) {
            var $row     = $('#linha_' + id);
            var $distIn  = $('#distancia_' + id);
            var $detIn   = $('#detalhe_' + id);
            var $btn     = $row.find('.btn-update');
            var $chipDist = $row.find('.chip-atual-distancia');
            var $chipBM   = $row.find('.chip-atual-beira-mar');

            var bm       = computeBeiraMar(normDist);
            var isEmpty  = normDist === '';
            var isBM     = bm === true;

            // Old state (para ajustar stats)
            var oldBM    = parseInt($row.data('original-beiramar')) === 1;
            var oldEmpty = parseInt($row.data('original-empty')) === 1;

            // Persistir no DOM
            $distIn.data('original-distancia', normDist);
            $detIn.data('original-detalhe', novoDet);
            $row.data('original-beiramar', isBM ? 1 : 0);
            $row.data('original-empty', isEmpty ? 1 : 0);

            // Atualizar chips "Atual"
            if (isEmpty) {
                $chipDist.addClass('empty').text('sem distância');
            } else {
                $chipDist.removeClass('empty').text(formatDistancia(normDist));
            }

            $chipBM.removeClass('bm-yes bm-no bm-divergent').html('');
            if (isBM) {
                $chipBM.addClass('bm-yes').text('Beira Mar');
            } else {
                $chipBM.addClass('bm-no').text('Não Beira Mar');
            }

            // Preview (recalcula)
            atualizarPreviewChip($row.find('.beira-mar-preview'), normDist);

            // Classes da linha
            $row.removeClass('row-modified row-divergent');
            $row.toggleClass('row-empty', isEmpty);

            // Stats
            ajustarStats(oldBM, isBM, oldEmpty, isEmpty);

            $btn.prop('disabled', true).addClass('saved')
                .html('<i class="fas fa-check-circle"></i> Salvo');
            atualizarContadorNaoSalvos();
            Toast.fire({ icon: 'success', title: message || 'Distância atualizada com sucesso.' });

            setTimeout(function () {
                $btn.removeClass('saved').html(btnHtmlOriginal);
                $distIn.prop('disabled', false);
                $detIn.prop('disabled', false);
            }, 1500);
        }

        function ajustarStats(oldBM, newBM, oldEmpty, newEmpty) {
            var $bmNum   = $('#statBM .num');
            var $vzNum   = $('#statVazio .num');
            var bmCount  = parseInt($bmNum.text()) || 0;
            var vzCount  = parseInt($vzNum.text()) || 0;

            if (oldBM && !newBM) bmCount--;
            if (!oldBM && newBM) bmCount++;

            if (oldEmpty && !newEmpty) vzCount--;
            if (!oldEmpty && newEmpty) vzCount++;

            $bmNum.text(Math.max(0, bmCount));
            $vzNum.text(Math.max(0, vzCount));
        }

        // ---- beforeunload ----
        window.addEventListener('beforeunload', beforeUnloadHandler);

        // ---- Fechar com confirmação ----
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