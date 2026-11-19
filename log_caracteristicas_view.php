<?php
// log_caracteristicas_view.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ ARQUIVOS OBRIGATÓRIOS ============
foreach (array('conn.php', 'theme_config.php') as $req) {
    if (!file_exists(__DIR__ . '/' . $req)) {
        die('Arquivo obrigatório não encontrado: ' . htmlspecialchars($req));
    }
}
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/theme_config.php';

// ============ LOGIN ============
if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}

// ============ CONEXÃO ============
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

// ============ TEMA SEGURO ============
$themeDefaults = array(
    'primary'     => '#800000',
    'primary_dark'=> '#4B0C1D',
    'secondary'   => '#6c757d',
    'accent'      => '#ffc107',
    'bg_body'     => '#f0f2f5',
    'text_color'  => '#1a1a2e',
    'card_bg'     => '#ffffff',
    'shadow_color'=> '#1a1a2e',
    'btn_bg'      => '#800000',
    'btn_hover'   => '#4B0C1D',
    'btn_text'    => '#ffffff',
);

$themeSafe = array_merge(
    $themeDefaults,
    (isset($theme) && is_array($theme)) ? $theme : array()
);

// ============ FILTROS ============
$filtroEmp  = isset($_GET['emp'])  ? (int)$_GET['emp']  : 0;
$filtroUser = isset($_GET['user']) && is_string($_GET['user']) ? trim($_GET['user']) : '';
$filtroDe   = isset($_GET['de'])   && is_string($_GET['de'])   ? trim($_GET['de'])   : '';
$filtroAte  = isset($_GET['ate'])  && is_string($_GET['ate'])  ? trim($_GET['ate'])  : '';

$erroLog         = null;
$logs            = array();
$empreendimentos = array();
$usuarios        = array();

