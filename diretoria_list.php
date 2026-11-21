<?php
// ============================================================
// SESSION PRIMEIRO
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conn.php';
require_once 'theme_config.php';



// ===============================================
// Diretorias — CRUD Completo com Theme System
// Create / Read / Update / Delete em página única
// Inclui quantidade de vendas + quantidade de gerências
// ===============================================

//require_once 'bootstrap.php';
$mensagem = $_GET['mensagem'] ?? '';

// ---- Tema (com fallback inline) ----
$theme_file = __DIR__ . '/theme_config.php';
if (file_exists($theme_file)) require_once $theme_file;
if (!isset($theme) || !is_array($theme)) {
    $theme = [
        'primary'      => '#0d6efd',
        'primary_dark' => '#0a58ca',
        'secondary'    => '#6c757d',
        'accent'       => '#fbbf24',
        'bg_body'      => '#f1f5f9',
        'text_color'   => '#1e293b',
        'card_bg'      => '#ffffff',
        'shadow_color' => '#0f172a',
        'btn_bg'       => '#ffffff',
        'btn_hover'    => '#f3f4f6',
        'btn_text'     => '#1e293b',
    ];
}

// ===============================================
// POST — Salvar (INSERT/UPDATE) e Excluir (DELETE)
// ===============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- SAVE ----------
    if ($action === 'save') {
        $id              = intval($_POST['DiretoriaId'] ?? 0);
        $nomeDiretoria   = trim($_POST['NomeDiretoria'] ?? '');
        $nomeDiretor     = trim($_POST['NomeDiretor'] ?? '');
        $telefoneDiretor = trim($_POST['TelefoneDiretor'] ?? '');
        $userId          = !empty($_POST['UserId']) ? intval($_POST['UserId']) : null;
        $estadoDiretoria = trim($_POST['EstadoDiretoria'] ?? 'ativo');

        if (empty($nomeDiretoria)) {
            $_SESSION['flash_dir'] = ['type' => 'warning', 'msg' => 'O nome da Diretoria é obrigatório.'];
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE diretorias SET 
                        NomeDiretoria   = :nome,
                        NomeDiretor     = :diretor,
                        TelefoneDiretor = :telefone,
                        UserId          = :userid,
                        EstadoDiretoria = :estado
                        WHERE DiretoriaId = :id");
                    $stmt->execute([
                        ':nome'     => $nomeDiretoria,
                        ':diretor'  => $nomeDiretor,
                        ':telefone' => $telefoneDiretor,
                        ':userid'   => $userId,
                        ':estado'   => $estadoDiretoria,
                        ':id'       => $id,
                    ]);
                    $_SESSION['flash_dir'] = ['type' => 'success', 'msg' => 'Diretoria atualizada com sucesso!'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO diretorias 
                        (NomeDiretoria, NomeDiretor, TelefoneDiretor, UserId, EstadoDiretoria) 
                        VALUES (:nome, :diretor, :telefone, :userid, :estado)");
                    $stmt->execute([
                        ':nome'     => $nomeDiretoria,
                        ':diretor'  => $nomeDiretor,
                        ':telefone' => $telefoneDiretor,
                        ':userid'   => $userId,
                        ':estado'   => $estadoDiretoria,
                    ]);
                    $_SESSION['flash_dir'] = ['type' => 'success', 'msg' => 'Diretoria cadastrada com sucesso!'];
                }
            } catch (PDOException $e) {
                $_SESSION['flash_dir'] = ['type' => 'danger', 'msg' => 'Erro ao salvar: ' . $e->getMessage()];
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // ---------- DELETE ----------
    if ($action === 'delete') {
        $id = intval($_POST['DiretoriaId'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM diretorias WHERE DiretoriaId = :id");
                $stmt->execute([':id' => $id]);
                $_SESSION['flash_dir'] = ['type' => 'success', 'msg' => 'Diretoria excluída com sucesso!'];
            } catch (PDOException $e) {
                $_SESSION['flash_dir'] = ['type' => 'danger', 'msg' => 'Não foi possível excluir (registro em uso). Detalhes: ' . $e->getMessage()];
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$flash = $_SESSION['flash_dir'] ?? null;
unset($_SESSION['flash_dir']);

// ===============================================
// CARREGAR DADOS
// ===============================================
try {
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            u.Nome,
            u.Usuario,
            (SELECT COUNT(*) 
             FROM vendas v 
             WHERE v.DiretoriaId = d.DiretoriaId 
               AND (v.Excluido = 0 OR v.Excluido IS NULL)
            ) AS QtdVendas,
            (SELECT COUNT(*) 
             FROM gerencias g 
             WHERE g.DiretoriaId = d.DiretoriaId
            ) AS QtdGerencias
        FROM diretorias d
        LEFT JOIN usuarios u ON d.UserId = u.UserId
        ORDER BY d.NomeDiretoria ASC
    ");
    $stmt->execute();
    $diretorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT UserId, Nome, Usuario FROM usuarios 
                           WHERE Ativo = 1 OR Ativo IS NULL
                           ORDER BY Nome ASC");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Erro na consulta: " . $e->getMessage());
}

$usuarioSessao = $_SESSION['Usuario'] ?? 'Sistema';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGVendas | Diretorias</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <style>
        :root {
            --primary:      <?= $theme['primary'] ?>;
            --primary-dark: <?= $theme['primary_dark'] ?>;
            --secondary:    <?= $theme['secondary'] ?>;
            --accent:       <?= $theme['accent'] ?>;
            --bg-body:      <?= $theme['bg_body'] ?>;
            --text-color:   <?= $theme['text_color'] ?>;
            --card-bg:      <?= $theme['card_bg'] ?>;
            --shadow-color: <?= $theme['shadow_color'] ?>;
            --btn-bg:       <?= $theme['btn_bg'] ?>;
            --btn-hover:    <?= $theme['btn_hover'] ?>;
            --btn-text:     <?= $theme['btn_text'] ?>;

            --success: #16a34a;
            --success-dark: #15803d;
            --success-bg: #f0fdf4;
            --danger: #dc2626;
            --danger-dark: #b91c1c;
            --danger-bg: #fef2f2;
            --warning: #d97706;
            --warning-bg: #fef3c7;
            --muted: #64748b;

            --radius: 12px;
            --radius-sm: 8px;
            --border: 2px solid var(--shadow-color);
            --shadow-sm: 3px 3px 0 var(--shadow-color);
            --shadow-md: 4px 4px 0 var(--shadow-color);
        }

        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }

        body {
            background-color: var(--bg-body);
            color: var(--text-color);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        .app-navbar {
            background: var(--primary);
            border-bottom: var(--border);
            box-shadow: 0 4px 0 var(--shadow-color);
            padding: 0.9rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .app-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #fff;
            font-weight: 900;
            font-size: 1.15rem;
            letter-spacing: -0.02em;
        }
        .app-brand-icon {
            width: 40px; height: 40px;
            border: var(--border);
            border-radius: var(--radius-sm);
            background: var(--accent);
            color: var(--shadow-color);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            box-shadow: var(--shadow-sm);
        }
        .navbar-actions {
            display: flex;
            gap: 0.6rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .nav-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 0.9rem;
            border: var(--border);
            border-radius: var(--radius-sm);
            background: var(--accent);
            color: var(--shadow-color);
            font-size: 0.78rem;
            font-weight: 800;
            box-shadow: var(--shadow-sm);
        }

        .hero {
            background: var(--primary-dark);
            color: #fff;
            padding: 2.25rem 1.5rem;
            border-bottom: var(--border);
        }
        .hero-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .hero h1 {
            font-weight: 900;
            font-size: 2rem;
            margin: 0 0 0.4rem;
            letter-spacing: -0.02em;
            color: #fff;
        }
        .hero p {
            margin: 0;
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            font-weight: 500;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.75rem 1.25rem;
            border: var(--border);
            border-radius: var(--radius);
            background: var(--accent);
            color: var(--shadow-color);
            font-weight: 900;
            font-size: 1.4rem;
            box-shadow: var(--shadow-sm);
        }
        .hero-badge-label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
            opacity: 0.75;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.75rem 1.5rem 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.65rem 1.15rem;
            border: var(--border);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm);
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
            background: var(--btn-bg);
            color: var(--btn-text);
        }
        .btn:hover:not(:disabled) {
            transform: translate(-2px, -2px);
            box-shadow: var(--shadow-md);
        }
        .btn:active:not(:disabled) {
            transform: translate(1px, 1px);
            box-shadow: 1px 1px 0 var(--shadow-color);
        }
        .btn:disabled { opacity: 0.6; cursor: wait; }
        .btn i { font-size: 0.95em; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover:not(:disabled) { background: var(--primary-dark); }
        .btn-secondary { background: var(--btn-bg); color: var(--btn-text); }
        .btn-secondary:hover:not(:disabled) { background: var(--btn-hover); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover:not(:disabled) { background: var(--success-dark); }
        .btn-danger  { background: var(--danger);  color: #fff; }
        .btn-danger:hover:not(:disabled)  { background: var(--danger-dark); }
        .btn-sm { padding: 0.4rem 0.75rem; font-size: 0.75rem; }

        .card {
            background: var(--card-bg);
            border: var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.4rem;
            background: var(--primary);
            color: #fff;
            border-bottom: var(--border);
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .card-header .card-header-title {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .card-header .count-badge {
            background: var(--accent);
            color: var(--shadow-color);
            padding: 0.2rem 0.65rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 900;
            border: 2px solid var(--shadow-color);
        }

        .flash {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border: var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
            font-weight: 500;
            animation: slideIn 0.25s ease;
        }
        .flash i { font-size: 1.15rem; flex-shrink: 0; margin-top: 1px; }
        .flash-success { background: var(--success-bg); color: #166534; border-color: var(--success-dark); }
        .flash-success i { color: var(--success); }
        .flash-danger  { background: var(--danger-bg); color: #7f1d1d; border-color: var(--danger); }
        .flash-danger i { color: var(--danger); }
        .flash-warning { background: var(--warning-bg); color: #78350f; border-color: var(--warning); }
        .flash-warning i { color: var(--warning); }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .table-responsive { overflow-x: auto; }
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            background: var(--card-bg);
        }
        .table thead th {
            background: var(--primary);
            color: #fff;
            padding: 0.75rem 0.9rem;
            text-align: left;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid var(--shadow-color);
            white-space: nowrap;
        }
        .table thead th.text-center { text-align: center; }
        .table tbody td {
            padding: 0.7rem 0.9rem;
            border: 1px solid var(--shadow-color);
            vertical-align: middle;
            color: var(--text-color);
            font-weight: 500;
        }
        .table tbody tr:nth-child(even) td { background: rgba(0,0,0,0.02); }
        .table tbody tr:hover td { background: rgba(13,110,253,0.06); }

        .table .cell-id {
            text-align: center;
            font-weight: 900;
            color: var(--muted);
        }
        .table .cell-nome {
            font-weight: 800;
            color: var(--text-color);
        }
        .table .cell-muted { color: var(--muted); font-size: 0.82rem; }

        /* Badges de quantidade */
        .badge-qtd {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
            border: 1.5px solid;
            margin-left: 0.4rem;
            white-space: nowrap;
        }
        .badge-gerencias {
            background: #e0f2fe;
            color: #0369a1;
            border-color: #7dd3fc;
        }
        .badge-vendas {
            background: #dcfce7;
            color: #15803d;
            border-color: #86efac;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.7rem;
            border: 2px solid var(--shadow-color);
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            box-shadow: 2px 2px 0 var(--shadow-color);
            white-space: nowrap;
        }
        .badge-ativo   { background: var(--success); color: #fff; }
        .badge-inativo { background: var(--secondary); color: #fff; }

        .avatar-user {
            width: 34px; height: 34px;
            border: var(--border);
            border-radius: var(--radius-sm);
            background: var(--accent);
            color: var(--shadow-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            margin-right: 0.65rem;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
        }
        .user-cell {
            display: flex;
            align-items: center;
            gap: 0.15rem;
        }
        .user-name {
            font-weight: 700;
            font-size: 0.85rem;
            line-height: 1.2;
            color: var(--text-color);
        }
        .user-login {
            font-size: 0.72rem;
            color: var(--muted);
            font-weight: 500;
        }

        .actions {
            display: inline-flex;
            gap: 0.35rem;
            flex-wrap: nowrap;
        }
        .actions .btn {
            padding: 0.35rem 0.55rem;
            font-size: 0.78rem;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(2px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
        }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .modal-box {
            background: var(--card-bg);
            border: var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            width: 100%;
            max-width: 540px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: popIn 0.2s ease;
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.95); }
            to   { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            background: var(--primary);
            color: #fff;
            padding: 1rem 1.4rem;
            border-bottom: var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .modal-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .modal-close {
            background: var(--accent);
            border: var(--border);
            border-radius: var(--radius-sm);
            color: var(--shadow-color);
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: all 0.15s;
            font-size: 0.85rem;
        }
        .modal-close:hover {
            transform: translate(-1px, -1px);
            box-shadow: var(--shadow-md);
        }

        .modal-body {
            padding: 1.5rem 1.4rem;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 1rem 1.4rem;
            border-top: var(--border);
            background: var(--bg-body);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .modal-footer .footer-right {
            display: flex;
            gap: 0.6rem;
            margin-left: auto;
        }

        .form-group { margin-bottom: 1.1rem; }
        .form-group:last-child { margin-bottom: 0; }

        .form-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            margin-bottom: 0.4rem;
        }
        .form-label .req { color: var(--danger); margin-left: 2px; }

        .form-control,
        .form-select {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: var(--border);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-color);
            background: #fff;
            transition: all 0.15s;
        }
        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
        }
        .form-control::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        .empty-row td {
            text-align: center;
            padding: 2.5rem 1rem;
            color: var(--muted);
            font-style: italic;
        }
        .empty-row i {
            display: block;
            font-size: 2rem;
            opacity: 0.4;
            margin-bottom: 0.6rem;
        }

        .dataTables_wrapper {
            padding: 0.75rem 1rem 1rem;
        }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            border: var(--border) !important;
            border-radius: var(--radius-sm) !important;
            padding: 0.4rem 0.7rem !important;
            font-family: inherit !important;
            font-weight: 600 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 2px solid var(--shadow-color) !important;
            border-radius: var(--radius-sm) !important;
            background: var(--card-bg) !important;
            color: var(--text-color) !important;
            font-weight: 700 !important;
            padding: 0.3rem 0.7rem !important;
            margin: 0 0.15rem !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: #fff !important;
        }

        .app-footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--muted);
            font-size: 0.78rem;
            border-top: var(--border);
            background: var(--card-bg);
        }

        @media (max-width: 768px) {
            .hero { padding: 1.5rem 1rem; }
            .hero h1 { font-size: 1.4rem; }
            .hero-badge { font-size: 1.1rem; padding: 0.6rem 1rem; }
            .page-container { padding: 1.15rem 0.75rem; }
            .actions .btn { padding: 0.3rem 0.45rem; font-size: 0.72rem; }
            .table { font-size: 0.75rem; }
            .table thead th, .table tbody td { padding: 0.5rem 0.4rem; }
            .modal-footer { flex-direction: column-reverse; }
            .modal-footer .footer-right { width: 100%; margin-left: 0; }
            .modal-footer .footer-right .btn { flex: 1; }
        }
    </style>
</head>
<body>

<nav class="app-navbar">
    <a class="app-brand" href="#">
        <div class="app-brand-icon"><i class="fas fa-building"></i></div>
        SGVendas
    </a>
    <div class="navbar-actions">
        <div class="nav-badge">
            <i class="fas fa-user-circle"></i>
            <?= htmlspecialchars($usuarioSessao) ?>
        </div>
        <button type="button" onclick="fecharAba()" class="btn btn-secondary btn-sm">
            <i class="fas fa-times"></i> Fechar Aba
        </button>
    </div>
</nav>

<section class="hero">
    <div class="hero-inner">
        <div>
            <h1><i class="fas fa-crown me-2"></i>Diretorias</h1>
            <p>Gestão de unidades e lideranças SGVendas</p>
        </div>
        <div class="hero-badge">
            <div>
                <div class="hero-badge-label">Total</div>
                <div><?= count($diretorias) ?></div>
            </div>
        </div>
    </div>
</section>

<div class="page-container">

    <?php if ($flash): ?>
    <div class="flash flash-<?= htmlspecialchars($flash['type']) ?>">
        <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : ($flash['type'] === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
        <div><?= htmlspecialchars($flash['msg']) ?></div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="card-header-title">
                <i class="fas fa-list"></i>
                <span>Lista de Diretorias</span>
                <span class="count-badge"><?= count($diretorias) ?></span>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="btnNova">
                <i class="fas fa-plus"></i> Nova Diretoria
            </button>
        </div>

        <div class="table-responsive">
            <table id="tabelaDiretorias" class="table" style="width:100%">
                <thead>
                    <tr>
                        <th class="text-center">ID</th>
                        <th>Nome da Diretoria</th>
                        <th>Diretor Responsável</th>
                        <th>Contato</th>
                        <th>Usuário Sistema</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center" style="width:100px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($diretorias)): ?>
                    <tr class="empty-row">
                        <td colspan="7">
                            <i class="fas fa-inbox"></i>
                            Nenhuma diretoria cadastrada ainda.
                            Clique em <strong>"Nova Diretoria"</strong> para começar.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($diretorias as $d):
                        $status = strtolower($d['EstadoDiretoria'] ?? 'ativo');
                        $isAtivo = ($status === 'ativo' || $status === 'a' || $status === '1');
                        $qtdVendas = (int)($d['QtdVendas'] ?? 0);
                        $qtdGerencias = (int)($d['QtdGerencias'] ?? 0);
                    ?>
                    <tr>
                        <td class="cell-id">#<?= (int)$d['DiretoriaId'] ?></td>
                        <td class="cell-nome">
                            <?= htmlspecialchars($d['NomeDiretoria']) ?>

                            <!-- Badge Gerências -->
                            <span class="badge-qtd badge-gerencias" title="Quantidade de Gerências">
                                <i class="fas fa-sitemap"></i> <?= $qtdGerencias ?>
                            </span>

                            <!-- Badge Vendas -->
                            <span class="badge-qtd badge-vendas" title="Quantidade de Vendas">
                                <i class="fas fa-shopping-cart"></i> <?= $qtdVendas ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($d['NomeDiretor'] ?? 'Não definido') ?></td>
                        <td class="cell-muted">
                            <i class="fas fa-phone me-1"></i>
                            <?= htmlspecialchars($d['TelefoneDiretor'] ?? '-') ?>
                        </td>
                        <td>
                            <div class="user-cell">
                                <div class="avatar-user"><i class="fas fa-user-alt"></i></div>
                                <div>
                                    <div class="user-name"><?= htmlspecialchars($d['Nome'] ?? 'N/A') ?></div>
                                    <div class="user-login"><?= htmlspecialchars(strtolower($d['Usuario'] ?? '')) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge-status <?= $isAtivo ? 'badge-ativo' : 'badge-inativo' ?>">
                                <i class="fas <?= $isAtivo ? 'fa-check' : 'fa-minus' ?>"></i>
                                <?= $isAtivo ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="actions">
                                <button type="button"
                                        class="btn btn-secondary btn-edit"
                                        title="Editar"
                                        data-id="<?= (int)$d['DiretoriaId'] ?>"
                                        data-nome="<?= htmlspecialchars($d['NomeDiretoria'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-diretor="<?= htmlspecialchars($d['NomeDiretor'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-telefone="<?= htmlspecialchars($d['TelefoneDiretor'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-userid="<?= htmlspecialchars($d['UserId'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-estado="<?= htmlspecialchars($d['EstadoDiretoria'] ?? 'ativo', ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button"
                                        class="btn btn-danger btn-delete"
                                        title="Excluir"
                                        data-id="<?= (int)$d['DiretoriaId'] ?>"
                                        data-nome="<?= htmlspecialchars($d['NomeDiretoria'], ENT_QUOTES, 'UTF-8') ?>">
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
    </div>

</div>

<!-- MODAL CRUD -->
<div class="modal-overlay" id="modalDiretoria">
    <div class="modal-box">
        <form method="post" id="formDiretoria" autocomplete="off">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="DiretoriaId" id="DiretoriaId" value="0">

            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">
                    <i class="fas fa-plus-circle"></i>
                    <span>Nova Diretoria</span>
                </h3>
                <button type="button" class="modal-close" onclick="fecharModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="NomeDiretoria" class="form-label">
                        Nome da Diretoria <span class="req">*</span>
                    </label>
                    <input type="text" class="form-control" id="NomeDiretoria" name="NomeDiretoria"
                           required maxlength="100" placeholder="Ex: Diretoria SP">
                </div>

                <div class="form-group">
                    <label for="NomeDiretor" class="form-label">Diretor Responsável</label>
                    <input type="text" class="form-control" id="NomeDiretor" name="NomeDiretor"
                           maxlength="100" placeholder="Ex: João Silva">
                </div>

                <div class="form-group">
                    <label for="TelefoneDiretor" class="form-label">Telefone</label>
                    <input type="text" class="form-control" id="TelefoneDiretor" name="TelefoneDiretor"
                           maxlength="30" placeholder="(11) 99999-9999">
                </div>

                <div class="form-group">
                    <label for="UserId" class="form-label">Usuário do Sistema</label>
                    <select class="form-select" id="UserId" name="UserId">
                        <option value="">-- Nenhum --</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= (int)$u['UserId'] ?>">
                                <?= htmlspecialchars($u['Nome'] ?: $u['Usuario']) ?>
                                <?= $u['Usuario'] ? ' (' . htmlspecialchars($u['Usuario']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="EstadoDiretoria" class="form-label">Estado</label>
                    <select class="form-select" id="EstadoDiretoria" name="EstadoDiretoria" required>
                        <option value="ativo">Ativo</option>
                        <option value="inativo">Inativo</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm d-none" id="btnDeleteModal">
                    <i class="fas fa-trash"></i> Excluir
                </button>
                <div class="footer-right">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnSalvar">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- FORM OCULTO PARA DELETE -->
<form method="post" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="DiretoriaId" id="deleteId">
</form>

<footer class="app-footer">
    &copy; <?= date('Y') ?> SunnyImob | SGVendas — Valter Barreto
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
function abrirModal() {
    document.getElementById('modalDiretoria').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function fecharModal() {
    document.getElementById('modalDiretoria').classList.remove('active');
    document.body.style.overflow = '';
}
document.getElementById('modalDiretoria').addEventListener('click', function (e) {
    if (e.target === this) fecharModal();
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fecharModal();
});

function fecharAba() {
    try { window.open('', '_self', ''); window.close(); } catch (e) {}
    setTimeout(function () {
        if (!window.closed) window.location.href = 'gestao_vendas_list3x.php';
    }, 350);
}

$(document).ready(function () {
    if ($('#tabelaDiretorias tbody tr:not(.empty-row)').length > 0) {
        $('#tabelaDiretorias').DataTable({
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
            pageLength: 15,
            responsive: true,
            order: [[0, 'desc']],
            columnDefs: [
                { targets: -1, orderable: false, searchable: false }
            ]
        });
    }

    $('#btnNova').on('click', function () {
        $('#formDiretoria')[0].reset();
        $('#DiretoriaId').val(0);
        $('#modalTitle').html('<i class="fas fa-plus-circle"></i><span>Nova Diretoria</span>');
        $('#btnDeleteModal').addClass('d-none');
        $('#btnSalvar').prop('disabled', false).html('<i class="fas fa-save"></i> Salvar');
        abrirModal();
        setTimeout(() => $('#NomeDiretoria').focus(), 200);
    });

    $(document).on('click', '.btn-edit', function () {
        const $b = $(this);
        $('#DiretoriaId').val($b.data('id'));
        $('#NomeDiretoria').val($b.data('nome'));
        $('#NomeDiretor').val($b.data('diretor'));
        $('#TelefoneDiretor').val($b.data('telefone'));
        $('#UserId').val(String($b.data('userid') || ''));
        $('#EstadoDiretoria').val($b.data('estado') || 'ativo');
        $('#modalTitle').html('<i class="fas fa-edit"></i><span>Editar Diretoria</span>');
        $('#btnDeleteModal').removeClass('d-none');
        $('#btnSalvar').prop('disabled', false).html('<i class="fas fa-save"></i> Salvar');
        abrirModal();
    });

    $(document).on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        const nome = $(this).data('nome');
        if (confirm('Excluir a Diretoria "' + nome + '"?\n\n⚠ Esta ação não pode ser desfeita.')) {
            $('#deleteId').val(id);
            $('#deleteForm').submit();
        }
    });

    $('#btnDeleteModal').on('click', function () {
        const id = $('#DiretoriaId').val();
        const nome = $('#NomeDiretoria').val();
        if (confirm('Excluir a Diretoria "' + nome + '"?\n\n⚠ Esta ação não pode ser desfeita.')) {
            $('#deleteId').val(id);
            $('#deleteForm').submit();
        }
    });

    $('#formDiretoria').on('submit', function () {
        if (!$('#NomeDiretoria').val().trim()) {
            alert('Informe o nome da Diretoria.');
            $('#NomeDiretoria').focus();
            return false;
        }
        $('#btnSalvar').prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin"></i> Salvando...');
        return true;
    });
});
</script>

</body>
</html>