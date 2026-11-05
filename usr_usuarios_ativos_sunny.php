<?php
// session_start() PRIMEIRO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'send_email.php';
require_once 'theme_config.php';

if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}
$loggedUser = $_SESSION['Usuario'];

// --- Conexão ---
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else die("Erro: conexão com o banco não encontrada.");

// --- Tema seguro ---
$themeDefaults = [
    'primary' => '#800020', 'primary_dark' => '#4B0C1D',
    'secondary' => '#6c757d', 'accent' => '#ffc107',
    'bg_body' => '#f0f2f5', 'text_color' => '#1a1a2e',
    'card_bg' => '#ffffff', 'shadow_color' => '#1a1a2e',
    'btn_bg' => '#800020', 'btn_hover' => '#4B0C1D', 'btn_text' => '#ffffff',
];
$themeSafe = array_merge($themeDefaults, isset($theme) && is_array($theme) ? $theme : []);

// --- Helper para avatar ---
function avatarUser($uid) {
    $path = __DIR__ . "/fotos/" . (int)$uid . ".jpg";
    if (file_exists($path)) {
        return ["url" => "fotos/" . (int)$uid . ".jpg", "initial" => null];
    }
    return ["url" => null, "initial" => null]; // inicial calculada no template
}

try {
    $idsDiretores = $db->query("SELECT DISTINCT UserId FROM diretorias WHERE UserId IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $idsGerentes  = $db->query("SELECT DISTINCT UserId FROM gerencias  WHERE UserId IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $todosAtivos  = $db->query("SELECT * FROM usuarios WHERE IdEmp = 2 AND Ativo = 1 ORDER BY Usuario ASC")->fetchAll(PDO::FETCH_ASSOC);
    $mapaGrupos   = $db->query("SELECT ID_Grupo, Nome_Grupo FROM grupo")->fetchAll(PDO::FETCH_KEY_PAIR);
    $toccaGroupId = array_search('TOCCA_ONZE', $mapaGrupos);

    $lideranca = [];
    $colaboradores = [];
    $ativosTocca = 0;
    $totalLideranca = 0;
    $totalColaboradores = 0;

    foreach ($todosAtivos as $u) {
        $uid = $u['UserId'] ?? $u['UserID'] ?? 0;
        $isLider = (in_array($uid, $idsDiretores) || in_array($uid, $idsGerentes));

        if ($isLider) {
            $lideranca[] = $u;
            $totalLideranca++;
        } else {
            $colaboradores[] = $u;
            $totalColaboradores++;
        }

        if ($toccaGroupId !== false && ($u['GrupoId'] ?? null) == $toccaGroupId) {
            $ativosTocca++;
        }
    }

    $usuariosFinal    = array_merge($lideranca, $colaboradores);
    $totalGeralAtivos = count($usuariosFinal);

    // --- Email de notificação ---
    if (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '::1') {
        $assunto  = "Usuarios Ativos Tocca Onze - " . strtoupper($loggedUser) . " - " . date('d/m/Y H:i:s');
        $mensagem = "Acesso à página de Contagem de Ativos\n\n"
                  . "Título: Usuarios Ativos Tocca Onze\n"
                  . "Usuário Logado: " . strtoupper($loggedUser) . "\n"
                  . "Total de Usuários Ativos: " . $totalGeralAtivos . "\n"
                  . "Liderança: " . $totalLideranca . "\n"
                  . "Colaboradores: " . $totalColaboradores . "\n"
                  . "Usuários no Grupo Tocca Onze: " . $ativosTocca . "\n"
                  . "IP de Acesso: " . $_SERVER['REMOTE_ADDR'];
        @enviarEmailSGVendas("sendmail@gabnetweb.com.br, valterpb@hotmail.com", $assunto, $mensagem);
    }

} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

// Cálculo licenças protegido contra negativo
$baseAtivos   = max(0, $ativosTocca - 10);
$val_licencas = $baseAtivos * 30;
$val_total    = $val_licencas + 350;
$referencia   = $baseAtivos . "-" . $val_licencas . "-350-" . $val_total;
$data_hora    = date("d/m/Y H:i");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Ativos — TOCCA ONZE</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

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
            --success: #10b981;
            --danger: #ef4444;
            --info: #3b82f6;
            --warning: #f59e0b;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.05) 1px, transparent 0);
            background-size: 22px 22px;
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding: 0 0 40px;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================================ */
        /* HEADER                                                       */
        /* ============================================================ */
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
            position: relative;
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
            gap: 20px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .header-brand { display: flex; align-items: center; gap: 16px; min-width: 0; }
        .logo-img {
            height: 58px; width: auto; max-width: 160px; min-width: 58px;
            object-fit: contain;
            border: 3px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: #fff;
            padding: 5px 8px;
            flex-shrink: 0;
        }
        .header-title h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: -0.4px;
            line-height: 1.15;
        }
        .header-title p {
            margin: 4px 0 0;
            font-size: 0.78rem;
            font-weight: 600;
            opacity: 0.9;
        }
        .header-title p strong {
            background: rgba(255,255,255,0.18);
            padding: 1px 8px;
            border-radius: 6px;
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
            min-width: 80px;
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
        .stat-badge.is-info    { background: var(--info);    }
        .stat-badge.is-warning { background: var(--warning); color: #000; }

        .btn-close-header {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 800;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            text-decoration: none;
        }
        .btn-close-header:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            background: #b02a37;
            color: #fff;
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
            margin-bottom: 18px;
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
            width: 40px; height: 22px;
            background: #d1d5db;
            border-radius: 999px;
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .neo-switch .switch-track::after {
            content: "";
            position: absolute;
            width: 18px; height: 18px;
            background: #fff;
            border-radius: 50%;
            top: 2px; left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .neo-switch input:checked + .switch-track { background: var(--primary); }
        .neo-switch input:checked + .switch-track::after { transform: translateX(18px); }
        .neo-switch i { color: var(--primary); }

        /* ============================================================ */
        /* TABLE                                                        */
        /* ============================================================ */
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
            padding: 12px !important;
            font-size: 13.5px;
            border: none !important;
            border-bottom: 1px solid rgba(0,0,0,0.07) !important;
            vertical-align: middle !important;
        }
        table.dataTable.table-neo tbody tr:last-child td { border-bottom: none !important; }
        table.dataTable.table-neo tbody tr:hover td { background: rgba(0,0,0,0.025); }

        /* Linha de liderança destacada */
        table.dataTable.table-neo tbody tr.row-lideranca td {
            background: rgba(59,130,246,0.06);
        }
        table.dataTable.table-neo tbody tr.row-lideranca:hover td {
            background: rgba(59,130,246,0.12);
        }
        table.dataTable.table-neo tbody tr.row-lideranca td:first-child {
            box-shadow: inset 4px 0 0 0 var(--info);
        }

        /* Linha do grupo Tocca */
        table.dataTable.table-neo tbody tr.row-tocca td:first-child {
            box-shadow: inset 4px 0 0 0 var(--warning);
        }
        table.dataTable.table-neo tbody tr.row-lideranca.row-tocca td:first-child {
            box-shadow: inset 4px 0 0 0 var(--info), inset 8px 0 0 0 var(--warning);
        }

        /* Célula do usuário */
        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1rem;
            text-transform: uppercase;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .user-info { min-width: 0; }
        .user-nome {
            font-weight: 800;
            font-size: 0.92rem;
            color: var(--text-color);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .user-real {
            font-size: 0.76rem;
            color: var(--text-color);
            opacity: 0.65;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Badges */
        .badge-rank {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            padding: 4px 10px;
            background: var(--shadow-color);
            color: #fff;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.72rem;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 rgba(0,0,0,0.15);
        }
        .badge-id {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            background: rgba(0,0,0,0.05);
            color: var(--text-color);
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.72rem;
            border: 1.5px solid rgba(0,0,0,0.1);
            font-family: 'SFMono-Regular', Consolas, monospace;
        }
        .badge-ativo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--success);
            color: #fff;
            font-size: 0.66rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .badge-ativo::before {
            content: "";
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #fff;
        }
        .badge-hier {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            font-size: 0.68rem;
            font-weight: 800;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            margin-right: 4px;
            margin-bottom: 2px;
        }
        .badge-hier.dir { background: var(--primary); color: #fff; }
        .badge-hier.ger { background: var(--success); color: #fff; }
        .badge-hier.ges { background: #6b7280; color: #fff; }

        .badge-grupo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            background: rgba(245,158,11,0.15);
            color: #b45309;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.72rem;
            border: 1.5px solid rgba(245,158,11,0.35);
        }

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
        /* MOBILE CARDS                                                 */
        /* ============================================================ */
        .mobile-container { display: none; }
        .user-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            padding: 16px;
            margin-bottom: 14px;
            transition: all 0.15s;
            position: relative;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 7px 7px 0 0 var(--shadow-color);
        }
        .user-card.lider { border-left: 8px solid var(--info); }
        .user-card.tocca { background: linear-gradient(to right, rgba(245,158,11,0.04) 0%, var(--card-bg) 40%); }

        .user-card .card-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .user-card .user-avatar { width: 50px; height: 50px; font-size: 1.15rem; }
        .user-card .card-top-info { min-width: 0; flex: 1; }
        .user-card .user-nome { font-size: 1rem; }
        .user-card .badges-row { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }

        .user-card .card-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px dashed rgba(0,0,0,0.12);
            font-size: 0.78rem;
            gap: 12px;
            flex-wrap: wrap;
        }
        .user-card .card-details .detail { display: flex; flex-direction: column; }
        .user-card .card-details .detail small { opacity: 0.6; font-weight: 600; text-transform: uppercase; font-size: 0.6rem; letter-spacing: 0.5px; }
        .user-card .card-details .detail strong { font-weight: 800; }
        .user-card .card-index {
            position: absolute;
            top: 8px; right: 10px;
            font-size: 0.65rem;
            font-weight: 800;
            background: var(--shadow-color);
            color: #fff;
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* ============================================================ */
        /* EMPTY STATE                                                  */
        /* ============================================================ */
        .empty-state {
            display: none;
            text-align: center;
            padding: 50px 20px;
            background: var(--card-bg);
            border: 3px dashed var(--shadow-color);
            border-radius: 16px;
            color: var(--text-color);
        }
        .empty-state.visible { display: block; }
        .empty-state i { font-size: 3rem; color: var(--primary); opacity: 0.4; display: block; margin-bottom: 12px; }
        .empty-state h3 { font-weight: 900; margin: 0 0 6px; }
        .empty-state p { opacity: 0.65; font-weight: 500; margin: 0; }

        /* ============================================================ */
        /* FOOTER                                                       */
        /* ============================================================ */
        .custom-footer {
            text-align: center;
            padding: 26px 20px 10px;
            border-top: 3px solid var(--shadow-color);
            margin-top: 30px;
            font-size: 0.78rem;
            color: var(--text-color);
        }
        .custom-footer strong { color: var(--primary); }
        .custom-footer small { opacity: 0.7; }

        /* ============================================================ */
        /* RESPONSIVO                                                   */
        /* ============================================================ */
        @media (max-width: 992px) {
            .header-title h1 { font-size: 1.1rem; }
            .logo-img { height: 52px; max-width: 140px; }
        }
        @media (max-width: 767px) {
            .app-header { padding: 10px 0; }
            .header-inner { padding: 0 12px; gap: 12px; }
            .logo-img { height: 42px; max-width: 110px; padding: 3px 6px; }
            .header-title h1 { font-size: 0.95rem; }
            .header-title p { font-size: 0.7rem; }
            .header-stats { width: 100%; }
            .stat-badge { flex: 1; min-width: 0; padding: 6px 10px; }
            .stat-badge .num { font-size: 1.1rem; }
            .stat-badge .label { font-size: 0.55rem; }
            .btn-close-header { width: 100%; justify-content: center; }
            .container-neo { padding: 0 12px; }
            .table-card { display: none; }
            .mobile-container { display: block; }
            .toolbar-left { flex-basis: 100%; }
            .search-box { max-width: 100%; flex-basis: 100%; }
            .neo-switch { font-size: 0.75rem; padding: 7px 11px; }
        }
        @media (min-width: 768px) {
            .mobile-container { display: none; }
        }
    </style>
</head>
<body>

<!-- ============================================================ -->
<!-- HEADER                                                       -->
<!-- ============================================================ -->
<header class="app-header">
    <div class="header-inner">
        <div class="header-brand">
            <img src="img/logosunny.jpg" alt="Logo" class="logo-img">
            <div class="header-title">
                <h1><i class="fas fa-users me-2"></i> Gestão de Ativos</h1>
                <p>Grupo <strong>TOCCA_ONZE</strong> · Painel de equipe</p>
            </div>
        </div>

        <div class="header-stats">
            <div class="stat-badge">
                <span class="num"><?php echo $totalGeralAtivos; ?></span>
                <span class="label">Total</span>
            </div>
            <div class="stat-badge is-info">
                <span class="num"><?php echo $totalLideranca; ?></span>
                <span class="label">Liderança</span>
            </div>
            <div class="stat-badge is-success">
                <span class="num"><?php echo $totalColaboradores; ?></span>
                <span class="label">Colaboradores</span>
            </div>
            <div class="stat-badge is-warning">
                <span class="num"><?php echo $ativosTocca; ?></span>
                <span class="label">Grupo Tocca</span>
            </div>
        </div>

        <a href="#" class="btn-close-header" onclick="window.close(); return false;">
            <i class="fas fa-times"></i> Fechar
        </a>
    </div>
</header>

<!-- ============================================================ -->
<!-- CONTEÚDO                                                     -->
<!-- ============================================================ -->
<div class="container-neo">

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search icon"></i>
                <input type="text" id="searchInput" placeholder="Buscar por nome ou usuário…" autocomplete="off">
                <button type="button" class="clear" id="searchClear" title="Limpar busca">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <label class="neo-switch" title="Mostrar apenas diretores e gerentes">
                <input type="checkbox" id="filterLider">
                <span class="switch-track"></span>
                <span><i class="fas fa-crown"></i> Só liderança</span>
            </label>

            <label class="neo-switch" title="Mostrar apenas usuários do grupo Tocca Onze">
                <input type="checkbox" id="filterTocca">
                <span class="switch-track"></span>
                <span><i class="fas fa-users-cog"></i> Só grupo Tocca</span>
            </label>
        </div>
    </div>

    <!-- ========== TABELA (desktop) ========== -->
    <div class="table-card">
        <table id="tabelaAtivos" class="table table-neo w-100">
            <thead>
                <tr>
                    <th style="width: 70px;">#</th>
                    <th style="width: 100px;">ID</th>
                    <th>Colaborador</th>
                    <th style="width: 180px;">Hierarquia</th>
                    <th style="width: 200px;">Grupo</th>
                    <th style="width: 110px;">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php $counter = 1; foreach ($usuariosFinal as $u):
                $uid     = $u['UserId'] ?? $u['UserID'] ?? 0;
                $eDir    = in_array($uid, $idsDiretores);
                $eGer    = in_array($uid, $idsGerentes);
                $isLider = ($eDir || $eGer);
                $isTocca = ($toccaGroupId !== false && ($u['GrupoId'] ?? null) == $toccaGroupId);

                $fotoPath = __DIR__ . "/fotos/" . (int)$uid . ".jpg";
                $hasFoto  = file_exists($fotoPath);
                $initial  = strtoupper(mb_substr($u['Usuario'] ?? '?', 0, 1));

                $cls = trim(($isLider ? 'row-lideranca ' : '') . ($isTocca ? 'row-tocca' : ''));
            ?>
                <tr class="<?php echo $cls; ?>">
                    <td><span class="badge-rank"><?php echo $counter++; ?>º</span></td>
                    <td><span class="badge-id">#<?php echo str_pad($uid, 3, '0', STR_PAD_LEFT); ?></span></td>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar">
                                <?php if ($hasFoto): ?>
                                    <img src="fotos/<?php echo (int)$uid; ?>.jpg" alt="">
                                <?php else: ?>
                                    <?php echo $initial; ?>
                                <?php endif; ?>
                            </div>
                            <div class="user-info">
                                <div class="user-nome"><?php echo htmlspecialchars(strtoupper($u['Usuario'])); ?></div>
                                <div class="user-real"><?php echo htmlspecialchars($u['Nome']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($eDir): ?><span class="badge-hier dir"><i class="fas fa-crown me-1"></i>Diretoria</span><?php endif; ?>
                        <?php if ($eGer): ?><span class="badge-hier ger"><i class="fas fa-user-tie me-1"></i>Gerência</span><?php endif; ?>
                        <?php if (!$eDir && !$eGer): ?><span class="badge-hier ges">Gestão</span><?php endif; ?>
                    </td>
                    <td>
                        <?php $grupoNome = $mapaGrupos[$u['GrupoId'] ?? 0] ?? null; ?>
                        <?php if ($grupoNome): ?>
                            <span class="badge-grupo"><i class="fas fa-users"></i> <?php echo htmlspecialchars($grupoNome); ?></span>
                        <?php else: ?>
                            <span style="opacity:0.4;font-weight:600;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-ativo">Ativo</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ========== CARDS (mobile) ========== -->
    <div class="mobile-container">
        <div id="listaCards">
            <?php $counterM = 1; foreach ($usuariosFinal as $u):
                $uid     = $u['UserId'] ?? $u['UserID'] ?? 0;
                $eDir    = in_array($uid, $idsDiretores);
                $eGer    = in_array($uid, $idsGerentes);
                $isLider = ($eDir || $eGer);
                $isTocca = ($toccaGroupId !== false && ($u['GrupoId'] ?? null) == $toccaGroupId);

                $fotoPath = __DIR__ . "/fotos/" . (int)$uid . ".jpg";
                $hasFoto  = file_exists($fotoPath);
                $initial  = strtoupper(mb_substr($u['Usuario'] ?? '?', 0, 1));

                $grupoNome = $mapaGrupos[$u['GrupoId'] ?? 0] ?? null;

                $cardCls = trim(($isLider ? 'lider ' : '') . ($isTocca ? 'tocca' : ''));
            ?>
            <div class="user-card <?php echo $cardCls; ?>"
                 data-search="<?php echo htmlspecialchars(strtolower($u['Usuario'] . ' ' . $u['Nome'])); ?>"
                 data-lider="<?php echo $isLider ? '1' : '0'; ?>"
                 data-tocca="<?php echo $isTocca ? '1' : '0'; ?>">

                <span class="card-index"><?php echo $counterM++; ?>º</span>

                <div class="card-top">
                    <div class="user-avatar">
                        <?php if ($hasFoto): ?>
                            <img src="fotos/<?php echo (int)$uid; ?>.jpg" alt="">
                        <?php else: ?>
                            <?php echo $initial; ?>
                        <?php endif; ?>
                    </div>
                    <div class="card-top-info">
                        <div class="user-nome"><?php echo htmlspecialchars(strtoupper($u['Usuario'])); ?></div>
                        <div class="user-real"><?php echo htmlspecialchars($u['Nome']); ?></div>
                        <div class="badges-row">
                            <?php if ($eDir): ?><span class="badge-hier dir"><i class="fas fa-crown me-1"></i>Diretoria</span><?php endif; ?>
                            <?php if ($eGer): ?><span class="badge-hier ger"><i class="fas fa-user-tie me-1"></i>Gerência</span><?php endif; ?>
                            <?php if (!$eDir && !$eGer): ?><span class="badge-hier ges">Gestão</span><?php endif; ?>
                            <span class="badge-ativo" style="font-size:0.6rem;padding:2px 8px;">Ativo</span>
                        </div>
                    </div>
                </div>

                <div class="card-details">
                    <div class="detail">
                        <small>ID</small>
                        <strong>#<?php echo $uid; ?></strong>
                    </div>
                    <div class="detail" style="text-align:right;">
                        <small>Grupo</small>
                        <strong><?php echo $grupoNome ? htmlspecialchars($grupoNome) : '—'; ?></strong>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="empty-state" id="emptyCards">
            <i class="fas fa-search"></i>
            <h3>Nenhum resultado</h3>
            <p>Nenhum colaborador corresponde à busca ou aos filtros atuais.</p>
        </div>
    </div>

</div>

<!-- ============================================================ -->
<!-- FOOTER                                                       -->
<!-- ============================================================ -->
<footer class="custom-footer">
    <div style="margin-bottom: 5px;">
        <strong>Sistema SunnyImob</strong> — Valter Barreto
    </div>
    <div>
        <small>Cód.: <strong><?php echo $referencia; ?></strong> · <?php echo $data_hora; ?></small>
    </div>
</footer>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
(function () {
    'use strict';

    $(document).ready(function () {

        // ============================================================
        // 1) DataTables — sempre inicializa
        // ============================================================
        const table = $('#tabelaAtivos').DataTable({
            language: { url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json" },
            pageLength: 50,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Todos"]],
            ordering: false,
            columnDefs: [
                { targets: [0, 1, 3, 4, 5], orderable: false },
                { targets: [0, 1, 5], searchable: false }
            ],
            drawCallback: function () {
                // Reaplica visual (a classe já está no tr)
            }
        });

        // ============================================================
        // 2) Filtros customizados para DataTables
        // ============================================================
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'tabelaAtivos') return true;
            const $lider = $('#filterLider');
            const $tocca = $('#filterTocca');
            const tr = settings.aoData[dataIndex].nTr;

            if ($lider.is(':checked') && !$(tr).hasClass('row-lideranca')) return false;
            if ($tocca.is(':checked') && !$(tr).hasClass('row-tocca')) return false;
            return true;
        });

        // ============================================================
        // 3) Busca customizada
        // ============================================================
        const $search = $('#searchInput');
        const $clear  = $('#searchClear');
        let timer;

        $search.on('input', function () {
            const v = $(this).val();
            $clear.toggleClass('visible', v.length > 0);

            clearTimeout(timer);
            timer = setTimeout(function () {
                table.search(v).draw();
                filtrarCards(v);
            }, 150);
        });

        $search.on('keydown', function (e) {
            if (e.key === 'Escape') $(this).val('').trigger('input');
        });

        $clear.on('click', function () {
            $search.val('').trigger('input').focus();
        });

        // ============================================================
        // 4) Filtros (switches) — afetam tabela e cards
        // ============================================================
        $('#filterLider, #filterTocca').on('change', function () {
            table.draw();
            filtrarCards($search.val());
        });

        // ============================================================
        // 5) Cards mobile — filtro client-side
        // ============================================================
        function filtrarCards(termo) {
            termo = (termo || '').toLowerCase().trim();
            const onlyLider = $('#filterLider').is(':checked');
            const onlyTocca = $('#filterTocca').is(':checked');

            let visiveis = 0;
            $('#listaCards .user-card').each(function () {
                const $c = $(this);
                const matchTexto = !termo || ($c.data('search') || '').indexOf(termo) !== -1;
                const matchLider = !onlyLider || $c.data('lider') === 1;
                const matchTocca = !onlyTocca || $c.data('tocca') === 1;

                const show = matchTexto && matchLider && matchTocca;
                $c.toggle(show);
                if (show) visiveis++;
            });

            $('#emptyCards').toggleClass('visible', visiveis === 0);
        }

        // Inicializa empty state
        filtrarCards('');

        // ============================================================
        // 6) Ajuste do DataTables ao redimensionar
        // ============================================================
        let resizeTimer;
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                table.columns.adjust();
            }, 200);
        });
    });
})();
</script>
</body>
</html>