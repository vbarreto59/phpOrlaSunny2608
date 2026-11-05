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

// ============================================================
// USUÁRIO LOGADO
// ============================================================
$usuarioLogado    = $_SESSION['Usuario'] ?? '';
$ehBarreto        = strtolower($usuarioLogado) === 'barreto';
$csrf             = $_SESSION['csrf_usr_list'] ?? ($_SESSION['csrf_usr_list'] = bin2hex(random_bytes(32)));
$podeVerSenha     = $ehBarreto;

// ============================================================
// QUERY OTIMIZADA — N+1 RESOLVIDO
// ============================================================
$usuarios = [];
$erro = null;

try {
    // GROUP_CONCAT traz todos os grupos numa única query
    // JOIN com sunnyempresas usando alias para evitar colisão
    $sql = "
        SELECT 
            u.*,
            e.EmpresaSunny      AS EmpresaSunnyAlias,
            e.Conf_Versao       AS EmpresaVersao,
            (
                SELECT GROUP_CONCAT(g.Nome_Grupo ORDER BY g.Nome_Grupo SEPARATOR '||')
                FROM grupo g
                INNER JOIN usuario_grupo ug ON g.ID_Grupo = ug.ID_Grupo
                WHERE ug.UserId = u.UserId
            ) AS GruposStr
        FROM usuarios u
        LEFT JOIN sunnyempresas e ON u.IdEmp = e.IdEmp
        ORDER BY u.Usuario ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erro = $e->getMessage();
    error_log("[USR_LIST] " . $e->getMessage());
}

