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
// CONTEXTO
// ============================================================
$IdEmp = (int)($_SESSION['IdEmp'] ?? 2);  // mantém comportamento original como fallback
$usuarioLogado = $_SESSION['Usuario'] ?? 'sistema';

// ============================================================
// CSRF
// ============================================================
if (empty($_SESSION['csrf_usr_admin'])) {
    $_SESSION['csrf_usr_admin'] = bin2hex(random_bytes(32));
}

// ============================================================
// PROCESSAMENTO POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_usr_admin'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['MsgErro'] = "Falha na verificação de segurança. Recarregue a página.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $acao   = $_POST['acao']   ?? '';
    $userId = (int)($_POST['User_Id'] ?? 0);

    if ($userId > 0) {
        try {
            if ($acao === 'toggle_status') {
                $novoStatus = (int)($_POST['novo_status'] ?? 0);
                $stmt = $db->prepare("UPDATE usuarios SET Ativo = ? WHERE UserID = ? AND IdEmp = ?");
                $stmt->execute([$novoStatus, $userId, $IdEmp]);
                $_SESSION['MsgSucesso'] = $novoStatus
                    ? "Usuário ativado com sucesso."
                    : "Usuário inativado.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            if ($acao === 'excluir') {
                $db->beginTransaction();
                $db->prepare("DELETE FROM Usuario_Grupo WHERE UserId = ?")->execute([$userId]);
                $db->prepare("DELETE FROM usuarios WHERE UserID = ? AND IdEmp = ?")->execute([$userId, $IdEmp]);
                $db->commit();
                $_SESSION['MsgSucesso'] = "Usuário excluído permanentemente.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("[USR_ADMIN] " . $e->getMessage()); // log seguro no servidor
            $_SESSION['MsgErro'] = "Erro ao processar: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// ============================================================
// CARREGAR DADOS
// ============================================================
$usuarios = [];
$erro = null;

try {
    // LEFT JOIN para nomes de diretoria/gerência (se as tabelas existirem)
    // Se não existirem, o catch cuida e cai no fallback
    $sql = "
        SELECT u.*,
               (SELECT d.NomeDiretoria FROM diretorias d WHERE d.UserId = u.UserId LIMIT 1) AS DiretoriaNome,
               (SELECT g.NomeGerencia  FROM gerencias  g WHERE g.UserId = u.UserId LIMIT 1) AS GerenciaNome
        FROM usuarios u
        WHERE u.IdEmp = ?
        ORDER BY u.Usuario ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$IdEmp]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback sem JOIN (se tabelas auxiliares não existirem)
    try {
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE IdEmp = ? ORDER BY Usuario ASC");
        $stmt->execute([$IdEmp]);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $erro = "Erro ao carregar usuários: " . $e2->getMessage();
        error_log("[USR_ADMIN] " . $e2->getMessage());
    }
}

// Estatísticas
$stats = [
    'total'      => count($usuarios),
    'ativos'     => 0,
    'inativos'   => 0,
    'semDir'     => 0,
];
foreach ($usuarios as $u) {
    $ativo = !empty($u['Ativo']);
    if ($ativo) {
        $stats['ativos']++;
        if (empty($u['DiretoriaID']) && empty($u['DiretoriaNome'])) {
            $stats['semDir']++;
        }
    } else {
        $stats['inativos']++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin · Gerenciar Usuários</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
        .btn-header.primary { background: var(--primary-dark); }

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
        .stat-icon.i-warning  { background: var(--warning); color: #000; }
        .stat-info { min-width: 0; }
        .stat-num { font-size: 1.5rem; font-weight: 900; line-height: 1; letter-spacing: -0.8px; }
        .stat-lbl {
            font-size: 0.64rem; text-transform: uppercase;
            letter-spacing: 1px; font-weight: 700;
            opacity: 0.6; margin-top: 4px;
        }

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
            max-width: 460px;
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

        /* BOTÕES */
        .btn-neo {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 10px 18px;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            text-decoration: none;
        }
        .btn-neo:hover { transform: translate(-2px, -2px); box-shadow: 5px 5px 0 0 var(--shadow-color); }
        .btn-neo:active { transform: translate(1px, 1px); box-shadow: 1px 1px 0 0 var(--shadow-color); }
        .btn-neo-primary { background: var(--btn-bg); color: var(--btn-text); }
        .btn-neo-primary:hover { background: var(--btn-hover); color: #fff; }

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
            background: #fff;
            color: var(--text-color);
        }
        .btn-icon:hover { transform: translate(-1px, -1px); box-shadow: 3px 3px 0 0 var(--shadow-color); color: var(--text-color); }
        .btn-icon.edit      { background: var(--warning); color: #000; }
        .btn-icon.activate  { background: var(--success); color: #fff; }
        .btn-icon.deactivate{ background: var(--warning); color: #000; }
        .btn-icon.delete    { background: var(--danger);  color: #fff; }

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
            font-size: 0.72rem;
            letter-spacing: 1px;
            padding: 14px 12px !important;
            border: none !important;
            border-bottom: 3px solid var(--shadow-color) !important;
        }
        table.dataTable.table-neo thead th:first-child { border-top-left-radius: 10px; }
        table.dataTable.table-neo thead th:last-child  { border-top-right-radius: 10px; }
        table.dataTable.table-neo tbody td {
            padding: 10px 12px !important;
            font-size: 13px;
            border: none !important;
            border-bottom: 1px solid rgba(0,0,0,0.07) !important;
            vertical-align: middle !important;
        }
        table.dataTable.table-neo tbody tr:last-child td { border-bottom: none !important; }
        table.dataTable.table-neo tbody tr:hover td { background: rgba(0,0,0,0.025); }
        table.dataTable.table-neo tbody tr.row-inativo td { opacity: 0.65; }

        /* Célula usuário */
        .user-cell { display: flex; align-items: center; gap: 10px; }
        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            background: var(--primary);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 0.85rem;
            text-transform: uppercase;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-name { font-weight: 800; font-size: 0.85rem; }
        .user-id   { font-size: 0.68rem; opacity: 0.55; font-family: 'SFMono-Regular', Consolas, monospace; }

        /* Badges */
        .badge-status {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px;
            font-size: 0.68rem; font-weight: 800; letter-spacing: 0.4px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .badge-status.on  { background: var(--success); color: #fff; }
        .badge-status.off { background: rgba(0,0,0,0.1); color: var(--text-color); box-shadow: none; }
        .badge-funcao {
            display: inline-block;
            background: rgba(0,0,0,0.06);
            padding: 3px 10px;
            border-radius: 6px;
            font-weight: 700; font-size: 0.72rem;
            color: var(--text-color);
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
            padding: 40px 20px !important;
            font-weight: 700 !important;
            opacity: 0.6 !important;
            font-style: italic;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: var(--card-bg);
            border: 3px dashed var(--shadow-color);
            border-radius: 16px;
            color: var(--text-color);
        }
        .empty-state i { font-size: 3rem; color: var(--primary); opacity: 0.4; display: block; margin-bottom: 12px; }
        .empty-state h3 { font-weight: 900; margin: 0 0 6px; }
        .empty-state p { opacity: 0.65; font-weight: 500; margin: 0 0 18px; }

        /* Alertas */
        .alert-neo {
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            padding: 14px 18px;
            font-weight: 700; font-size: 0.88rem;
            margin-bottom: 18px;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .alert-neo i { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
        .alert-neo.success { background: var(--success); color: #fff; }
        .alert-neo.danger  { background: var(--danger);  color: #fff; }

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
            .btn-neo { flex: 1; justify-content: center; }
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
                <h1><i class="fas fa-users-cog me-2"></i> Gerenciar Usuários</h1>
                <p>Administração · Empresa #<?= $IdEmp ?></p>
            </div>
        </div>
        <div class="header-actions">
            <a href="usr_admin_novo_usuario3.php" class="btn-header primary">
                <i class="fas fa-plus"></i> Novo Usuário
            </a>
            <a href="#" onclick="window.close(); return false;" class="btn-header danger">
                <i class="fas fa-times"></i> Fechar
            </a>
        </div>
    </div>
</header>

<div class="container-neo">

    <!-- Flash messages -->
    <?php if (isset($_SESSION['MsgSucesso'])): ?>
        <div class="alert-neo success">
            <i class="fas fa-check-circle"></i>
            <div><?= $_SESSION['MsgSucesso']; unset($_SESSION['MsgSucesso']); ?></div>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['MsgErro'])): ?>
        <div class="alert-neo danger">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= $_SESSION['MsgErro']; unset($_SESSION['MsgErro']); ?></div>
        </div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert-neo danger">
            <i class="fas fa-exclamation-triangle"></i>
            <div><?= htmlspecialchars($erro) ?></div>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon i-primary"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['total'] ?></div>
                <div class="stat-lbl">Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-success"><i class="fas fa-user-check"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['ativos'] ?></div>
                <div class="stat-lbl">Ativos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-danger"><i class="fas fa-user-slash"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['inativos'] ?></div>
                <div class="stat-lbl">Inativos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-warning"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['semDir'] ?></div>
                <div class="stat-lbl">Sem Diretoria</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar usuário, função…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <label class="neo-switch" title="Mostrar apenas usuários ativos">
                <input type="checkbox" id="filterAtivo">
                <span class="switch-track"></span>
                <span><i class="fas fa-user-check"></i> Só ativos</span>
            </label>
            <label class="neo-switch" title="Mostrar apenas usuários inativos">
                <input type="checkbox" id="filterInativo">
                <span class="switch-track"></span>
                <span><i class="fas fa-user-slash"></i> Só inativos</span>
            </label>
        </div>
    </div>

    <!-- Tabela -->
    <?php if ($stats['total'] === 0): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>Nenhum usuário cadastrado</h3>
            <p>Comece criando o primeiro usuário desta empresa.</p>
            <a href="usr_admin_novo_usuario3.php" class="btn-neo btn-neo-primary">
                <i class="fas fa-plus"></i> Novo Usuário
            </a>
        </div>
    <?php else: ?>
        <div class="table-card">
            <table id="tabelaUsuarios" class="table table-neo" style="width:100%">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Usuário</th>
                        <th style="width: 130px;">Status</th>
                        <th style="width: 160px;">Função</th>
                        <th style="width: 200px;">Diretoria / Gerência</th>
                        <th style="width: 130px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u):
                    $uid      = $u['UserID'] ?? $u['UserId'] ?? 0;
                    $ativo    = !empty($u['Ativo']);
                    $nome     = strtoupper($u['Usuario'] ?? '?');
                    $initial  = mb_substr($nome, 0, 1);
                    $fotoPath = __DIR__ . "/fotos/" . (int)$uid . ".jpg";
                    $hasFoto  = file_exists($fotoPath);

                    $diretoria = $u['DiretoriaNome'] ?? $u['Diretoria'] ?? null;
                    $gerencia  = $u['GerenciaNome']  ?? $u['Gerencia']  ?? null;
                ?>
                    <tr class="<?= $ativo ? '' : 'row-inativo' ?>"
                        data-status="<?= $ativo ? '1' : '0' ?>">
                        <td><span class="user-id">#<?= str_pad($uid, 3, '0', STR_PAD_LEFT) ?></span></td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">
                                    <?php if ($hasFoto): ?>
                                        <img src="fotos/<?= (int)$uid ?>.jpg" alt="">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initial) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="user-name"><?= htmlspecialchars($nome) ?></div>
                                    <div class="user-id"><?= htmlspecialchars($u['Nome'] ?? '—') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge-status <?= $ativo ? 'on' : 'off' ?>">
                                <i class="fas fa-<?= $ativo ? 'check-circle' : 'ban' ?>"></i>
                                <?= $ativo ? 'ATIVO' : 'INATIVO' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-funcao"><?= htmlspecialchars($u['Funcao'] ?? '—') ?></span>
                        </td>
                        <td>
                            <div style="font-size:0.76rem;line-height:1.5;">
                                <div><strong style="color:var(--info);">D:</strong> <?= htmlspecialchars($diretoria ?: 'N/D') ?></div>
                                <div><strong style="color:var(--success);">G:</strong> <?= htmlspecialchars($gerencia ?: 'N/D') ?></div>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <a href="usr_admin_editar3.php?id=<?= (int)$uid ?>"
                                   class="btn-icon edit" title="Editar">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <button type="button"
                                        class="btn-icon <?= $ativo ? 'deactivate' : 'activate' ?>"
                                        onclick="confirmarToggle(<?= (int)$uid ?>, <?= json_encode($nome) ?>, <?= $ativo ? 0 : 1 ?>)"
                                        title="<?= $ativo ? 'Inativar' : 'Ativar' ?>">
                                    <i class="fas fa-<?= $ativo ? 'user-slash' : 'user-plus' ?>"></i>
                                </button>
                                <button type="button"
                                        class="btn-icon delete"
                                        onclick="confirmarExclusao(<?= (int)$uid ?>, <?= json_encode($nome) ?>)"
                                        title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- ============================================================ -->
<!-- FORMS OCULTOS (envio via SweetAlert)                         -->
<!-- ============================================================ -->
<form id="formToggle" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_usr_admin']) ?>">
    <input type="hidden" name="acao" value="toggle_status">
    <input type="hidden" name="User_Id" id="toggleUserId" value="">
    <input type="hidden" name="novo_status" id="toggleStatus" value="">
</form>

<form id="formDelete" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_usr_admin']) ?>">
    <input type="hidden" name="acao" value="excluir">
    <input type="hidden" name="User_Id" id="deleteUserId" value="">
</form>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
(function () {
    'use strict';

    const THEME_PRIMARY = '<?= $themeSafe['primary'] ?>';

    // ============================================================
    // DATATABLE
    // ============================================================
    let table = null;
    if (document.getElementById('tabelaUsuarios')) {
        table = $('#tabelaUsuarios').DataTable({
            language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json" },
            pageLength: 50,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Todos"]],
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [5] },
                { searchable: false, targets: [5] }
            ]
        });

        // Filtro customizado: só ativos / só inativos
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'tabelaUsuarios') return true;
            const $ativo   = document.getElementById('filterAtivo');
            const $inativo = document.getElementById('filterInativo');

            if (!$ativo?.checked && !$inativo?.checked) return true; // nenhum filtro ativo

            const tr = settings.aoData[dataIndex].nTr;
            const isAtivo = tr.dataset.status === '1';

            if ($ativo?.checked && !isAtivo) return false;
            if ($inativo?.checked && isAtivo) return false;
            return true;
        });

        document.getElementById('filterAtivo')?.addEventListener('change', function () {
            if (this.checked) document.getElementById('filterInativo').checked = false;
            table.draw();
        });
        document.getElementById('filterInativo')?.addEventListener('change', function () {
            if (this.checked) document.getElementById('filterAtivo').checked = false;
            table.draw();
        });
    }

    // ============================================================
    // BUSCA CUSTOMIZADA
    // ============================================================
    const $search = document.getElementById('searchInput');
    const $clear  = document.getElementById('searchClear');
    let searchTimer;

    if ($search) {
        $search.addEventListener('input', function () {
            const v = this.value;
            $clear.classList.toggle('visible', v.length > 0);
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
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
    // TOGGLE STATUS
    // ============================================================
    window.confirmarToggle = function (id, nome, novoStatus) {
        const ativando = novoStatus === 1;
        Swal.fire({
            title: ativando ? 'Ativar usuário?' : 'Inativar usuário?',
            html: `O usuário <b>${nome}</b> ${ativando ? 'poderá voltar a' : 'não poderá mais'} acessar o sistema.`,
            icon: ativando ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonText: ativando ? 'Sim, ativar' : 'Sim, inativar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: ativando ? '#10b981' : '#f59e0b',
            cancelButtonColor: '#6b7280'
        }).then(r => {
            if (r.isConfirmed) {
                document.getElementById('toggleUserId').value = id;
                document.getElementById('toggleStatus').value = novoStatus;
                document.getElementById('formToggle').submit();
            }
        });
    };

    // ============================================================
    // EXCLUSÃO
    // ============================================================
    window.confirmarExclusao = function (id, nome) {
        Swal.fire({
            title: 'Excluir usuário?',
            html: `O usuário <b>${nome}</b> será removido <b>permanentemente</b>.<br><span style="color:#ef4444;font-size:0.85rem;font-weight:700;">Esta ação não pode ser desfeita.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280'
        }).then(r => {
            if (r.isConfirmed) {
                document.getElementById('deleteUserId').value = id;
                document.getElementById('formDelete').submit();
            }
        });
    };

    // ============================================================
    // TOAST pós-ação
    // ============================================================
    <?php if (isset($_SESSION['MsgSucesso'])): ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: <?= json_encode(strip_tags($_SESSION['MsgSucesso'])) ?>,
            timer: 4000,
            timerProgressBar: true,
            showConfirmButton: false
        });
    <?php endif; ?>

})();
</script>
</body>
</html>