try {
    // Dropdown de empreendimentos
    $empreendimentos = $db->query("
        SELECT Empreend_ID, NomeEmpreendimento
        FROM empreendimento
        ORDER BY NomeEmpreendimento
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Lista de usuários que já fizeram log
    $usuarios = $db->query("
        SELECT DISTINCT Usuario FROM log_caracteristicas ORDER BY Usuario
    ")->fetchAll(PDO::FETCH_COLUMN);

    // Query principal com filtros
    $sql    = "SELECT * FROM log_caracteristicas WHERE 1=1";
    $params = array();

    if ($filtroEmp > 0) {
        $sql .= " AND Empreend_ID = :emp";
        $params[':emp'] = $filtroEmp;
    }
    if ($filtroUser !== '') {
        $sql .= " AND Usuario = :user";
        $params[':user'] = $filtroUser;
    }
    if ($filtroDe !== '') {
        $sql .= " AND DataHora >= :de";
        $params[':de'] = $filtroDe . ' 00:00:00';
    }
    if ($filtroAte !== '') {
        $sql .= " AND DataHora <= :ate";
        $params[':ate'] = $filtroAte . ' 23:59:59';
    }

    $sql .= " ORDER BY DataHora DESC, Log_Id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $erroLog = "Não foi possível carregar o log. Verifique se a tabela 'log_caracteristicas' foi criada.";
}

// ============ TOTAIS ============
$totalRegistros = count($logs);
$totalCampos    = 0;
foreach ($logs as $l) {
    $totalCampos += (int)$l['QtdAlteracoes'];
}
$ultimoLog = !empty($logs) ? $logs[0]['DataHora'] : null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Atualizações — Características</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <style>
        :root {
            --primary: <?php echo $themeSafe['primary']; ?>;
            --primary-dark: <?php echo $themeSafe['primary_dark']; ?>;
            --bg-body: <?php echo $themeSafe['bg_body']; ?>;
            --text-color: <?php echo $themeSafe['text_color']; ?>;
            --card-bg: <?php echo $themeSafe['card_bg']; ?>;
            --shadow-color: <?php echo $themeSafe['shadow_color']; ?>;
            --btn-bg: <?php echo $themeSafe['btn_bg']; ?>;
            --btn-hover: <?php echo $themeSafe['btn_hover']; ?>;
            --btn-text: <?php echo $themeSafe['btn_text']; ?>;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding-bottom: 40px;
            -webkit-font-smoothing: antialiased;
        }

        .app-header {
            background: var(--primary);
            color: #fff;
            border-bottom: 5px solid var(--shadow-color);
            box-shadow: 0 6px 0 0 var(--shadow-color);
            padding: 14px 0;
            position: sticky; top: 0; z-index: 50;
            margin-bottom: 24px;
        }
        .app-header::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .header-inner {
            max-width: 1400px; margin: 0 auto; padding: 0 20px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap; position: relative; z-index: 1;
        }
        .header-brand { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .logo-img {
            height: 58px; width: auto; max-width: 160px; min-width: 58px;
            object-fit: contain;
            border: 3px solid var(--shadow-color); border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #fff; padding: 5px 8px; flex-shrink: 0;
        }
        .header-title h1 { margin: 0; font-size: 1.15rem; font-weight: 900; letter-spacing: -0.3px; line-height: 1.15; }
        .header-title p  { margin: 4px 0 0; font-size: 0.78rem; font-weight: 600; opacity: 0.9; }
        .header-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }
        .meta-chip {
            background: rgba(0,0,0,0.2); border: 2px solid var(--shadow-color);
            border-radius: 10px; padding: 6px 12px; font-size: 0.74rem;
            font-weight: 700; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-neo {
            border: 2px solid var(--shadow-color); box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 10px; padding: 8px 16px; font-weight: 800;
            font-size: 0.82rem; cursor: pointer; transition: all 0.15s;
            display: inline-flex; align-items: center; gap: 6px; font-family: inherit;
            text-decoration: none;
        }
        .btn-neo:hover { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 0 var(--shadow-color); }
        .btn-primary-neo { background: #fff; color: var(--primary); }
        .btn-primary-neo:hover { background: #f8f9fb; color: var(--primary-dark); }
        .btn-danger-neo { background: var(--danger); color: #fff; }
        .btn-danger-neo:hover { background: #b02a37; color: #fff; }

        .container-neo { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

        .section-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            margin-bottom: 22px;
            overflow: hidden;
        }
        .section-header {
            background: var(--primary); color: #fff; padding: 14px 22px;
            display: flex; align-items: center; gap: 14px;
            border-bottom: 3px solid var(--shadow-color);
        }
        .section-num {
            width: 34px; height: 34px; border-radius: 10px;
            background: rgba(0,0,0,0.25); display: flex;
            align-items: center; justify-content: center;
            font-weight: 900; font-size: 0.9rem; flex-shrink: 0;
        }
        .section-title { flex: 1; min-width: 0; }
        .section-title h2 { margin: 0; font-size: 1.02rem; font-weight: 900; letter-spacing: -0.2px; }
        .section-title p  { margin: 2px 0 0; font-size: 0.76rem; opacity: 0.85; font-weight: 500; }
        .section-body { padding: 22px; }

        .field-label {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.4px; color: var(--text-color); opacity: 0.85; margin-bottom: 6px;
        }
        .field-label i { color: var(--primary); font-size: 0.8rem; }
        .form-control, .form-select {
            border: 2px solid var(--shadow-color); border-radius: 10px;
            padding: 8px 12px; font-family: inherit; font-weight: 600;
            font-size: 0.85rem; color: var(--text-color); background: #fff;
            transition: all 0.15s;
        }
        .form-control:focus, .form-select:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }

        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 22px; }
        .stat-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            padding: 16px 20px;
            display: flex; align-items: center; gap: 14px;
        }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 12px;
            background: var(--primary); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
            border: 2px solid var(--shadow-color);
        }
        .stat-icon.success { background: var(--success); }
        .stat-icon.info    { background: var(--info); }
        .stat-icon.warning { background: var(--warning); color: #000; }
        .stat-info { min-width: 0; }
        .stat-value { font-size: 1.6rem; font-weight: 900; line-height: 1; }
        .stat-label { font-size: 0.72rem; font-weight: 700; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.4px; margin-top: 4px; }

        .table-neo { width: 100% !important; border-collapse: separate !important; border-spacing: 0; }
        .table-neo thead th {
            background: #f8f9fb !important; color: var(--text-color) !important;
            font-size: 0.72rem !important; font-weight: 900 !important;
            text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 3px solid var(--shadow-color) !important;
            padding: 12px 10px !important; white-space: nowrap;
        }
        .table-neo tbody td {
            padding: 10px !important; font-size: 0.85rem;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb !important;
        }
        .table-neo tbody tr:hover { background: #fafbfc; }
        .table-neo tbody tr:last-child td { border-bottom: none !important; }

        .badge-qtd {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 28px; height: 24px; padding: 0 8px;
            background: var(--primary); color: #fff;
            border-radius: 8px; font-weight: 800; font-size: 0.72rem;
            border: 1.5px solid var(--shadow-color);
        }
        .badge-yes { background: var(--success); color: #fff; padding: 3px 10px; border-radius: 8px; font-weight: 800; font-size: 0.7rem; }
        .badge-no  { background: #d1d5db; color: #374151; padding: 3px 10px; border-radius: 8px; font-weight: 800; font-size: 0.7rem; }

        .cell-truncate { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .btn-view-memo {
            background: var(--primary); color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            border-radius: 8px; padding: 5px 12px;
            font-weight: 800; font-size: 0.75rem;
            cursor: pointer; transition: all 0.15s;
            display: inline-flex; align-items: center; gap: 5px;
            font-family: inherit; white-space: nowrap;
        }
        .btn-view-memo:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            background: var(--primary-dark); color: #fff;
        }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--shadow-color) !important;
            border-radius: 8px !important;
            padding: 4px 10px !important;
            font-family: inherit !important;
            font-weight: 600 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: #fff !important;
            border: 2px solid var(--shadow-color) !important;
            border-radius: 8px !important;
            font-weight: 800 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 2px solid transparent !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f1f3f5 !important;
            border: 2px solid var(--shadow-color) !important;
            color: var(--text-color) !important;
        }
        .dataTables_wrapper .dataTables_info { font-size: 0.8rem; font-weight: 600; }

        #memoModal .modal-content {
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
        }
        #memoModal .modal-header {
            background: var(--primary); color: #fff;
            border-bottom: 3px solid var(--shadow-color);
            padding: 14px 22px;
        }
        #memoModal .modal-title { font-weight: 900; font-size: 1rem; }
        #memoModal .btn-close { filter: invert(1) brightness(2); }
        #memoModal .modal-body { padding: 22px; }
        #memoModal .modal-footer { border-top: 2px solid #e5e7eb; padding: 14px 22px; background: #f8f9fb; }
        .memo-pre {
            background: #1a1a2e; color: #e5e7eb;
            padding: 20px; border-radius: 12px;
            font-family: 'Courier New', Consolas, monospace;
            font-size: 0.82rem; line-height: 1.55;
            white-space: pre-wrap; word-break: break-word;
            max-height: 65vh; overflow-y: auto;
            margin: 0; border: 2px solid var(--shadow-color);
        }
        .memo-meta { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; font-size: 0.82rem; }
        .memo-meta .chip {
            background: #f1f3f5; border-radius: 8px;
            padding: 6px 12px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 6px;
            border: 1.5px solid var(--shadow-color);
        }
        .memo-meta .chip i { color: var(--primary); }

        .empty-state { text-align: center; padding: 48px 20px; color: #9ca3af; font-weight: 600; }
        .empty-state i { font-size: 3rem; opacity: 0.5; display: block; margin-bottom: 12px; }

        @media (max-width: 768px) {
            .header-inner { padding: 0 12px; }
            .logo-img { height: 44px; max-width: 110px; }
            .header-title h1 { font-size: 0.98rem; }
            .header-title p  { font-size: 0.7rem; }
            .container-neo { padding: 0 12px; }
            .section-body { padding: 14px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .stat-value { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<header class="app-header">
    <div class="header-inner">
        <div class="header-brand">
            <img src="img/logosunny.jpg" alt="Logo" class="logo-img">
            <div class="header-title">
                <h1><i class="fas fa-history me-2"></i> Log de Atualizações</h1>
                <p>Auditoria das características dos empreendimentos</p>
            </div>
        </div>
        <div class="header-meta">
            <span class="meta-chip">
                <i class="fas fa-user"></i>
                <?php echo htmlspecialchars($_SESSION['Usuario']); ?>
            </span>
            <a href="javascript:history.back()" class="btn-neo btn-primary-neo">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</header>

<div class="container-neo">

    <?php if ($erroLog): ?>
        <div class="alert alert-danger" style="border:2px solid var(--danger); border-radius:12px; font-weight:700;">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($erroLog); ?>
        </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($totalRegistros, 0, ',', '.'); ?></div>
                <div class="stat-label">Registros no filtro</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success"><i class="fas fa-edit"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($totalCampos, 0, ',', '.'); ?></div>
                <div class="stat-label">Campos alterados</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:1.05rem;">
                    <?php echo $ultimoLog ? date('d/m/Y H:i', strtotime($ultimoLog)) : '—'; ?>
                </div>
                <div class="stat-label">Última atualização</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?php echo count($usuarios); ?></div>
                <div class="stat-label">Usuários com registro</div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-num"><i class="fas fa-filter"></i></div>
            <div class="section-title">
                <h2>Filtros</h2>
                <p>Refine a consulta do log</p>
            </div>
        </div>
        <div class="section-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="field-label" for="fEmp"><i class="fas fa-building"></i> Empreendimento</label>
                    <select class="form-select" id="fEmp" name="emp">
                        <option value="0">— Todos —</option>
                        <?php foreach ($empreendimentos as $e): ?>
                            <option value="<?php echo (int)$e['Empreend_ID']; ?>" <?php echo $filtroEmp == $e['Empreend_ID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($e['NomeEmpreendimento']); ?>
                                (#<?php echo (int)$e['Empreend_ID']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="field-label" for="fUser"><i class="fas fa-user"></i> Usuário</label>
                    <select class="form-select" id="fUser" name="user">
                        <option value="">— Todos —</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $filtroUser === $u ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="field-label" for="fDe"><i class="fas fa-calendar-alt"></i> De</label>
                    <input type="date" class="form-control" id="fDe" name="de" value="<?php echo htmlspecialchars($filtroDe); ?>">
                </div>
                <div class="col-md-2">
                    <label class="field-label" for="fAte"><i class="fas fa-calendar-alt"></i> Até</label>
                    <input type="date" class="form-control" id="fAte" name="ate" value="<?php echo htmlspecialchars($filtroAte); ?>">
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn-neo btn-primary-neo flex-fill" title="Aplicar filtros">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="col-12">
                    <a href="log_caracteristicas_view.php" class="text-decoration-none" style="font-size:0.78rem; font-weight:700; color:var(--primary);">
                        <i class="fas fa-eraser me-1"></i> Limpar filtros
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- TABELA -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-num"><i class="fas fa-list"></i></div>
            <div class="section-title">
                <h2>Registros</h2>
                <p><?php echo $totalRegistros; ?> registro(s) encontrado(s)</p>
            </div>
        </div>
        <div class="section-body">

            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    Nenhum registro encontrado com os filtros atuais.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tableLog" class="table table-neo" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:60px;">ID</th>
                                <th>Data/Hora</th>
                                <th>Empreendimento</th>
                                <th>Empresa</th>
                                <th>Usuário</th>
                                <th>IP</th>
                                <th class="text-center">Campos</th>
                                <th class="text-center">E-mail</th>
                                <th class="text-center" style="width:100px;">Memo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <span style="font-weight:800; color:var(--primary);">#<?php echo (int)$log['Log_Id']; ?></span>
                                    </td>
                                    <td data-order="<?php echo htmlspecialchars($log['DataHora']); ?>" style="white-space:nowrap; font-weight:700;">
                                        <i class="fas fa-clock" style="color:var(--primary); font-size:0.75rem;"></i>
                                        <?php echo date('d/m/Y H:i:s', strtotime($log['DataHora'])); ?>
                                    </td>
                                    <td>
                                        <div class="cell-truncate" title="<?php echo htmlspecialchars($log['NomeEmpreendimento']); ?>">
                                            <strong><?php echo htmlspecialchars($log['NomeEmpreendimento']); ?></strong>
                                            <small class="text-muted"> #<?php echo (int)$log['Empreend_ID']; ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-truncate" title="<?php echo htmlspecialchars($log['Empresa'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($log['Empresa'] ?: '—'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <i class="fas fa-user-circle me-1" style="color:var(--primary);"></i>
                                        <?php echo htmlspecialchars($log['Usuario']); ?>
                                    </td>
                                    <td style="font-family:monospace; font-size:0.78rem; opacity:0.75;">
                                        <?php echo htmlspecialchars($log['IP'] ?? '—'); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-qtd"><?php echo (int)$log['QtdAlteracoes']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$log['EmailEnviado']): ?>
                                            <span class="badge-yes"><i class="fas fa-check me-1"></i>Sim</span>
                                        <?php else: ?>
                                            <span class="badge-no"><i class="fas fa-times me-1"></i>Não</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button"
                                                class="btn-view-memo"
                                                data-memo="<?php echo htmlspecialchars($log['Memo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-logid="<?php echo (int)$log['Log_Id']; ?>"
                                                data-data="<?php echo date('d/m/Y H:i:s', strtotime($log['DataHora'])); ?>"
                                                data-emp="<?php echo htmlspecialchars($log['NomeEmpreendimento'], ENT_QUOTES); ?>"
                                                data-user="<?php echo htmlspecialchars($log['Usuario'], ENT_QUOTES); ?>"
                                                data-ip="<?php echo htmlspecialchars($log['IP'] ?? '—', ENT_QUOTES); ?>"
                                                data-qtd="<?php echo (int)$log['QtdAlteracoes']; ?>">
                                            <i class="fas fa-file-alt"></i> Ver
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<!-- MODAL MEMO -->
<div class="modal fade" id="memoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt me-2"></i>
                    Memo do registro <span id="memoLogId"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="memo-meta">
                    <span class="chip"><i class="fas fa-clock"></i> <span id="memoData"></span></span>
                    <span class="chip"><i class="fas fa-building"></i> <span id="memoEmp"></span></span>
                    <span class="chip"><i class="fas fa-user"></i> <span id="memoUser"></span></span>
                    <span class="chip"><i class="fas fa-network-wired"></i> <span id="memoIp"></span></span>
                    <span class="chip"><i class="fas fa-edit"></i> <span id="memoQtd"></span> campo(s)</span>
                </div>
                <pre class="memo-pre" id="memoContent">—</pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-neo btn-primary-neo" id="btnCopyMemo">
                    <i class="fas fa-copy"></i> Copiar
                </button>
                <button type="button" class="btn-neo btn-danger-neo" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    if ($('#tableLog').length === 0) return;

    $('#tableLog').DataTable({
        language: {
            emptyTable:     "Nenhum registro encontrado",
            info:           "Mostrando _START_ a _END_ de _TOTAL_ registros",
            infoEmpty:      "Mostrando 0 a 0 de 0 registros",
            infoFiltered:   "(filtrado de _MAX_ registros no total)",
            lengthMenu:     "Mostrar _MENU_ registros",
            loadingRecords: "Carregando...",
            processing:     "Processando...",
            search:         "Buscar:",
            zeroRecords:    "Nenhum registro correspondente encontrado",
            paginate: {
                first:    "Primeiro",
                last:     "Último",
                next:     "Próximo",
                previous: "Anterior"
            }
        },
        order: [[1, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        columnDefs: [
            { orderable: false, targets: [8] },
            { className: 'align-middle', targets: '_all' }
        ],
        stateSave: true
    });

    const memoModalEl = document.getElementById('memoModal');
    const memoModal   = new bootstrap.Modal(memoModalEl);
    let currentMemo   = '';

    $('#tableLog').on('click', '.btn-view-memo', function () {
        const btn = this;

        document.getElementById('memoLogId').textContent = '#' + btn.dataset.logid;
        document.getElementById('memoData').textContent  = btn.dataset.data;
        document.getElementById('memoEmp').textContent   = btn.dataset.emp;
        document.getElementById('memoUser').textContent  = btn.dataset.user;
        document.getElementById('memoIp').textContent    = btn.dataset.ip;
        document.getElementById('memoQtd').textContent   = btn.dataset.qtd;

        currentMemo = btn.dataset.memo || '';
        document.getElementById('memoContent').textContent = currentMemo;

        memoModal.show();
    });

    document.getElementById('btnCopyMemo').addEventListener('click', function () {
        const btn = this;
        if (!currentMemo) return;

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(currentMemo).then(function () {
                btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                setTimeout(function () { btn.innerHTML = '<i class="fas fa-copy"></i> Copiar'; }, 1800);
            });
        } else {
            const ta = document.createElement('textarea');
            ta.value = currentMemo;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                setTimeout(function () { btn.innerHTML = '<i class="fas fa-copy"></i> Copiar'; }, 1800);
            } catch (e) {
                alert('Não foi possível copiar automaticamente.');
            }
            document.body.removeChild(ta);
        }
    });
});
</script>
</body>
</html>