// ============================================================
// CONTAGEM POR GRUPO
// ============================================================
$contagem_grupos = [];
try {
    $stmt = $db->prepare("
        SELECT 
            g.Nome_Grupo,
            COUNT(DISTINCT ug.UserId) AS total_usuarios,
            COUNT(DISTINCT CASE WHEN u.Ativo = 1 THEN ug.UserId END) AS ativos,
            COUNT(DISTINCT CASE WHEN u.Ativo = 0 THEN ug.UserId END) AS inativos
        FROM grupo g
        INNER JOIN usuario_grupo ug ON g.ID_Grupo = ug.ID_Grupo
        INNER JOIN usuarios u       ON ug.UserId = u.UserId
        GROUP BY g.ID_Grupo, g.Nome_Grupo
        ORDER BY g.Nome_Grupo
    ");
    $stmt->execute();
    $contagem_grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("[USR_LIST_GRUPOS] " . $e->getMessage());
}

// ============================================================
// ESTATÍSTICAS
// ============================================================
$total_usuarios    = count($usuarios);
$usuarios_ativos   = 0;
$usuarios_inativos = 0;

foreach ($usuarios as $u) {
    if ((int)($u['Ativo'] ?? 0) === 1) $usuarios_ativos++;
    else $usuarios_inativos++;
}

// ============================================================
// HELPERS
// ============================================================
function permissoesBadge($permissao) {
    return match((int)$permissao) {
        1 => ['class' => 'perm-superadmin', 'label' => 'SUPERADMIN'],
        2 => ['class' => 'perm-admin',      'label' => 'ADMIN'],
        3 => ['class' => 'perm-gestor',     'label' => 'GESTOR-EDITOR'],
        4 => ['class' => 'perm-editor',     'label' => 'EDITOR'],
        5 => ['class' => 'perm-corretor',   'label' => 'CORRETOR'],
        6 => ['class' => 'perm-corretor',   'label' => 'CORRETOR-EDITOR'],
        7 => ['class' => 'perm-visu',       'label' => 'VISUALIZADOR'],
        default => ['class' => 'perm-default', 'label' => '—'],
    };
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários · SunnyImob</title>

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

        /* GRUPOS STATS */
        .grupos-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 16px 18px;
            margin-bottom: 22px;
        }
        .grupos-card h6 {
            font-size: 0.82rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .grupos-card h6 i { color: var(--primary); }
        .grupos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 10px;
        }
        .grupo-stat-item {
            background: #f8f9fb;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 12px;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .grupo-stat-item strong {
            display: block;
            font-size: 0.82rem;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .grupo-stat-meta {
            display: flex;
            gap: 8px;
            font-size: 0.72rem;
            font-weight: 700;
            flex-wrap: wrap;
        }
        .grupo-stat-meta .g-ativos   { color: var(--success); }
        .grupo-stat-meta .g-inativos { color: var(--danger); }
        .grupo-stat-meta .g-total    { color: var(--text-color); opacity: 0.6; }

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
            max-width: 500px;
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
        table.dataTable.table-neo tbody tr.row-inativo td { opacity: 0.6; }

        /* Célula usuário */
        .user-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .user-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            background: var(--primary);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 0.9rem;
            text-transform: uppercase;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { min-width: 0; }
        .user-nome {
            font-weight: 800;
            font-size: 0.86rem;
            color: var(--text-color);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .user-real {
            font-size: 0.74rem;
            color: var(--text-color);
            opacity: 0.65;
            font-weight: 500;
            margin-top: 1px;
        }
        .user-meta {
            font-size: 0.7rem;
            color: var(--text-color);
            opacity: 0.55;
            font-weight: 600;
            margin-top: 2px;
            display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
        }
        .user-meta i { font-size: 0.65rem; }

        /* Badge status */
        .badge-status {
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
        .badge-status.on  { background: var(--success); color: #fff; }
        .badge-status.off { background: rgba(0,0,0,0.1); color: var(--text-color); box-shadow: none; }

        /* Badge permissão */
        .badge-perm {
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
            white-space: nowrap;
        }
        .perm-superadmin { background: var(--danger);  color: #fff; }
        .perm-admin      { background: var(--warning); color: #000; }
        .perm-gestor     { background: var(--info);    color: #fff; }
        .perm-editor     { background: var(--primary); color: #fff; }
        .perm-corretor   { background: #6b7280;        color: #fff; }
        .perm-visu       { background: var(--success); color: #fff; }
        .perm-default    { background: rgba(0,0,0,0.1); color: var(--text-color); box-shadow: none; }

        /* Badge grupo */
        .badge-grupo {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            background: var(--info);
            color: #fff;
            border-radius: 12px;
            font-size: 0.66rem;
            font-weight: 800;
            margin-right: 3px;
            margin-bottom: 3px;
        }

        /* Senha toggle */
        .senha-wrap {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(0,0,0,0.06);
            padding: 2px 8px;
            border-radius: 6px;
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .senha-toggle {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary);
            padding: 0 2px;
            font-size: 0.7rem;
        }

        /* Botões de ação */
        .actions-group {
            display: inline-flex;
            gap: 4px;
        }
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
        }
        .btn-icon:hover { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 0 var(--shadow-color); }
        .btn-icon.edit   { background: var(--warning); color: #000; }
        .btn-icon.mail   { background: var(--info);    color: #fff; }
        .btn-icon.delete { background: var(--danger);  color: #fff; }

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
            background: var(--danger); color: #fff;
        }

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
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .toolbar-left, .toolbar-right { width: 100%; }
            .search-box { max-width: 100%; flex-basis: 100%; }
            .grupos-grid { grid-template-columns: 1fr; }
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
                <h1><i class="fas fa-users me-2"></i> Usuários</h1>
                <p>Gestão de acessos · <?= $total_usuarios ?> registros</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="usr_novo.php" target="_blank" class="btn-header primary">
                <i class="fas fa-user-plus"></i> Novo
            </a>
            <a href="usr_grupo_list.php" target="_blank" class="btn-header">
                <i class="fas fa-users-cog"></i> Grupos
            </a>
            <a href="#" onclick="window.close(); return false;" class="btn-header danger">
                <i class="fas fa-times"></i> Fechar
            </a>
        </div>
    </div>
</header>

<div class="container-neo">

    <?php if ($erro): ?>
        <div class="alert-neo">
            <i class="fas fa-exclamation-circle"></i>
            <div>Erro ao carregar: <?= htmlspecialchars($erro) ?></div>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon i-primary"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $total_usuarios ?></div>
                <div class="stat-lbl">Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-success"><i class="fas fa-user-check"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $usuarios_ativos ?></div>
                <div class="stat-lbl">Ativos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-danger"><i class="fas fa-user-slash"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $usuarios_inativos ?></div>
                <div class="stat-lbl">Inativos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-info"><i class="fas fa-layer-group"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= count($contagem_grupos) ?></div>
                <div class="stat-lbl">Grupos</div>
            </div>
        </div>
    </div>

    <!-- Grupos stats -->
    <?php if (!empty($contagem_grupos)): ?>
        <div class="grupos-card">
            <h6><i class="fas fa-chart-bar"></i> Distribuição por Grupo</h6>
            <div class="grupos-grid">
                <?php foreach ($contagem_grupos as $g): ?>
                    <div class="grupo-stat-item">
                        <strong><?= htmlspecialchars($g['Nome_Grupo']) ?></strong>
                        <div class="grupo-stat-meta">
                            <span class="g-ativos">✓ <?= (int)$g['ativos'] ?></span>
                            <span class="g-inativos">✗ <?= (int)$g['inativos'] ?></span>
                            <span class="g-total">∑ <?= (int)$g['total_usuarios'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar usuário, nome, e-mail, grupo…" autocomplete="off">
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
    </div>

    <!-- Tabela -->
    <div class="table-card">
        <table id="tabelaUsuarios" class="table table-neo" style="width:100%">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Usuário</th>
                    <th style="width: 150px;">Empresa</th>
                    <th style="width: 110px;">Status</th>
                    <th style="width: 150px;">Função</th>
                    <th style="width: 200px;">Grupos</th>
                    <th style="width: 130px;">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($usuarios) && !$erro): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px 20px; opacity:0.6; font-weight:700; font-style:italic;">
                        <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                        Nenhum usuário cadastrado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($usuarios as $u):
                    $uid      = (int)($u['UserId'] ?? 0);
                    $ativo    = (int)($u['Ativo'] ?? 0) === 1;
                    $usuario  = $u['Usuario'] ?? '—';
                    $nome     = $u['Nome'] ?? '';
                    $empresa  = $u['Empresa'] ?? ($u['EmpresaSunnyAlias'] ?? '—');
                    $funcao   = $u['Funcao'] ?? '—';
                    $permissao = $u['Permissao'] ?? 0;
                    $email    = $u['Email'] ?? '';
                    $telefone = $u['Telefones'] ?? '';
                    $creci    = $u['CRECI'] ?? '';
                    $senha    = $u['SenhaAtual'] ?? '';

                    // Data com fallback (várias nomenclaturas possíveis)
                    $dataCadastro = $u['DataHoraCadastro']
                                 ?? $u['DataCadastro']
                                 ?? $u['Data']
                                 ?? null;
                    $dataFmt = $dataCadastro
                             ? date('d/m/Y', strtotime($dataCadastro))
                             : '—';

                    // Grupos via GROUP_CONCAT
                    $grupos = !empty($u['GruposStr'])
                            ? explode('||', $u['GruposStr'])
                            : [];

                    // Foto
                    $fotoPath = __DIR__ . "/fotos/" . $uid . ".jpg";
                    $hasFoto  = $uid > 0 && file_exists($fotoPath);
                    $initial  = strtoupper(mb_substr($usuario, 0, 1));

                    // Badge
                    $perm = permissoesBadge($permissao);
                ?>
                    <tr class="<?= $ativo ? '' : 'row-inativo' ?>"
                        data-status="<?= $ativo ? '1' : '0' ?>">
                        <td>
                            <span style="font-family:'SFMono-Regular',Consolas,monospace;font-size:0.74rem;background:rgba(0,0,0,0.06);padding:3px 8px;border-radius:6px;font-weight:800;">
                                #<?= str_pad($uid, 3, '0', STR_PAD_LEFT) ?>
                            </span>
                        </td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">
                                    <?php if ($hasFoto): ?>
                                        <img src="fotos/<?= $uid ?>.jpg" alt="">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initial) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="user-info">
                                    <div class="user-nome"><?= htmlspecialchars(strtoupper($usuario)) ?></div>
                                    <?php if ($nome): ?>
                                        <div class="user-real"><?= htmlspecialchars($nome) ?></div>
                                    <?php endif; ?>
                                    <div class="user-meta">
                                        <?php if ($email): ?>
                                            <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($email) ?></span>
                                        <?php endif; ?>
                                        <?php if ($telefone): ?>
                                            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($telefone) ?></span>
                                        <?php endif; ?>
                                        <?php if ($creci): ?>
                                            <span><i class="fas fa-id-badge"></i> <?= htmlspecialchars($creci) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-meta">
                                        <i class="fas fa-calendar-alt"></i> <?= $dataFmt ?>
                                        <?php if ($podeVerSenha && $senha): ?>
                                            ·
                                            <span class="senha-wrap">
                                                <span class="senha-text" data-target="senha_<?= $uid ?>">••••••••</span>
                                                <span class="senha-real" id="senha_<?= $uid ?>" style="display:none;"><?= htmlspecialchars($senha) ?></span>
                                                <button type="button" class="senha-toggle" onclick="toggleSenha('senha_<?= $uid ?>', this)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($empresa): ?>
                                <span style="font-size:0.78rem;font-weight:700;"><?= htmlspecialchars($empresa) ?></span>
                            <?php else: ?>
                                <span style="opacity:0.4;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-status <?= $ativo ? 'on' : 'off' ?>">
                                <i class="fas fa-<?= $ativo ? 'check-circle' : 'ban' ?>"></i>
                                <?= $ativo ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-perm <?= $perm['class'] ?>">
                                <i class="fas fa-shield-alt"></i> <?= htmlspecialchars($perm['label']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($grupos)): ?>
                                <?php foreach ($grupos as $gNome): ?>
                                    <span class="badge-grupo"><?= htmlspecialchars($gNome) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="opacity:0.4;font-size:0.76rem;">Nenhum</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions-group">
                                <a href="usr_edit.php?id=<?= $uid ?>"
                                   target="_blank"
                                   class="btn-icon edit" title="Editar">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <button type="button"
                                        class="btn-icon mail"
                                        title="Enviar credenciais"
                                        onclick="confirmarEnvio(<?= $uid ?>)">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                <button type="button"
                                        class="btn-icon delete"
                                        title="Excluir"
                                        onclick="confirmarExclusao(<?= $uid ?>, <?= json_encode($usuario) ?>)">
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
<!-- FORM OCULTO PARA EXCLUSÃO (POST com CSRF)                   -->
<!-- ============================================================ -->
<form method="POST" action="usr_excluir.php" id="formDelete" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="id" id="deleteId" value="">
</form>

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
    if (document.getElementById('tabelaUsuarios')) {
        table = new DataTable('#tabelaUsuarios', {
            language: ptBR,
            pageLength: 100,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Todos"]],
            order: [[0, 'desc']],
            autoWidth: false,
            columnDefs: [
                { orderable: false, targets: [6] },
                { searchable: false, targets: [6] }
            ]
        });

        // Filtro: só ativos
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'tabelaUsuarios') return true;
            const $f = document.getElementById('filterAtivo');
            if (!$f?.checked) return true;
            const tr = settings.aoData[dataIndex].nTr;
            return tr.dataset.status === '1';
        });

        document.getElementById('filterAtivo')?.addEventListener('change', function () {
            table.draw();
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
    // TOGGLE SENHA
    // ============================================================
    window.toggleSenha = function (id, btn) {
        const $span = document.getElementById(id);
        const $mask = $span.previousElementSibling;
        const $icon = btn.querySelector('i');

        if ($span.style.display === 'none') {
            $span.style.display = 'inline';
            $mask.style.display = 'none';
            $icon.classList.remove('fa-eye');
            $icon.classList.add('fa-eye-slash');
        } else {
            $span.style.display = 'none';
            $mask.style.display = 'inline';
            $icon.classList.remove('fa-eye-slash');
            $icon.classList.add('fa-eye');
        }
    };

    // ============================================================
    // ENVIAR CREDENCIAIS
    // ============================================================
    window.confirmarEnvio = function (id) {
        Swal.fire({
            title: 'Enviar credenciais?',
            text: 'Um e-mail será enviado ao usuário com os dados de acesso.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '<?= $themeSafe['primary'] ?>',
            cancelButtonColor: '#6b7280'
        }).then(r => {
            if (r.isConfirmed) {
                window.open('usr_email_credencial.php?UserID=' + encodeURIComponent(id), '_blank');
            }
        });
    };

    // ============================================================
    // EXCLUSÃO
    // ============================================================
    window.confirmarExclusao = function (id, nome) {
        Swal.fire({
            title: 'Excluir usuário?',
            html: `O usuário <b>${nome}</b> será removido <b>permanentemente</b>.<br>` +
                  `<span style="color:#ef4444;font-size:0.85rem;font-weight:700;">Esta ação não pode ser desfeita.</span>`,
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

})();
</script>
</body>
</html>