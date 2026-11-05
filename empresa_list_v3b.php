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

// ============================================================
// LOGO DO HEADER com fallback (bug #3)
// ============================================================
$logoHeader = !empty($_SESSION['Conf_ImgLogo']) && file_exists(__DIR__ . '/' . $_SESSION['Conf_ImgLogo'])
    ? $_SESSION['Conf_ImgLogo']
    : 'logoSunny66a.JPG';

// ============================================================
// HELPERS
// ============================================================
function RemoverNumeros($texto) {
    if (empty($texto)) return '';
    $texto = str_replace('-', ' ', $texto);            // vira espaço
    $texto = preg_replace('/[0-9]+/', ' ', $texto);    // remove números
    $texto = preg_replace('/\s+/', ' ', $texto);       // colapsa espaços
    return trim($texto);
}

// ============================================================
// BUSCAR EMPREENDIMENTOS
// ============================================================
$empreendimentos = [];

try {
    $sql = "SELECT e.*, emp.NomeEmpresa
            FROM empreendimento e
            INNER JOIN empresa emp ON emp.Empresa_Id = e.Empresa_Id
            WHERE e.Ocultar = 0
            ORDER BY e.NomeEmpreendimento";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na consulta: " . htmlspecialchars($e->getMessage()));
}

// ============================================================
// PRÉ-CARREGAR FLUXOS EM 1 QUERY (bug #1 e #2)
// ============================================================
$fluxosPorEmpreend = [];
if (!empty($empreendimentos)) {
    try {
        $stmtFluxo = $db->query("
            SELECT Fluxo_ID, Posicao, Etapa, Percentual
            FROM fluxo_Etapas
            ORDER BY Fluxo_ID, Posicao ASC
        ");
        while ($row = $stmtFluxo->fetch(PDO::FETCH_ASSOC)) {
            $fluxosPorEmpreend[$row['Fluxo_ID']][] = $row;
        }
    } catch (PDOException $e) {
        error_log("[EMPREEND_LIST] Fluxo: " . $e->getMessage());
    }
}

// Total de empreendimentos para o header
$totalEmpreend = count($empreendimentos);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empreendimentos · SunnyImob</title>

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
            --edit-color: #b8860b;
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

        /* Card container */
        .table-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
            padding: 6px;
        }

        /* Item */
        .empreendimento-item {
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            padding: 18px;
            margin-bottom: 14px;
            transition: all 0.15s;
        }
        .empreendimento-item:hover {
            transform: translateY(-2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
        }

        /* Imagens */
        .img-wrap {
            position: relative;
            width: 100%;
            height: 160px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            background: #f8f9fb;
            cursor: pointer;
            transition: all 0.15s;
        }
        .img-wrap:hover {
            transform: translate(-1px, -1px);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
        }
        .img-wrap img {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        .img-wrap .placeholder {
            width: 100%; height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.05);
            color: var(--text-color);
            opacity: 0.3;
            font-size: 2.2rem;
        }

        /* Título */
        .empreendimento-title {
            color: var(--primary);
            font-weight: 900;
            font-size: 1.25rem;
            margin: 0 0 6px;
            line-height: 1.25;
            letter-spacing: -0.3px;
        }
        .empresa-name {
            color: var(--text-color);
            opacity: 0.7;
            font-weight: 700;
            font-size: 0.9rem;
            margin: 0;
        }
        .empresa-name i { color: var(--primary); margin-right: 6px; }

        .vendido-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--danger);
            color: #fff;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            margin-left: 6px;
            vertical-align: middle;
        }

        .last-update {
            text-align: right;
            font-size: 0.72rem;
            color: var(--text-color);
            opacity: 0.55;
            font-weight: 600;
        }
        .last-update i { margin-right: 3px; }

        /* Badges */
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--primary);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            border-radius: 20px;
            padding: 3px 11px;
            margin-right: 6px;
            margin-bottom: 6px;
            font-size: 0.76rem;
            font-weight: 700;
        }
        .info-badge i { font-size: 0.72rem; }

        .feature-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #6b7280;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            border-radius: 20px;
            padding: 2px 9px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .feature-badge i { font-size: 0.68rem; }

        /* Badge Comissão destacada */
        .badge-comissao {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--warning);
            color: #000;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.78rem;
            font-weight: 800;
            margin-right: 6px;
            margin-bottom: 6px;
        }

        /* Badge Fluxo */
        .badge-fluxo {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--info);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.76rem;
            font-weight: 800;
            margin-right: 6px;
            margin-bottom: 6px;
            cursor: help;
        }

        /* Botões de ação */
        .btn-ficha, .btn-editar {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 8px;
            padding: 6px 12px;
            font-weight: 800;
            font-size: 0.78rem;
            transition: all 0.15s;
            text-decoration: none;
            margin: 2px 4px 2px 0;
            white-space: nowrap;
        }
        .btn-ficha {
            background: var(--btn-bg);
            color: var(--btn-text);
        }
        .btn-ficha:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-editar {
            background: var(--edit-color);
            color: #fff;
        }
        .btn-editar:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: #a67c00;
            color: #fff;
        }

        #buttons-links {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px dashed rgba(0,0,0,0.1);
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
            padding: 60px 20px !important;
            font-weight: 700 !important;
            opacity: 0.6 !important;
            font-style: italic;
            text-align: center;
        }

        /* Remover estilos da table do DataTables */
        #tabelaEmpreendimentos { width: 100% !important; }
        #tabelaEmpreendimentos thead { display: none; }
        #tabelaEmpreendimentos tbody td {
            padding: 0 !important;
            border: none !important;
            background: transparent !important;
        }

        /* Modal imagem */
        #imageModal {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.92);
            z-index: 9999;
            padding: 40px 20px;
            justify-content: center;
            align-items: center;
            cursor: zoom-out;
        }
        #imageModal img {
            max-width: 95vw;
            max-height: 90vh;
            object-fit: contain;
            border: 4px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 8px 8px 0 0 var(--shadow-color);
            cursor: default;
        }
        #imageModal .close-modal {
            position: absolute;
            top: 15px; right: 30px;
            color: #fff;
            font-size: 2.5rem;
            font-weight: 900;
            cursor: pointer;
            line-height: 1;
            text-shadow: 0 2px 8px rgba(0,0,0,0.6);
            transition: transform 0.15s;
        }
        #imageModal .close-modal:hover { transform: scale(1.15); }

        /* Footer */
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
            .toolbar-left, .toolbar-right { width: 100%; }
            .search-box { max-width: 100%; flex-basis: 100%; }
            .empreendimento-title { font-size: 1.05rem; }
            .img-wrap { height: 130px; }
            .last-update { text-align: left; margin-top: 6px; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="app-header">
    <div class="header-inner">
        <div class="header-brand">
            <img src="<?= htmlspecialchars($logoHeader) ?>"
                 alt="Logo"
                 class="header-logo"
                 onerror="this.onerror=null;this.src='img/logosunny.jpg';">
            <div class="header-title">
                <h1><i class="fas fa-building me-2"></i> Empreendimentos</h1>
                <p><?= $totalEmpreend ?> registros · SunnyImob</p>
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

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar empreendimento, empresa, localidade…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de cards -->
    <div class="table-card">
        <table id="tabelaEmpreendimentos" class="table" style="width:100%">
            <thead>
                <tr><th>Empreendimentos</th></tr>
            </thead>
            <tbody>
            <?php if (!empty($empreendimentos)): foreach ($empreendimentos as $empreendimento):
                $id         = (int)$empreendimento['Empreend_ID'];
                $nomeEmp    = RemoverNumeros(strtoupper(htmlspecialchars($empreendimento['NomeEmpreendimento'] ?? '')));
                $localidade = strtoupper(htmlspecialchars($empreendimento['Localidade'] ?? ''));
                $empresa    = RemoverNumeros(strtoupper(htmlspecialchars($empreendimento['NomeEmpresa'] ?? '')));
                $vendido    = !empty($empreendimento['Vendido']);

                // Imagem com cache-bust
                $imgPath = 'card/' . $id . '.jpg';
                $imgFull = __DIR__ . '/' . $imgPath;
                $hasImg  = file_exists($imgFull);
                $imgUrl  = $hasImg ? $imgPath . '?v=' . filemtime($imgFull) : '';

                // Fluxo (do pré-carregamento)
                $fluxos       = $fluxosPorEmpreend[$id] ?? [];
                $temFluxo     = !empty($fluxos);
                $qtdEtapas    = count($fluxos);
                $fluxoTooltip = '';
                if ($temFluxo) {
                    $parts = [];
                    foreach ($fluxos as $f) {
                        $parts[] = $f['Posicao'] . ') ' . $f['Etapa'] . ' (' . $f['Percentual'] . '%)';
                    }
                    $fluxoTooltip = htmlspecialchars(implode(' · ', $parts), ENT_QUOTES);
                }

                // Comissão (bug #8)
                $comissao = $empreendimento['ComissaoVenda'] ?? null;
                $temComissao = ($comissao !== null && $comissao !== '' && (float)$comissao > 0);

                // Data de atualização (bug #11)
                $dataAtu = $empreendimento['DataAtualizacao'] ?? $empreendimento['DataCadastro'] ?? null;
                $dataAtuFmt = $dataAtu ? date('d/m/Y', strtotime($dataAtu)) : null;

                // MesEntrega/AnoEntrega
                $mesEntrega = trim((string)($empreendimento['MesEntrega'] ?? ''));
                $anoEntrega = trim((string)($empreendimento['AnoEntrega'] ?? ''));
                $entregaTexto = '';
                if (is_numeric($mesEntrega) && is_numeric($anoEntrega)) {
                    $m = (int)$mesEntrega;
                    $a = (int)$anoEntrega;
                    if ($m >= 1 && $m <= 12 && $a >= 2020) {
                        $entregaTexto = str_pad($m, 2, '0', STR_PAD_LEFT) . '/' . $a;
                    } elseif ($m === 0 && $a > 0) {
                        $entregaTexto = '00/' . $a;
                    } elseif ($m > 0 && $a === 0) {
                        $entregaTexto = str_pad($m, 2, '0', STR_PAD_LEFT) . '/0000';
                    }
                }
            ?>
                <tr>
                    <td>
                        <div class="empreendimento-item">
                            <div class="row g-3">
                                <!-- Imagem -->
                                <div class="col-md-2">
                                    <?php if ($hasImg): ?>
                                        <div class="img-wrap" onclick="openImageModal('<?= htmlspecialchars($imgUrl) ?>', '<?= htmlspecialchars($empreendimento['NomeEmpreendimento'] ?? '') ?>')">
                                            <img src="<?= htmlspecialchars($imgUrl) ?>"
                                                 alt="<?= htmlspecialchars($empreendimento['NomeEmpreendimento'] ?? '') ?>"
                                                 onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\'placeholder\'><i class=\'fas fa-image\'></i></div>';">
                                        </div>
                                    <?php else: ?>
                                        <div class="img-wrap">
                                            <div class="placeholder"><i class="fas fa-building"></i></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Título + Empresa -->
                                <div class="col-md-7">
                                    <h3 class="empreendimento-title">
                                        <?= $localidade . ($localidade && $nomeEmp ? ' — ' : '') . $nomeEmp ?>
                                        <?php if ($vendido): ?>
                                            <span class="vendido-tag"><i class="fas fa-check"></i> Vendido</span>
                                        <?php endif; ?>
                                    </h3>
                                    <?php if ($empresa): ?>
                                        <p class="empresa-name"><i class="fas fa-building"></i> <?= $empresa ?></p>
                                    <?php endif; ?>
                                </div>

                                <!-- Última atualização -->
                                <div class="col-md-3">
                                    <?php if ($dataAtuFmt): ?>
                                        <div class="last-update">
                                            <i class="fas fa-clock"></i> Atualizado <?= $dataAtuFmt ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Badges de informações -->
                            <div class="d-flex flex-wrap mt-3 mb-2">
                                <?php if (!empty($empreendimento['MetragemMin']) || !empty($empreendimento['MetragemMax'])): ?>
                                    <?php
                                    $min = (float)($empreendimento['MetragemMin'] ?? 0);
                                    $max = (float)($empreendimento['MetragemMax'] ?? 0);
                                    $metTexto = '';
                                    if ($min > 0 && $max > 0 && $min != $max)  $metTexto = $min . 'm² a ' . $max . 'm²';
                                    elseif ($min > 0 && $max > 0)              $metTexto = $min . 'm²';
                                    elseif ($min > 0)                          $metTexto = $min . 'm²';
                                    elseif ($max > 0)                          $metTexto = $max . 'm²';
                                    ?>
                                    <?php if ($metTexto): ?>
                                        <span class="info-badge"><i class="fas fa-ruler-combined"></i> <?= $metTexto ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php
                                $menor = (float)($empreendimento['MenorPreco'] ?? 0);
                                $maior = (float)($empreendimento['MaiorPreco'] ?? 0);
                                if ($menor > 0 && $maior > 0 && $menor != $maior):
                                    $precoTexto = 'R$ ' . number_format($menor, 0, ',', '.') . ' a R$ ' . number_format($maior, 0, ',', '.');
                                elseif ($menor > 0):
                                    $precoTexto = 'R$ ' . number_format($menor, 0, ',', '.');
                                elseif ($maior > 0):
                                    $precoTexto = 'R$ ' . number_format($maior, 0, ',', '.');
                                else:
                                    $precoTexto = '';
                                endif;
                                ?>
                                <?php if ($precoTexto): ?>
                                    <span class="info-badge"><i class="fas fa-tag"></i> <?= $precoTexto ?></span>
                                <?php endif; ?>

                                <?php if (!empty($empreendimento['VagasGaragem']) && (int)$empreendimento['VagasGaragem'] > 0): ?>
                                    <span class="info-badge">
                                        <i class="fas fa-car"></i>
                                        <?= (int)$empreendimento['VagasGaragem'] ?> vaga(s)
                                        <?= htmlspecialchars($empreendimento['TipoGaragem'] ?? '') ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($empreendimento['Tipologia'])): ?>
                                    <span class="info-badge"><i class="fas fa-bed"></i> <?= htmlspecialchars($empreendimento['Tipologia']) ?></span>
                                <?php endif; ?>

                                <?php if ($entregaTexto): ?>
                                    <span class="info-badge"><i class="fas fa-calendar"></i> <?= $entregaTexto ?></span>
                                <?php endif; ?>

                                <?php if (!empty($empreendimento['QTD_Unidades']) && (int)$empreendimento['QTD_Unidades'] > 0): ?>
                                    <span class="info-badge">
                                        <i class="fas fa-home"></i>
                                        <?= (int)$empreendimento['QTD_Unidades'] ?> un.
                                    </span>
                                <?php endif; ?>

                                <?php if ($temComissao): ?>
                                    <span class="badge-comissao">
                                        <i class="fas fa-percentage"></i>
                                        Comissão: <?= rtrim(rtrim(number_format((float)$comissao, 2, ',', '.'), '0'), ',') ?>%
                                    </span>
                                <?php endif; ?>

                                <?php if ($temFluxo): ?>
                                    <span class="badge-fluxo" title="<?= $fluxoTooltip ?>">
                                        <i class="fas fa-stream"></i>
                                        <?= $qtdEtapas ?> etapa<?= $qtdEtapas > 1 ? 's' : '' ?> de fluxo
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Comodidades -->
                            <?php
                            $comodidades = [
                                'Academia' => 'fas fa-dumbbell',
                                'BeiraMar' => 'fas fa-umbrella-beach',
                                'Bicicletario' => 'fas fa-bicycle',
                                'Brinquedoteca' => 'fas fa-child',
                                'Coworking' => 'fas fa-laptop',
                                'Emporio' => 'fas fa-store',
                                'EspacoGourmet' => 'fas fa-utensils',
                                'FechaduraEletronica' => 'fas fa-key',
                                'GasEncanado' => 'fas fa-fire',
                                'Hidromassagem' => 'fas fa-hot-tub',
                                'Lavanderia' => 'fas fa-tshirt',
                                'LoungeBar' => 'fas fa-glass-martini-alt',
                                'Petplace' => 'fas fa-paw',
                                'Piscina2' => 'fas fa-swimming-pool',
                                'PiscinaPrivativa' => 'fas fa-swimmer',
                                'QuadraTenis' => 'fas fa-table-tennis',
                                'Recepcao24h' => 'fas fa-concierge-bell',
                                'Restaurante' => 'fas fa-utensils',
                                'Rooftop' => 'fas fa-building',
                                'SalaoJogos' => 'fas fa-gamepad',
                                'Sauna' => 'fas fa-hot-tub',
                                'Spa' => 'fas fa-spa',
                                'Varanda' => 'fas fa-door-open',
                            ];
                            $temComodidades = false;
                            foreach ($comodidades as $campo => $icone) {
                                if (!empty($empreendimento[$campo])) { $temComodidades = true; break; }
                            }
                            ?>
                            <?php if ($temComodidades): ?>
                                <div class="mb-2">
                                    <?php foreach ($comodidades as $campo => $icone): ?>
                                        <?php if (!empty($empreendimento[$campo])): ?>
                                            <span class="feature-badge">
                                                <i class="<?= $icone ?>"></i>
                                                <?= htmlspecialchars(str_replace('_', ' ', $campo)) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Botões -->
                            <div id="buttons-links">
                                <?php if (!empty($empreendimento['LinkMapa'])): ?>
                                    <a href="<?= htmlspecialchars($empreendimento['LinkMapa']) ?>" target="_blank" class="btn-ficha">
                                        <i class="fas fa-map-marked-alt"></i> Mapa
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($empreendimento['LinkSite'])): ?>
                                    <a href="<?= htmlspecialchars($empreendimento['LinkSite']) ?>" target="_blank" class="btn-ficha">
                                        <i class="fas fa-globe"></i> Site
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($empreendimento['LinkVideo'])): ?>
                                    <a href="<?= htmlspecialchars($empreendimento['LinkVideo']) ?>" target="_blank" class="btn-ficha">
                                        <i class="fas fa-video"></i> Vídeo
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($empreendimento['LinkTocca'])): ?>
                                    <a href="<?= htmlspecialchars($empreendimento['LinkTocca']) ?>" target="_blank" class="btn-ficha">
                                        <i class="fas fa-building"></i> Tocca
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($empreendimento['LinkConstrutora'])): ?>
                                    <a href="<?= htmlspecialchars($empreendimento['LinkConstrutora']) ?>" target="_blank" class="btn-ficha">
                                        <i class="fas fa-hammer"></i> Construtora
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($empreendimento['LinkTabela'])): ?>
                                    <a href="<?= htmlspecialchars($empreendimento['LinkTabela']) ?>" target="_blank" class="btn-ficha">
                                        <i class="fas fa-dollar-sign"></i> Tabela
                                    </a>
                                <?php endif; ?>

                                <a href="empreend_ficha1.php?id=<?= $id ?>" target="_blank" class="btn-ficha">
                                    <i class="fas fa-list"></i> Ficha
                                </a>

                                <?php if (UsuarioPodeEditar()): ?>
                                    <a href="gestao_empreend_links_update.php?id=<?= $id ?>" target="_blank" class="btn-editar">
                                        <i class="fas fa-link"></i> Links
                                    </a>
                                    <a href="gestao_empreend_caract_update2.php?id=<?= $id ?>" target="_blank" class="btn-editar">
                                        <i class="fas fa-edit"></i> Caract.
                                    </a>
                                    <a href="fluxo_listar.php?Empreend_id=<?= $id ?>" target="_blank" class="btn-editar">
                                        <i class="fas fa-stream"></i> Fluxo
                                    </a>
                                <?php endif; ?>

                                <?php if (UsuarioGestor() || UsuarioAdmin()): ?>
                                    <a href="gestao_painel.php" target="_blank" class="btn-editar">
                                        <i class="fas fa-cog"></i> Gestão
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <footer class="page-footer">
        <p style="margin:0;">
            &copy; <?= date('Y') ?> <strong>SunnyImob</strong> · Todos os direitos reservados
        </p>
    </footer>
