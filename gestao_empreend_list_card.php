<?php
require_once 'conn.php';
require_once 'theme_config.php';

$sql = "SELECT empreendimento.*, empresa.NomeEmpresa 
        FROM empreendimento 
        INNER JOIN empresa ON empreendimento.Empresa_Id = empresa.Empresa_Id 
        ORDER BY empreendimento.NomeEmpreendimento";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cont = 0;
} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}

$totalVendidos = 0;
$totalOcultos  = 0;
foreach ($empreendimentos as $e) {
    if (!empty($e['Vendido'])) $totalVendidos++;
    if (!empty($e['Ocultar'])) $totalOcultos++;
}
$totalEmpreend = count($empreendimentos);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="600">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Empreendimentos</title>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: <?php echo $theme['primary']; ?>;
            --primary-dark: <?php echo $theme['primary_dark']; ?>;
            --secondary: <?php echo $theme['secondary']; ?>;
            --accent: <?php echo $theme['accent']; ?>;
            --bg-body: <?php echo $theme['bg_body']; ?>;
            --text-color: <?php echo $theme['text_color']; ?>;
            --card-bg: <?php echo $theme['card_bg']; ?>;
            --shadow-color: <?php echo $theme['shadow_color']; ?>;
            --btn-bg: <?php echo $theme['btn_bg']; ?>;
            --btn-hover: <?php echo $theme['btn_hover']; ?>;
            --btn-text: <?php echo $theme['btn_text']; ?>;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            background-color: var(--bg-body);
            background-image:
                radial-gradient(circle at 1px 1px, rgba(0,0,0,0.06) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            min-height: 100vh;
            padding-bottom: 60px;
        }

        /* ============================================================ */
        /* HEADER                                                        */
        /* ============================================================ */
        .app-header {
            background: var(--primary);
            color: #fff;
            border-bottom: 5px solid var(--shadow-color);
            box-shadow: 0 6px 0px 0px var(--shadow-color);
            padding: 1.2rem 1.5rem;
            position: relative;
        }
        .app-header::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .header-inner {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 22px;
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        .header-brand { display: flex; align-items: center; gap: 18px; min-width: 0; }
        .logo-img {
            height: 84px;
            width: auto;
            max-width: 170px;
            min-width: 84px;
            object-fit: contain;
            flex-shrink: 0;
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #fff;
            padding: 6px;
        }
        .header-title { min-width: 0; }
        .header-title h1 {
            font-weight: 900;
            font-size: 1.7rem;
            margin: 0;
            line-height: 1.05;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-title h1 i {
            background: var(--shadow-color);
            color: #fff;
            padding: 9px 11px;
            border-radius: 10px;
            font-size: 1.05rem;
            box-shadow: 3px 3px 0 rgba(0,0,0,0.25);
        }
        .header-subtitle {
            margin: 6px 0 0;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.9;
        }
        .header-stats { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .stat-badge {
            background: rgba(0,0,0,0.18);
            border: 2px solid var(--shadow-color);
            border-radius: 14px;
            padding: 8px 18px;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 96px;
            transition: transform 0.15s;
        }
        .stat-badge:hover { transform: translateY(-2px); }
        .stat-badge .stat-num {
            font-size: 1.45rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -1px;
        }
        .stat-badge .stat-label {
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-top: 5px;
            opacity: 0.9;
            font-weight: 700;
        }
        .stat-badge.stat-success { background: var(--success); color: #fff; }
        .stat-badge.stat-danger  { background: var(--danger);  color: #fff; }
        .stat-badge.stat-primary { background: var(--shadow-color); color: #fff; }

        .header-actions { display: flex; gap: 10px; align-items: center; flex-shrink: 0; }
        .btn-header {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            color: var(--shadow-color);
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-radius: 12px;
            padding: 10px 18px;
            font-weight: 800;
            font-size: 0.85rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.12s ease;
            font-family: inherit;
            white-space: nowrap;
        }
        .btn-header:hover { transform: translate(-2px, -2px); box-shadow: 6px 6px 0px 0px var(--shadow-color); }
        .btn-header.btn-close { background: var(--danger); color: #fff; }

        /* ============================================================ */
        /* TOOLBAR                                                       */
        /* ============================================================ */
        .toolbar {
            max-width: 1440px;
            margin: 26px auto 16px;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .toolbar-left  { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex: 1 1 320px; min-width: 0; }
        .toolbar-right { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex-shrink: 0; }

        /* ============================================================ */
        /* SEARCH BOX NEOBRUTALISTA                                      */
        /* ============================================================ */
        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 520px;
            min-width: 260px;
        }
        .search-box input {
            width: 100%;
            background: var(--card-bg);
            color: var(--text-color);
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            padding: 12px 46px 12px 46px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.15s ease;
        }
        .search-box input:focus {
            outline: none;
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        .search-box input::placeholder {
            color: var(--text-color);
            opacity: 0.5;
            font-weight: 500;
        }
        .search-box .search-icon {
            position: absolute;
            left: 16px;
            color: var(--text-color);
            opacity: 0.6;
            pointer-events: none;
            font-size: 14px;
            transition: color 0.15s;
        }
        .search-box:focus-within .search-icon {
            color: var(--primary);
            opacity: 1;
        }
        .search-box .search-clear {
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
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            transition: all 0.12s ease;
            font-family: inherit;
        }
        .search-box .search-clear.visible { display: flex; }
        .search-box .search-clear:hover {
            transform: scale(1.12);
            background: #b02a37;
        }

        /* ============================================================ */
        /* BOTÕES                                                        */
        /* ============================================================ */
        .btn {
            padding: 7px 15px;
            border-radius: 8px;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
            display: inline-block;
            transition: all 0.15s;
            font-family: inherit;
        }
        .btn:hover { transform: translate(-2px, -2px); box-shadow: 5px 5px 0px 0px var(--shadow-color); }
        .btn-success   { background: var(--success); }
        .btn-success:hover   { background: #218838; }
        .btn-info      { background: var(--info); }
        .btn-info:hover      { background: #138496; }
        .btn-warning   { background: var(--warning); color: #000; }
        .btn-warning:hover   { background: #e0a800; }
        .btn-danger    { background: var(--danger); }
        .btn-danger:hover    { background: #b02a37; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-dark      { background: var(--dark); }
        .btn-dark:hover      { background: #23272b; }
        .btn-small {
            padding: 4px 10px;
            font-size: 11.5px;
            border-radius: 6px;
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
        }
        .btn-small:hover { transform: translate(-1px, -1px); box-shadow: 3px 3px 0px 0px var(--shadow-color); }
        .disabled-link { opacity: 0.35; cursor: not-allowed; pointer-events: none; }
        .btn-neo-toggle {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 11px 20px;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .btn-neo-toggle:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }

        /* ============================================================ */
        /* TABELA                                                        */
        /* ============================================================ */
        .table-wrapper { max-width: 1440px; margin: 0 auto; padding: 0 20px; }
        table.dataTable {
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            background: var(--card-bg);
            color: var(--text-color);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
        }
        table.dataTable thead th {
            background: var(--primary) !important;
            color: #fff !important;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 1px;
            padding: 12px 10px !important;
            border-bottom: 3px solid var(--shadow-color) !important;
        }
        table.dataTable tbody td {
            padding: 12px 10px !important;
            font-size: 13.5px;
            border-bottom: 1px solid rgba(0,0,0,0.08) !important;
            vertical-align: middle !important;
        }
        table.dataTable tbody tr:hover td { background: rgba(0,0,0,0.03); }
        table.dataTable tbody tr:last-child td { border-bottom: none !important; }
        table.dataTable img {
            max-width: 90px;
            height: auto;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            display: block;
            margin-top: 6px;
        }

        .btns-links { display: flex; flex-wrap: wrap; gap: 4px; max-width: 200px; }
        .btns-acoes { display: flex; flex-wrap: wrap; gap: 5px; max-width: 220px; }

        .badge-vendido,
        .badge-oculto {
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.68rem;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            display: inline-block;
            margin-right: 4px;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-vendido { background: var(--success); color: #fff; }
        .badge-oculto  { background: var(--danger);  color: #fff; }

        /* ============================================================ */
        /* DATATABLES                                                    */
        /* ============================================================ */
        .dataTables_wrapper { color: var(--text-color); padding: 0; }

        /* 🔇 Oculta o campo de busca nativo do DataTables — usamos o nosso */
        .dataTables_wrapper .dataTables_filter { display: none !important; }

        .dataTables_wrapper .dataTables_length select {
            color: var(--text-color);
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            padding: 6px 12px;
            font-family: inherit;
            font-weight: 600;
            margin-left: 6px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-color) !important;
            border: 2px solid var(--shadow-color) !important;
            border-radius: 8px !important;
            padding: 5px 12px !important;
            margin: 0 2px !important;
            font-weight: 700 !important;
            transition: all 0.15s !important;
            background: var(--card-bg) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary) !important;
            color: #fff !important;
            transform: translateY(-2px);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--shadow-color) !important;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_length {
            padding: 10px 4px !important;
            font-size: 13px;
            font-weight: 600;
        }

        /* ============================================================ */
        /* CARDS                                                         */
        /* ============================================================ */
        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .card {
            background: var(--card-bg);
            color: var(--text-color);
            border-radius: 16px;
            padding: 18px;
            border: 3px solid var(--shadow-color);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .card:hover { transform: translateY(-4px); box-shadow: 8px 8px 0px 0px var(--shadow-color); }
        .card h4 {
            margin: 0;
            font-size: 0.75rem;
            font-weight: 800;
            color: #fff;
            background: var(--shadow-color);
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 1px;
            align-self: flex-start;
        }
        .card .card-title {
            font-weight: 800;
            font-size: 1rem;
            line-height: 1.3;
            color: var(--primary);
        }
        .card img {
            max-width: 100%;
            height: auto;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }
        .card .btns-links, .card .btns-acoes { max-width: 100%; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-color);
            opacity: 0.6;
            font-weight: 700;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; display: block; }

        /* ============================================================ */
        /* RESPONSIVO                                                    */
        /* ============================================================ */
        @media (max-width: 1024px) {
            .header-inner { grid-template-columns: auto 1fr; }
            .header-stats { grid-column: 1 / -1; justify-content: flex-start; }
        }
        @media (max-width: 768px) {
            .app-header { padding: 1rem; }
            .header-inner { grid-template-columns: 1fr; text-align: left; }
            .logo-img { height: 68px; max-width: 140px; }
            .header-title h1 { font-size: 1.3rem; }
            .header-title h1 i { padding: 6px; font-size: 0.9rem; }
            .header-actions { width: 100%; }
            .btn-header { flex: 1; justify-content: center; }
            .header-stats { width: 100%; }
            .stat-badge { flex: 1; min-width: 0; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .toolbar-left, .toolbar-right { width: 100%; flex-basis: auto; }
            .search-box { max-width: 100%; }
            .btn-neo-toggle { flex: 1; justify-content: center; }
            .table-wrapper { padding: 0 10px; }
        }
    </style>
</head>
<body>

<!-- ============================================================ -->
<!-- HEADER                                                        -->
<!-- ============================================================ -->
<header class="app-header">
    <div class="header-inner">
        <div class="header-brand">
            <img src="img/logosunny.jpg" alt="Logotipo Sunny" class="logo-img">
            <div class="header-title">
                <h1><i class="fas fa-building"></i> Empreendimentos</h1>
                <p class="header-subtitle">SunnyImob · Painel de Gestão</p>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-badge stat-primary">
                <span class="stat-num"><?php echo $totalEmpreend; ?></span>
                <span class="stat-label">Total</span>
            </div>
            <div class="stat-badge stat-success">
                <span class="stat-num"><?php echo $totalVendidos; ?></span>
                <span class="stat-label">Vendidos</span>
            </div>
            <div class="stat-badge stat-danger">
                <span class="stat-num"><?php echo $totalOcultos; ?></span>
                <span class="stat-label">Ocultos</span>
            </div>
        </div>

        <div class="header-actions">
            <button type="button" class="btn-header btn-close" onclick="window.close()">
                <i class="fas fa-times"></i> Fechar
            </button>
        </div>
    </div>
</header>

<!-- ============================================================ -->
<!-- TOOLBAR                                                       -->
<!-- ============================================================ -->
<div class="toolbar">
    <div class="toolbar-left">
        <!-- 🔍 CAMPO DE BUSCA CUSTOMIZADO -->
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input
                type="text"
                id="searchEmpreend"
                placeholder="Buscar por empresa, empreendimento ou localidade…"
                autocomplete="off"
            >
            <button type="button" class="search-clear" id="searchClear" title="Limpar busca">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div class="toolbar-right">
        <a href="gestao_empreend_create.php" class="btn btn-success" target="_blank">
            <i class="fas fa-plus"></i> Novo
        </a>
        <button id="toggleView" class="btn-neo-toggle">
            <i class="fas fa-table"></i> <span class="toggle-label">Alternar para Tabela</span>
        </button>
    </div>
</div>

<!-- ============================================================ -->
<!-- TABELA (inicialmente oculta — modo Card é o padrão)           -->
<!-- ============================================================ -->
<div class="table-wrapper" id="tableWrap" style="display:none;">
    <table id="tabelaEmpreendimentos" class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>#</th>
                <th>ID</th>
                <th>Localidade</th>
                <th>Empreendimento</th>
                <th>Links</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($empreendimentos as $emp): ?>
            <?php $cont++; ?>
            <tr>
                <td><?php echo $cont; ?></td>
                <td><?php echo htmlspecialchars($emp['Empreend_ID']); ?></td>
                <td><?php echo mb_strtoupper(htmlspecialchars($emp['Localidade'])); ?></td>
                <td>
                    <strong><?php echo mb_strtoupper(htmlspecialchars($emp['NomeEmpresa'])); ?></strong>
                    — <?php echo mb_strtoupper(htmlspecialchars($emp['NomeEmpreendimento'])); ?>
                    <div>
                        <?php if (!empty($emp['Vendido']) && $emp['Vendido']): ?>
                            <span class="badge-vendido">100% Vendido</span>
                        <?php endif; ?>
                        <?php if (!empty($emp['Ocultar']) && $emp['Ocultar']): ?>
                            <span class="badge-oculto">Oculto</span>
                        <?php endif; ?>
                    </div>
                    <?php if (file_exists('card/' . $emp['Empreend_ID'] . '.jpg')): ?>
                        <img src="card/<?php echo $emp['Empreend_ID']; ?>.jpg" alt="Imagem">
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btns-links">
                        <?php if (!empty($emp['LinkMapa'])): ?>
                            <a href="<?php echo htmlspecialchars($emp['LinkMapa']); ?>" target="_blank" class="btn btn-small btn-secondary">Mapa</a>
                        <?php else: ?>
                            <span title="Mapa indisponível" class="btn btn-small btn-secondary disabled-link">🚫</span>
                        <?php endif; ?>
                        <?php if (!empty($emp['LinkFicha'])): ?>
                            <a href="<?php echo htmlspecialchars($emp['LinkFicha']); ?>" target="_blank" class="btn btn-small btn-success">Ficha</a>
                        <?php else: ?>
                            <span title="Ficha indisponível" class="btn btn-small btn-success disabled-link">🚫</span>
                        <?php endif; ?>
                        <?php if (!empty($emp['LinkSite'])): ?>
                            <a href="<?php echo htmlspecialchars($emp['LinkSite']); ?>" target="_blank" class="btn btn-small btn-danger">Site</a>
                        <?php else: ?>
                            <span title="Site indisponível" class="btn btn-small btn-danger disabled-link">🚫</span>
                        <?php endif; ?>
                        <?php if (!empty($emp['LinkVideo'])): ?>
                            <a href="<?php echo htmlspecialchars($emp['LinkVideo']); ?>" target="_blank" class="btn btn-small btn-warning">Vídeo</a>
                        <?php else: ?>
                            <span title="Vídeo indisponível" class="btn btn-small btn-warning disabled-link">🚫</span>
                        <?php endif; ?>
                        <?php if (!empty($emp['LinkTocca'])): ?>
                            <a href="<?php echo htmlspecialchars($emp['LinkTocca']); ?>" target="_blank" class="btn btn-small btn-info">Drive</a>
                        <?php else: ?>
                            <span title="Drive indisponível" class="btn btn-small btn-info disabled-link">🚫</span>
                        <?php endif; ?>
                        <?php if (!empty($emp['LinkConstrutora'])): ?>
                            <a href="<?php echo htmlspecialchars($emp['LinkConstrutora']); ?>" target="_blank" class="btn btn-small btn-dark">Drive Const.</a>
                        <?php else: ?>
                            <span title="Drive Const. indisponível" class="btn btn-small btn-dark disabled-link">🚫</span>
                        <?php endif; ?>
                        <?php if (!empty($emp['LinkTabela'])): ?>
                            <a href="<?php echo htmlspecialchars($emp['LinkTabela']); ?>" target="_blank" class="btn btn-small btn-secondary">Tabela</a>
                        <?php else: ?>
                            <span title="Tabela indisponível" class="btn btn-small btn-secondary disabled-link">🚫</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="btns-acoes">
                        <a href="gestao_empreend_update.php?id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-small btn-warning" target="_blank">Editar</a>
                        <a href="empreend_ficha1.php?id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-small btn-info" target="_blank">Card</a>
                        <a href="empreend_v3_imagem_paste.php?Empreend_id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-small btn-info" target="_blank">Imagem</a>
                        <a href="gestao_empreend_caract_update2.php?id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-small btn-success" target="_blank">Caract.</a>
                        <a href="gestao_empreend_links_update.php?id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-small btn-success" target="_blank">Links</a>
                        <a href="gestao_empreend_delete.php?id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Deseja excluir?');">Excluir</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ============================================================ -->
<!-- CARDS (padrão visível)                                        -->
<!-- ============================================================ -->
<div class="table-wrapper" id="cardViewWrapper">
    <div id="cardView"></div>
</div>

<script>
$(document).ready(function() {

    // ============================================================
    // 1) Inicializa DataTables
    // ============================================================
    if (!$.fn.DataTable.isDataTable('#tabelaEmpreendimentos')) {
        $('#tabelaEmpreendimentos').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json" },
            "pageLength": 100,
            "order": [[1, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": [4, 5] }
            ]
        });
    }

    const $tabela     = $('#tabelaEmpreendimentos');
    const $cardView   = $('#cardView');
    const $cardWrap   = $('#cardViewWrapper');
    const $tableWrap  = $('#tableWrap');
    const $toggleBtn  = $('#toggleView');
    const $toggleLbl  = $toggleBtn.find('.toggle-label');
    const $toggleIcon = $toggleBtn.find('i');
    const $search     = $('#searchEmpreend');
    const $searchClear= $('#searchClear');

    let modoCards = true;

    // ============================================================
    // 2) 🔍 Filtro customizado — aplica em Localidade (col 2) e
    //    Empreendimento (col 3, que já inclui o nome da empresa)
    // ============================================================
    $.fn.dataTable.ext.search.push(function(settings, data) {
        if (settings.nTable.id !== 'tabelaEmpreendimentos') return true;
        const term = ($search.val() || '').toLowerCase().trim();
        if (!term) return true;
        const localidade   = (data[2] || '').toLowerCase();
        const empreendimento = (data[3] || '').toLowerCase();
        return localidade.indexOf(term) !== -1 || empreendimento.indexOf(term) !== -1;
    });

    // ============================================================
    // 3) Gera cards a partir das linhas filtradas do DataTables
    // ============================================================
    function gerarCards() {
        const table = $tabela.DataTable();
        const rows  = table.rows({ search: 'applied' }).data().toArray();
        $cardView.empty();

        if (!rows.length) {
            $cardView.html('<div class="empty-state"><i class="fas fa-search"></i>Nenhum empreendimento encontrado.</div>');
            return;
        }

        const container = $('<div class="card-container"></div>');
        rows.forEach(row => {
            container.append(`
                <div class="card">
                    <h4>${row[2]}</h4>
                    <div class="card-title">${row[3]}</div>
                    <div class="btns-links">${row[4]}</div>
                    <div class="btns-acoes">${row[5]}</div>
                </div>
            `);
        });
        $cardView.append(container);
    }

    // ============================================================
    // 4) Alternância Tabela ↔ Cards
    // ============================================================
    function atualizarModo() {
        if (modoCards) {
            $tableWrap.hide();
            gerarCards();
            $cardWrap.show();
            $toggleLbl.text('Alternar para Tabela');
            $toggleIcon.attr('class', 'fas fa-table');
        } else {
            $cardWrap.hide();
            $tableWrap.show();
            $toggleLbl.text('Alternar para Cards');
            $toggleIcon.attr('class', 'fas fa-th-large');
        }
    }

    $toggleBtn.on('click', function() {
        modoCards = !modoCards;
        atualizarModo();
    });

    atualizarModo();

    // ============================================================
    // 5) Sempre que a tabela redesenhar (filtro, ordenação, page…)
    //    regenera os cards se estiver em modo Card.
    // ============================================================
    $tabela.on('draw.dt', function() {
        if (modoCards) gerarCards();
    });

    // ============================================================
    // 6) Busca customizada — debounce 200ms + botão de limpar
    // ============================================================
    let searchTimer;
    $search.on('input', function() {
        const val = $(this).val();
        $searchClear.toggleClass('visible', val.length > 0);

        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            $tabela.DataTable().draw();
        }, 200);
    });

    // ESC limpa busca
    $search.on('keydown', function(e) {
        if (e.key === 'Escape') {
            $(this).val('').trigger('input');
        }
    });

    // Botão X
    $searchClear.on('click', function() {
        $search.val('').trigger('input').focus();
    });
});
</script>
</body>
</html>