<?php
// ============================================================
// SESSION PRIMEIRO
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['Usuario'])) {
    header("Location: login_p1.php");
    exit();
}

require_once 'conn.php';
require_once 'theme_config.php';

// ============================================================
// CONEXÃO
// ============================================================
if (isset($conn) && $conn instanceof PDO)      $db = $conn;
elseif (isset($pdo) && $pdo instanceof PDO)    $db = $pdo;
else $db = null;

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
// DADOS DE SESSÃO
// ============================================================
$usuarioLogado   = htmlspecialchars($_SESSION['Usuario']   ?? 'Visitante');
$permissaoLogado = htmlspecialchars($_SESSION['Permissao'] ?? '—');
$userIdLogado    = (int)($_SESSION['UserId']               ?? 0);

// ============================================================
// ESTATÍSTICAS (tolerantes a falha)
// ============================================================
$stats = [
    'usuarios'   => null,
    'logs_hoje'  => null,
    'grupos'     => null,
    'empresas'   => null,
    'diretorias' => null,
    'gerencias'  => null,
];

if ($db) {
    try { $stats['usuarios']   = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE Ativo = 1")->fetchColumn(); } catch (Exception $e) {}
    try { $stats['grupos']     = (int)$db->query("SELECT COUNT(*) FROM grupo")->fetchColumn(); } catch (Exception $e) {}
    try { $stats['empresas']   = (int)$db->query("SELECT COUNT(*) FROM sunnyempresas")->fetchColumn(); } catch (Exception $e) {}
    try { $stats['diretorias'] = (int)$db->query("SELECT COUNT(*) FROM diretorias")->fetchColumn(); } catch (Exception $e) {}
    try { $stats['gerencias']  = (int)$db->query("SELECT COUNT(*) FROM gerencias WHERE DataEncerramento IS NULL")->fetchColumn(); } catch (Exception $e) {}

    try {
        $stats['logs_hoje'] = (int)$db->query("SELECT COUNT(*) FROM log_navegacao WHERE DATE(DataHora) = CURDATE()")->fetchColumn();
    } catch (Exception $e) {
        try { $stats['logs_hoje'] = (int)$db->query("SELECT COUNT(*) FROM logs WHERE DATE(Data) = CURDATE()")->fetchColumn(); } catch (Exception $e2) {}
    }
}

// ============================================================
// MENU ESTRUTURADO
// ============================================================
$menu = [
    'Estrutura Organizacional' => [
        ['icon' => 'bi-building',  'title' => 'Gestão de Diretorias', 'url' => 'diretoria_list.php', 'desc' => 'Cadastro e gerenciamento de diretorias'],
        ['icon' => 'bi-diagram-3', 'title' => 'Gestão de Gerências',  'url' => 'gerencia_list.php',  'desc' => 'Gerências vinculadas às diretorias'],
    ],
    'Gestão de Usuários' => [
        ['icon' => 'bi-people-fill',     'title' => 'Usuários Ativos / Licenciados', 'url' => 'usr_admin_listar3b.php', 'desc' => 'Listagem completa com status de licença'],
        ['icon' => 'bi-diagram-3-fill',  'title' => 'Contagem por Grupos',            'url' => 'usr_grupo_contagem.php', 'desc' => 'Distribuição de usuários por grupo'],
        ['icon' => 'bi-person-plus-fill','title' => 'Cadastro de Usuário',            'url' => 'usr_novo_usuario1.php',  'desc' => 'Novo usuário no sistema'],
        ['icon' => 'bi-pencil-square',   'title' => 'Editar Usuários',                'url' => 'usr_listar.php',         'desc' => 'Modificar cadastros existentes'],
    ],
    'Logs e Histórico' => [
        ['icon' => 'bi-clock-history', 'title' => 'Histórico de Alterações', 'url' => 'historico_list.php',    'desc' => 'Registro de mudanças no sistema'],
        ['icon' => 'bi-journal-text',  'title' => 'Logs de Acesso',          'url' => 'log_list.php',          'desc' => 'Navegação de usuários'],
        ['icon' => 'bi-graph-up',      'title' => 'Contagem de Páginas',     'url' => 'log_conta_paginas.php', 'desc' => 'Páginas mais acessadas'],
    ],
    'Configurações' => [
        ['icon' => 'bi-building',     'title' => 'Gestão de Empresas',   'url' => 'sunnyempresas_crud.php', 'desc' => 'Cadastro de empresas do sistema'],
        ['icon' => 'bi-folder2-open', 'title' => 'Atualizador de Menu',  'url' => 'menu4b.php',             'desc' => 'Configurar itens do menu principal'],
        ['icon' => 'bi-tools',        'title' => 'Manutenção do Sistema', 'url' => 'manutmenu.php',          'desc' => 'Ferramentas administrativas'],
    ],
];

// ============================================================
// AÇÕES RÁPIDAS (atualizadas)
// ============================================================
$quickActions = [
    ['icon' => 'bi-people',       'label' => 'Usuários',   'url' => 'usr_listar.php',     'color' => 'primary'],
    ['icon' => 'bi-building',     'label' => 'Diretorias', 'url' => 'diretoria_list.php', 'color' => 'info'],
    ['icon' => 'bi-diagram-3',    'label' => 'Gerências',  'url' => 'gerencia_list.php',  'color' => 'warning'],
    ['icon' => 'bi-journal-text', 'label' => 'Ver Logs',   'url' => 'log_list.php',       'color' => 'success'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo · SunnyImob</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
            --purple: #8b5cf6;
            --cyan: #06b6d4;
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

        .container-neo { max-width: 1300px; margin: 0 auto; padding: 0 20px; }

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
        }
        .app-header::after {
            content: "";
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0 50%, transparent 50% 100%);
            background-size: 40px 40px;
            pointer-events: none;
        }
        .header-inner {
            max-width: 1300px;
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
        .header-logo {
            height: 56px; width: auto; max-width: 160px; min-width: 56px;
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
            font-size: 1.2rem;
            font-weight: 900;
            letter-spacing: -0.3px;
            line-height: 1.15;
        }
        .header-title p {
            margin: 4px 0 0;
            font-size: 0.76rem;
            font-weight: 600;
            opacity: 0.9;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(0,0,0,0.18);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            padding: 6px 12px 6px 6px;
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            flex-shrink: 0;
        }
        .header-user img {
            width: 40px; height: 40px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #fff;
            background: #fff;
            flex-shrink: 0;
        }
        .header-user-text .label {
            display: block;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            opacity: 0.75;
        }
        .header-user-text .name {
            display: block;
            font-size: 0.85rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .header-user-text .perm {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0 6px;
            border-radius: 6px;
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.4px;
            margin-top: 2px;
        }

        .btn-header {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            border-radius: 12px;
            padding: 10px 18px;
            font-weight: 800;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-header:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            background: #b02a37;
            color: #fff;
        }

        /* ============================================================ */
        /* WELCOME HERO                                                 */
        /* ============================================================ */
        .welcome-hero {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 18px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0;
            align-items: center;
        }
        .welcome-body { padding: 26px 30px; }
        .welcome-title {
            font-size: 1.4rem;
            font-weight: 900;
            letter-spacing: -0.5px;
            margin: 0 0 8px;
            color: var(--text-color);
            line-height: 1.2;
        }
        .welcome-desc {
            margin: 0 0 16px;
            font-size: 0.88rem;
            color: var(--text-color);
            opacity: 0.7;
            font-weight: 500;
            line-height: 1.5;
        }
        .welcome-chips { display: flex; gap: 8px; flex-wrap: wrap; }
        .welcome-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(0,0,0,0.05);
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 6px 12px;
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--text-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .welcome-chip i { color: var(--primary); }
        .welcome-hero-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 30px;
            background: var(--primary);
            color: #fff;
            align-self: stretch;
            font-size: 3rem;
            border-left: 3px solid var(--shadow-color);
            min-width: 140px;
        }

        /* ============================================================ */
        /* STATS                                                        */
        /* ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 26px;
        }
        .stat-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.15s;
        }
        .stat-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 0 var(--shadow-color);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            flex-shrink: 0;
        }
        .stat-icon.i-primary  { background: var(--primary); color: #fff; }
        .stat-icon.i-info     { background: var(--info);    color: #fff; }
        .stat-icon.i-success  { background: var(--success); color: #fff; }
        .stat-icon.i-warning  { background: var(--warning); color: #000; }
        .stat-icon.i-secondary{ background: #6b7280;        color: #fff; }
        .stat-icon.i-purple   { background: var(--purple);  color: #fff; }
        .stat-icon.i-cyan     { background: var(--cyan);    color: #fff; }
        .stat-info { min-width: 0; }
        .stat-num {
            font-size: 1.6rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -1px;
            color: var(--text-color);
        }
        .stat-lbl {
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            color: var(--text-color);
            opacity: 0.6;
            margin-top: 4px;
        }

        /* ============================================================ */
        /* SECTION                                                      */
        /* ============================================================ */
        .section {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
            margin-bottom: 22px;
        }
        .section-header {
            background: var(--primary);
            color: #fff;
            padding: 14px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 3px solid var(--shadow-color);
        }
        .section-header i {
            background: rgba(0,0,0,0.25);
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
        }
        .section-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: -0.2px;
        }
        .section-count {
            background: rgba(255,255,255,0.25);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 800;
            margin-left: auto;
        }
        .section-body { padding: 16px; }

        /* ============================================================ */
        /* MENU ITEMS                                                   */
        /* ============================================================ */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 10px;
        }
        .menu-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            background: #f8f9fb;
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .menu-item:hover {
            background: #fff;
            color: var(--text-color);
            text-decoration: none;
            transform: translate(-2px, -2px);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
        }
        .menu-item:hover .menu-icon {
            background: var(--primary);
            color: #fff;
        }
        .menu-item:hover .menu-arrow {
            color: var(--primary);
            transform: translateX(2px);
        }
        .menu-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(0,0,0,0.05);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            transition: all 0.15s;
            border: 2px solid var(--shadow-color);
        }
        .menu-content { flex: 1; min-width: 0; }
        .menu-title {
            font-weight: 800;
            font-size: 0.88rem;
            line-height: 1.2;
            margin: 0 0 2px;
            color: var(--text-color);
        }
        .menu-desc {
            font-size: 0.72rem;
            opacity: 0.6;
            line-height: 1.3;
            margin: 0;
            font-weight: 500;
        }
        .menu-arrow {
            color: var(--text-color);
            opacity: 0.3;
            font-size: 0.85rem;
            transition: all 0.15s;
            flex-shrink: 0;
            margin-top: 12px;
        }

        /* ============================================================ */
        /* QUICK ACTIONS                                                */
        /* ============================================================ */
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
        }
        .quick-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 18px 12px;
            background: #f8f9fb;
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-color);
            font-weight: 800;
            font-size: 0.78rem;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            text-align: center;
        }
        .quick-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            color: var(--text-color);
            text-decoration: none;
        }
        .quick-btn i {
            font-size: 1.4rem;
            transition: transform 0.15s;
        }
        .quick-btn:hover i { transform: scale(1.15); }
        .quick-btn.q-primary i { color: var(--primary); }
        .quick-btn.q-info    i { color: var(--info); }
        .quick-btn.q-warning i { color: var(--warning); }
        .quick-btn.q-success i { color: var(--success); }

        /* ============================================================ */
        /* INFO SIDEBAR                                                 */
        /* ============================================================ */
        .info-sidebar {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .info-card {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 14px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            padding: 18px;
        }
        .info-card h6 {
            font-size: 0.82rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-color);
            margin: 0 0 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .info-card h6 i { color: var(--primary); }
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px dashed rgba(0,0,0,0.1);
        }
        .info-item:last-child { border-bottom: none; padding-bottom: 0; }
        .info-item .info-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
            border: 2px solid var(--shadow-color);
        }
        .info-item .info-icon.i-green  { background: var(--success); color: #fff; }
        .info-item .info-icon.i-blue   { background: var(--info);    color: #fff; }
        .info-item .info-icon.i-yellow { background: var(--warning); color: #000; }
        .info-item .info-text { min-width: 0; flex: 1; }
        .info-item .info-text strong {
            display: block;
            font-size: 0.85rem;
            font-weight: 800;
            margin-bottom: 1px;
        }
        .info-item .info-text span {
            font-size: 0.74rem;
            color: var(--text-color);
            opacity: 0.6;
            font-weight: 500;
        }

        /* ============================================================ */
        /* FOOTER                                                       */
        /* ============================================================ */
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

        /* ============================================================ */
        /* RESPONSIVO                                                   */
        /* ============================================================ */
        @media (max-width: 991px) {
            .welcome-hero { grid-template-columns: 1fr; }
            .welcome-hero-icon { display: none; }
        }
        @media (max-width: 768px) {
            .app-header { padding: 10px 0; }
            .header-inner { padding: 0 12px; gap: 12px; }
            .header-logo { height: 42px; max-width: 110px; }
            .header-title h1 { font-size: 1rem; }
            .header-title p { font-size: 0.7rem; }
            .header-user { display: none; }
            .btn-header { padding: 8px 14px; font-size: 0.78rem; }
            .container-neo { padding: 0 12px; }
            .welcome-body { padding: 20px 18px; }
            .welcome-title { font-size: 1.1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .menu-grid { grid-template-columns: 1fr; }
            .quick-grid { grid-template-columns: repeat(2, 1fr); }
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
            <img src="img/logosunny.jpg" alt="Logo" class="header-logo" onerror="this.style.display='none'">
            <div class="header-title">
                <h1><i class="bi bi-shield-lock-fill me-2"></i> Painel Administrativo</h1>
                <p>SunnyImob · Central de gestão do sistema</p>
            </div>
        </div>

        <div class="header-user">
            <?php
            $fotoUser = "fotos/" . $userIdLogado . ".jpg";
            $srcFoto  = file_exists(__DIR__ . "/" . $fotoUser) ? $fotoUser : "fotos/semfoto.jpg";
            ?>
            <img src="<?= htmlspecialchars($srcFoto) ?>" alt="Foto" onerror="this.style.display='none'">
            <div class="header-user-text">
                <span class="label">Logado como</span>
                <span class="name"><?= $usuarioLogado ?></span>
                <span class="perm"><?= $permissaoLogado ?></span>
            </div>
        </div>

        <a href="logout.php" class="btn-header" onclick="return confirmarLogout(event)">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>

    </div>
</header>

<!-- ============================================================ -->
<!-- CONTEÚDO                                                     -->
<!-- ============================================================ -->
<div class="container-neo">

    <!-- Welcome Hero -->
    <div class="welcome-hero">
        <div class="welcome-body">
            <h2 class="welcome-title">Bem-vindo, <?= $usuarioLogado ?></h2>
            <p class="welcome-desc">
                Gerencie usuários, estrutura organizacional, logs, empresas e configurações do sistema em um só lugar.
                Todos os links abrem em nova aba para não perder o contexto.
            </p>
            <div class="welcome-chips">
                <span class="welcome-chip"><i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?></span>
                <span class="welcome-chip"><i class="bi bi-clock"></i> <?= date('H:i') ?></span>
                <span class="welcome-chip"><i class="bi bi-person-badge"></i> ID <?= $userIdLogado ?></span>
                <span class="welcome-chip"><i class="bi bi-version"></i> v7.0</span>
            </div>
        </div>
        <div class="welcome-hero-icon">
            <i class="bi bi-speedometer2"></i>
        </div>
    </div>

    <!-- Stats (agora com 6 KPIs) -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon i-primary"><i class="bi bi-people-fill"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['usuarios'] !== null ? $stats['usuarios'] : '—' ?></div>
                <div class="stat-lbl">Usuários Ativos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-info"><i class="bi bi-journal-text"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['logs_hoje'] !== null ? $stats['logs_hoje'] : '—' ?></div>
                <div class="stat-lbl">Logs Hoje</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-warning"><i class="bi bi-diagram-3-fill"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['grupos'] !== null ? $stats['grupos'] : '—' ?></div>
                <div class="stat-lbl">Grupos</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-success"><i class="bi bi-building"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['empresas'] !== null ? $stats['empresas'] : '—' ?></div>
                <div class="stat-lbl">Empresas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-purple"><i class="bi bi-building-gear"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['diretorias'] !== null ? $stats['diretorias'] : '—' ?></div>
                <div class="stat-lbl">Diretorias</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon i-cyan"><i class="bi bi-diagram-3"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $stats['gerencias'] !== null ? $stats['gerencias'] : '—' ?></div>
                <div class="stat-lbl">Gerências</div>
            </div>
        </div>
    </div>

    <!-- Layout 2 colunas -->
    <div class="row g-3">

        <!-- Coluna principal -->
        <div class="col-lg-8">

            <?php foreach ($menu as $categoria => $itens): ?>
                <div class="section">
                    <div class="section-header">
                        <i class="bi bi-folder-fill"></i>
                        <h3 class="section-title"><?= htmlspecialchars($categoria) ?></h3>
                        <span class="section-count"><?= count($itens) ?></span>
                    </div>
                    <div class="section-body">
                        <div class="menu-grid">
                            <?php foreach ($itens as $item): ?>
                                <a href="<?= htmlspecialchars($item['url']) ?>"
                                   target="_blank"
                                   class="menu-item">
                                    <div class="menu-icon">
                                        <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                                    </div>
                                    <div class="menu-content">
                                        <div class="menu-title"><?= htmlspecialchars($item['title']) ?></div>
                                        <?php if (!empty($item['desc'])): ?>
                                            <div class="menu-desc"><?= htmlspecialchars($item['desc']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <i class="bi bi-arrow-right menu-arrow"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="info-sidebar">

                <!-- Ações rápidas (atualizadas) -->
                <div class="info-card">
                    <h6><i class="bi bi-lightning-charge-fill"></i> Ações Rápidas</h6>
                    <div class="quick-grid">
                        <?php foreach ($quickActions as $qa): ?>
                            <a href="<?= htmlspecialchars($qa['url']) ?>" target="_blank" class="quick-btn q-<?= $qa['color'] ?>">
                                <i class="bi <?= htmlspecialchars($qa['icon']) ?>"></i>
                                <?= htmlspecialchars($qa['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Status do sistema -->
                <div class="info-card">
                    <h6><i class="bi bi-info-circle-fill"></i> Status do Sistema</h6>
                    <div class="info-item">
                        <div class="info-icon i-green"><i class="bi bi-shield-check"></i></div>
                        <div class="info-text">
                            <strong>Acesso Seguro</strong>
                            <span>Sessão autenticada e protegida</span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon i-blue"><i class="bi bi-hdd-network"></i></div>
                        <div class="info-text">
                            <strong>Sistema Online</strong>
                            <span>Todos os serviços operacionais</span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon i-yellow"><i class="bi bi-clock-history"></i></div>
                        <div class="info-text">
                            <strong>Último Acesso</strong>
                            <span><?= date('d/m/Y H:i') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Dica -->
                <div class="info-card">
                    <h6><i class="bi bi-lightbulb-fill"></i> Dica</h6>
                    <p style="font-size:0.82rem;margin:0 0 10px;color:var(--text-color);opacity:0.75;font-weight:500;line-height:1.5;">
                        Todos os links abrem em <strong>nova aba</strong> para você não perder o contexto do painel.
                    </p>
                    <p style="font-size:0.82rem;margin:0;color:var(--text-color);opacity:0.75;font-weight:500;line-height:1.5;">
                        Certifique-se de fazer <strong>logout</strong> ao terminar suas atividades.
                    </p>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="page-footer">
        <p style="margin:0;">
            Sistema Administrativo &copy; <?= date('Y') ?> · <strong>SunnyImob</strong>
            <br><small>v7.0 · Última atualização: <?= date('d/m/Y') ?></small>
        </p>
    </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    window.confirmarLogout = function (event) {
        event.preventDefault();
        Swal.fire({
            title: 'Sair do sistema?',
            text: 'Sua sessão será encerrada.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, sair',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '<?= $themeSafe['primary'] ?>',
            cancelButtonColor: '#6b7280'
        }).then(function (r) {
            if (r.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
        return false;
    };

    document.querySelectorAll('.menu-item').forEach(function (item) {
        item.addEventListener('click', function () {
            this.style.opacity = '0.6';
            setTimeout(() => { this.style.opacity = '1'; }, 800);
        });
    });

    document.querySelectorAll('.section, .info-card, .stat-card').forEach(function (el, i) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(8px)';
        el.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
        setTimeout(() => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, 40 + i * 30);
    });
})();
</script>
</body>
</html>