</div>

<!-- Modal imagem -->
<div id="imageModal" onclick="if(event.target===this) closeImageModal();">
    <span class="close-modal" onclick="closeImageModal();">&times;</span>
    <img id="modalImage" alt="">
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

    // ============================================================
    // i18n embutido (bug #10)
    // ============================================================
    const ptBR = {
        emptyTable:     "Nenhum empreendimento encontrado",
        info:           "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty:      "Mostrando 0 a 0 de 0 registros",
        infoFiltered:   "(filtrado de _MAX_ registros no total)",
        infoThousands:  ".",
        lengthMenu:     "Exibir _MENU_ por página",
        loadingRecords: "Carregando...",
        processing:     "Processando...",
        search:         "Pesquisar:",
        zeroRecords:    "Nenhum empreendimento corresponde à busca",
        paginate: {
            first: "Primeira", last: "Última",
            next: "Próxima", previous: "Anterior"
        },
        aria: {
            sortAscending:  ": ordenar crescente",
            sortDescending: ": ordenar decrescente"
        }
    };

    // ============================================================
    // DATATABLES (bug #12: sem fake row, deixa o plugin tratar empty)
    // ============================================================
    let table = null;
    if (document.getElementById('tabelaEmpreendimentos')) {
        table = new DataTable('#tabelaEmpreendimentos', {
            language: ptBR,
            pageLength: 100,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Todos"]],
            order: [],           // ordem original do PHP
            ordering: false,     // cards não fazem sentido ordenar
            autoWidth: false,
            columnDefs: [
                { orderable: false, searchable: true, targets: 0 }
            ]
        });
    }

    // ============================================================
    // BUSCA CUSTOMIZADA
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
    // MODAL IMAGEM
    // ============================================================
    window.openImageModal = function (src, alt) {
        if (!src) return;
        document.getElementById('modalImage').src = src;
        document.getElementById('modalImage').alt = alt || '';
        document.getElementById('imageModal').style.display = 'flex';
    };

    window.closeImageModal = function () {
        document.getElementById('imageModal').style.display = 'none';
        document.getElementById('modalImage').src = '';
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeImageModal();
    });

    // ============================================================
    // FECHAR JANELA (bug #13)
    // ============================================================
    window.fecharJanela = function (event) {
        if (event) event.preventDefault();
        Swal.fire({
            title: 'Fechar janela?',
            text: 'Deseja realmente fechar esta janela?',
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

})();
</script>
</body>
</html>