<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php'; // <-- TEMA

// ============================================================
// FUNÇÕES AUXILIARES
// ============================================================
function getPermissaoTexto($codigo) {
    $map = [
        1 => "SUPERADMIN", 2 => "ADMIN", 3 => "GESTOR-EDITOR",
        4 => "EDITOR", 5 => "CORRETOR", 6 => "CORRETOR-EDITOR",
        7 => "VISUALIZADOR"
    ];
    return $map[$codigo] ?? "DESCONHECIDO";
}

// Fallback de tema (caso theme_config não defina algumas variáveis)
$theme = array_merge([
    'primary'      => '#0d6efd',
    'primary_dark' => '#0a58ca',
    'secondary'    => '#6610f2',
    'accent'       => '#ffd43b',
    'bg_body'      => '#f5f7fa',
    'text_color'   => '#1f2937',
    'card_bg'      => '#ffffff',
    'shadow_color' => '#111827',
    'btn_bg'       => '#0d6efd',
    'btn_hover'    => '#0a58ca',
    'btn_text'     => '#ffffff',
], $theme ?? []);

// ============================================================
// LIMPA DADOS DA SESSÃO NO CARREGAMENTO INICIAL (GET)
// ============================================================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    unset($_SESSION['form_data']);
    unset($_SESSION['MsgErro']);
}

// ============================================================
// PROCESSAMENTO DO FORMULÁRIO (POST)
// ============================================================
$erros = [];
$form_data = $_SESSION['form_data'] ?? [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario   = trim($_POST['Usuario']   ?? '');
    $senha     = trim($_POST['Senha']     ?? '');
    $email     = trim($_POST['Email']     ?? '');
    $telefones = trim($_POST['Telefones'] ?? '');
    $permissao = $_POST['Permissao']      ?? '';
    $id_grupo  = trim($_POST['ID_Grupo']  ?? '');
    $id_emp    = trim($_POST['idEmp']     ?? '');
    $nome      = trim($_POST['Nome']      ?? '');
    $creci     = trim($_POST['CRECI']     ?? '');
    $ativo     = isset($_POST['Ativo']) ? 1 : 0;

    $funcao = trim($_POST['Funcao'] ?? '');
    $acoes  = trim($_POST['Acoes']  ?? '');

    if (empty($funcao) || empty($acoes)) {
        $map = [
            '1' => ['funcao' => 'SUPERADMIN',     'acoes' => '/GESTAO/SISTEMA/USUARIOS/BACKUP/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'],
            '2' => ['funcao' => 'ADMIN',           'acoes' => '/USUARIOS/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'],
            '3' => ['funcao' => 'GESTOR-EDITOR',   'acoes' => '/GESTOR/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'],
            '4' => ['funcao' => 'EDITOR',          'acoes' => '/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/'],
            '5' => ['funcao' => 'CORRETOR',        'acoes' => '/CONSULTAR/'],
            '6' => ['funcao' => 'CORRETOR-EDITOR', 'acoes' => '/CONSULTAR/EDITAR/'],
            '7' => ['funcao' => 'VISUALIZADOR',    'acoes' => '/CONSULTAR/'],
        ];
        if (isset($map[$permissao])) {
            $funcao = $map[$permissao]['funcao'];
            $acoes  = $map[$permissao]['acoes'];
        }
    }

    if (empty($usuario) || empty($senha) || empty($permissao) ||
        empty($id_grupo) || empty($id_emp) || empty($nome)) {
        $erros[] = "Todos os campos obrigatórios devem ser preenchidos.";
    }
    if (!is_numeric($permissao) || $permissao < 1 || $permissao > 7) {
        $erros[] = "Nível de permissão inválido.";
    }
    if (!is_numeric($id_grupo) || (int)$id_grupo <= 0) {
        $erros[] = "Selecione um grupo válido.";
    }
    if (!is_numeric($id_emp) || (int)$id_emp <= 0) {
        $erros[] = "Selecione uma empresa válida.";
    }

    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT 1 FROM usuarios WHERE Usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->fetch()) {
            $erros[] = "Este nome de usuário já está cadastrado.";
        }
    }

    $empresa_nome = "";
    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT EmpresaSunny FROM sunnyempresas WHERE IdEmp = ?");
        $stmt->execute([(int)$id_emp]);
        if ($row = $stmt->fetch()) {
            $empresa_nome = $row['EmpresaSunny'];
        } else {
            $erros[] = "Empresa selecionada não encontrada.";
        }
    }

    $nome_grupo = "";
    if (empty($erros)) {
        $stmt = $conn->prepare("SELECT Nome_Grupo FROM grupo WHERE ID_Grupo = ?");
        $stmt->execute([(int)$id_grupo]);
        if ($row = $stmt->fetch()) {
            $nome_grupo = $row['Nome_Grupo'];
        } else {
            $erros[] = "Grupo selecionado não encontrado.";
        }
    }

    if (empty($erros)) {
        try {
            $conn->beginTransaction();

            $sql = "INSERT INTO usuarios (
                Usuario, SenhaAtual, Nome, CRECI, Email, Telefones,
                Acoes, Permissao, Funcao, Ativo, idEmp, Empresa,
                GrupoId, Grupo, Data, UsuarioCadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $usuario, $senha, $nome, $creci, $email, $telefones,
                $acoes, (int)$permissao, $funcao, $ativo,
                (int)$id_emp, $empresa_nome, (int)$id_grupo, $nome_grupo,
                $_SESSION['Usuario'] ?? 'SISTEMA'
            ]);

            $new_user_id = $conn->lastInsertId();

            $stmt = $conn->prepare("INSERT INTO usuario_grupo (UserId, ID_Grupo) VALUES (?, ?)");
            $stmt->execute([$new_user_id, (int)$id_grupo]);

            $conn->commit();

            unset($_SESSION['form_data']);
            $_SESSION['MsgSucesso'] = "Usuário criado com sucesso!";
            header("Location: usr_listar.php");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $erros[] = "Erro ao criar usuário: " . $e->getMessage();
        }
    }

    if (!empty($erros)) {
        $_SESSION['MsgErro'] = implode("<br>", $erros);
        $_SESSION['form_data'] = $_POST;
        $form_data = $_POST;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo Usuário | Sunny</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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

            --radius: 12px;
            --radius-sm: 8px;
            --border: 2.5px solid var(--shadow-color);
            --shadow-sm: 3px 3px 0 var(--shadow-color);
            --shadow-md: 4px 4px 0 var(--shadow-color);
        }

        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }

        body {
            background-color: var(--bg-body);
            color: var(--text-color);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            padding: 1.5rem;
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .app-wrap { max-width: 1320px; margin: 0 auto; }

        /* ================= HEADER ================= */
        .app-header {
            background: var(--card-bg);
            border: var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 1.15rem 1.6rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .header-left { display: flex; align-items: center; gap: 1rem; min-width: 0; }
        .header-icon {
            width: 52px; height: 52px;
            border: var(--border);
            border-radius: var(--radius-sm);
            background: var(--primary);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }
        .app-header h1 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 900;
            color: var(--text-color);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .app-header p {
            margin: 0.15rem 0 0;
            color: #6b7280;
            font-size: 0.78rem;
            font-weight: 500;
        }
        .header-right { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }

        /* ================= BOTÕES ================= */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.55rem 1.1rem;
            border: var(--border);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm);
            font-weight: 800;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            background: var(--btn-bg);
            color: var(--btn-text);
            font-family: inherit;
            white-space: nowrap;
        }
        .btn:hover { transform: translate(-2px, -2px); box-shadow: var(--shadow-md); }
        .btn:active { transform: translate(1px, 1px); box-shadow: 1px 1px 0 var(--shadow-color); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: #fff; color: var(--text-color); }
        .btn-secondary:hover { background: #f3f4f6; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-user {
            background: var(--accent);
            color: var(--shadow-color);
            cursor: default;
            text-transform: none;
            letter-spacing: 0;
        }
        .btn-user:hover { transform: none; box-shadow: var(--shadow-sm); }

        /* ================= ALERTA ERRO ================= */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.9rem 1.1rem;
            border: var(--border);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.25rem;
            font-weight: 600;
        }
        .alert-danger { background: #fee2e2; color: #7f1d1d; border-color: #991b1b; box-shadow: 3px 3px 0 #991b1b; }
        .alert i { font-size: 1.15rem; margin-top: 1px; flex-shrink: 0; }
        .alert-body { flex: 1; }
        .alert-close {
            background: none; border: none; cursor: pointer;
            font-size: 1.15rem; color: inherit; padding: 0;
            line-height: 1; opacity: 0.7; transition: opacity 0.15s;
        }
        .alert-close:hover { opacity: 1; }

        /* ================= LAYOUT ================= */
        .form-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 1100px) {
            .form-layout { grid-template-columns: 1fr; }
        }
        .form-main { min-width: 0; }

        /* ================= SEÇÕES ================= */
        .section {
            background: var(--card-bg);
            border: var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 1.25rem;
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }
        .section:hover { box-shadow: 5px 5px 0 var(--shadow-color); }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.8rem 1.2rem;
            background: var(--primary);
            color: #fff;
            border-bottom: var(--border);
            font-weight: 800;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .section-header h2 {
            margin: 0;
            font-size: inherit;
            font-weight: inherit;
            color: inherit;
            flex: 1;
        }
        .section-header i { font-size: 0.95rem; opacity: 0.95; }
        .section-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px; height: 22px;
            border-radius: 50%;
            background: #fff;
            color: var(--primary);
            font-size: 0.72rem;
            font-weight: 900;
            border: 2px solid var(--shadow-color);
            flex-shrink: 0;
        }
        .section-body { padding: 1.25rem 1.4rem; }

        /* ================= GRID INTERNO ================= */
        .row { display: grid; gap: 1rem; margin-bottom: 1rem; }
        .row:last-child { margin-bottom: 0; }
        .row.cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .row.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        @media (max-width: 720px) {
            .row.cols-2, .row.cols-3 { grid-template-columns: 1fr; }
        }

        /* ================= CAMPOS ================= */
        .field { min-width: 0; }
        .field label {
            display: block;
            font-weight: 700;
            font-size: 0.7rem;
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #4b5563;
        }
        .field label .req { color: #ef4444; margin-left: 2px; }

        .field input[type="text"],
        .field input[type="email"],
        .field input[type="password"],
        .field select,
        .field textarea {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border: var(--border);
            border-radius: var(--radius-sm);
            background: #fff;
            color: var(--text-color);
            font-size: 0.9rem;
            font-weight: 500;
            font-family: inherit;
            transition: all 0.15s;
        }
        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 3px 3px 0 var(--shadow-color);
        }
        .field input::placeholder,
        .field textarea::placeholder { color: #a3aab4; font-weight: 400; }
        .field input[readonly],
        .field textarea[readonly] {
            background: #f8fafc;
            cursor: default;
            color: #4b5563;
        }
        .field textarea { resize: vertical; min-height: 78px; font-family: 'SF Mono', Monaco, monospace; font-size: 0.78rem; line-height: 1.5; }

        /* ================= INPUT GROUP ================= */
        .input-group {
            display: flex;
            align-items: stretch;
            border: var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: #fff;
            transition: all 0.15s;
        }
        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 3px 3px 0 var(--shadow-color);
        }
        .input-group input {
            flex: 1;
            min-width: 0;
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            background: transparent;
            padding: 0.65rem 0.9rem;
            font-size: 0.9rem;
            font-weight: 500;
            font-family: inherit;
            color: var(--text-color);
            outline: none;
        }
        .input-group .prefix {
            display: flex;
            align-items: center;
            padding: 0 0.85rem;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .input-group .suffix {
            display: flex;
            align-items: center;
            padding: 0 0.75rem;
            background: #f1f5f9;
            color: #64748b;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
            border-left: 2px solid var(--shadow-color);
            cursor: pointer;
            transition: all 0.15s;
        }
        .input-group .suffix:hover { background: var(--primary); color: #fff; }

        /* ================= CHECKBOX SWITCH ================= */
        .switch {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            cursor: pointer;
            user-select: none;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .switch input { display: none; }
        .switch-track {
            width: 46px; height: 26px;
            border: var(--border);
            border-radius: 999px;
            background: #e5e7eb;
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
            box-shadow: 2px 2px 0 var(--shadow-color);
        }
        .switch-track::after {
            content: "";
            position: absolute;
            top: 2px; left: 2px;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid var(--shadow-color);
            transition: transform 0.2s;
        }
        .switch input:checked + .switch-track { background: #16a34a; }
        .switch input:checked + .switch-track::after { transform: translateX(20px); }

        /* ================= BADGE PERMISSÃO ================= */
        .perm-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 var(--shadow-color);
            font-weight: 800;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .perm-badge.superadmin     { background: #dc2626; color: #fff; }
        .perm-badge.admin          { background: #f59e0b; color: #1f2937; }
        .perm-badge.gestor-editor  { background: #06b6d4; color: #1f2937; }
        .perm-badge.editor         { background: #2563eb; color: #fff; }
        .perm-badge.corretor       { background: #6b7280; color: #fff; }
        .perm-badge.corretor-editor{ background: #7c3aed; color: #fff; }
        .perm-badge.visualizador   { background: #16a34a; color: #fff; }
        .perm-badge.empty          { background: #e5e7eb; color: #6b7280; }

        /* ================= SIDEBAR PREVIEW ================= */
        .form-summary {
            min-width: 0;
            position: sticky;
            top: 1.5rem;
        }
        .summary-card {
            background: var(--card-bg);
            border: var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .summary-header {
            background: var(--accent);
            color: var(--shadow-color);
            border-bottom: var(--border);
            padding: 0.95rem 1.2rem;
            font-weight: 900;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .summary-body { padding: 1.15rem; }

        .avatar-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem 0 1.15rem;
            border-bottom: 2px dashed #e5e7eb;
            margin-bottom: 1.15rem;
        }
        .avatar-circle {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            border: var(--border);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: 900;
            margin-bottom: 0.7rem;
        }
        .avatar-name {
            font-weight: 800;
            font-size: 0.95rem;
            color: var(--text-color);
            text-align: center;
            line-height: 1.2;
            word-break: break-word;
        }
        .avatar-creci {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.2rem;
            font-weight: 500;
        }

        .preview-field {
            margin-bottom: 0.85rem;
        }
        .preview-field:last-child { margin-bottom: 0; }
        .preview-label {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .preview-label i { color: var(--primary); }
        .preview-value {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--text-color);
            padding: 0.5rem 0.7rem;
            background: #f8fafc;
            border: 1.5px solid #e5e7eb;
            border-radius: var(--radius-sm);
            word-break: break-all;
            line-height: 1.35;
        }
        .preview-value.mono {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
        }
        .preview-value.empty {
            color: #94a3b8;
            font-style: italic;
            font-weight: 500;
        }
        .preview-value.password-value {
            color: #7c3aed;
            font-family: 'SF Mono', Monaco, monospace;
        }

        .strength-meter {
            margin-top: 0.4rem;
            height: 6px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
            border: 1.5px solid var(--shadow-color);
        }
        .strength-meter-bar {
            height: 100%;
            width: 0;
            transition: width 0.25s, background 0.25s;
            background: #dc2626;
        }

        .summary-divider {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0.9rem 0 0.75rem;
            color: #94a3b8;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .summary-divider::before,
        .summary-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 var(--shadow-color);
            font-weight: 800;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-pill.active   { background: #16a34a; color: #fff; }
        .status-pill.inactive { background: #6b7280; color: #fff; }

        /* ================= AÇÃO INFERIOR ================= */
        .action-bar {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px dashed #cbd5e0;
        }

        /* ================= HELPER ================= */
        .helper-text {
            font-size: 0.72rem;
            color: #94a3b8;
            margin-top: 0.4rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* ================= RESPONSIVO ================= */
        @media (max-width: 640px) {
            body { padding: 0.75rem; }
            .app-header { padding: 1rem 1.15rem; }
            .app-header h1 { font-size: 1.15rem; }
            .header-icon { width: 44px; height: 44px; font-size: 1.15rem; }
            .section-body { padding: 1rem; }
            .header-right { width: 100%; justify-content: flex-end; }
        }
    </style>
</head>
<body>

<div class="app-wrap">

    <!-- ================= HEADER ================= -->
    <header class="app-header">
        <div class="header-left">
            <div class="header-icon"><i class="fas fa-user-plus"></i></div>
            <div>
                <h1>Novo Usuário</h1>
                <p>Cadastro de usuário com credenciais e permissões automáticas</p>
            </div>
        </div>
        <div class="header-right">
            <a href="usr_listar.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> Lista
            </a>
            <div class="btn btn-user">
                <i class="fas fa-user"></i>
                <?php echo htmlspecialchars($_SESSION['Usuario'] ?? ''); ?>
            </div>
        </div>
    </header>

    <?php if (isset($_SESSION['MsgErro'])): ?>
    <div class="alert alert-danger" id="alertErro">
        <i class="fas fa-circle-exclamation"></i>
        <div class="alert-body"><?= $_SESSION['MsgErro'] ?></div>
        <button type="button" class="alert-close" onclick="document.getElementById('alertErro').remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- ================= FORM + PREVIEW ================= -->
    <form method="post" class="form-layout" id="formUsuario" novalidate>

        <!-- ========== COLUNA ESQUERDA (FORM) ========== -->
        <main class="form-main">

            <!-- 1 · DADOS PESSOAIS -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">1</span>
                    <i class="fas fa-user-tie"></i>
                    <h2>Dados Pessoais</h2>
                </div>
                <div class="section-body">
                    <div class="row cols-3">
                        <div class="field" style="grid-column: span 2;">
                            <label for="Nome">Nome completo <span class="req">*</span></label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-user"></i></span>
                                <input type="text" id="Nome" name="Nome" required
                                       placeholder="Ex: João da Silva"
                                       value="<?= htmlspecialchars($form_data['Nome'] ?? '') ?>">
                            </div>
                            <div class="helper-text">
                                <i class="fas fa-wand-magic-sparkles"></i>
                                Usuário e senha são gerados automaticamente ao digitar
                            </div>
                        </div>
                        <div class="field">
                            <label for="CRECI">CRECI</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-id-card"></i></span>
                                <input type="text" id="CRECI" name="CRECI"
                                       placeholder="Ex: 12345-F"
                                       value="<?= htmlspecialchars($form_data['CRECI'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row cols-2">
                        <div class="field">
                            <label for="Email">E-mail</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-envelope"></i></span>
                                <input type="email" id="Email" name="Email"
                                       placeholder="usuario@empresa.com.br"
                                       value="<?= htmlspecialchars($form_data['Email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="field">
                            <label for="Telefones">Telefones</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-phone"></i></span>
                                <input type="text" id="Telefones" name="Telefones"
                                       placeholder="(21) 99999-9999"
                                       value="<?= htmlspecialchars($form_data['Telefones'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2 · CREDENCIAIS DE ACESSO -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">2</span>
                    <i class="fas fa-key"></i>
                    <h2>Credenciais de Acesso</h2>
                </div>
                <div class="section-body">
                    <div class="row cols-2">
                        <div class="field">
                            <label for="Usuario">Usuário <span class="req">*</span></label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-at"></i></span>
                                <input type="text" id="Usuario" name="Usuario" required
                                       placeholder="login do usuário"
                                       value="<?= htmlspecialchars($form_data['Usuario'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="field">
                            <label for="Senha">Senha inicial <span class="req">*</span></label>
                            <div class="input-group">
                                <span class="prefix"><i class="fas fa-lock"></i></span>
                                <input type="password" id="Senha" name="Senha" required
                                       placeholder="senha inicial"
                                       value="<?= htmlspecialchars($form_data['Senha'] ?? '') ?>">
                                <span class="suffix" id="togglePassword" title="Mostrar/ocultar senha">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row cols-2">
                        <div class="field">
                            <label for="idEmp">Empresa <span class="req">*</span></label>
                            <select id="idEmp" name="idEmp" required>
                                <option value="" disabled <?= empty($form_data['idEmp']) ? 'selected' : '' ?>>Selecione...</option>
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT IdEmp, EmpresaSunny FROM sunnyempresas ORDER BY EmpresaSunny");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $sel = ($form_data['idEmp'] ?? '') == $row['IdEmp'] ? 'selected' : '';
                                        echo "<option value='{$row['IdEmp']}' $sel>" . htmlspecialchars($row['EmpresaSunny']) . "</option>";
                                    }
                                } catch (Exception $e) {
                                    echo "<option value=''>Erro ao carregar empresas</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="ID_Grupo">Grupo <span class="req">*</span></label>
                            <select id="ID_Grupo" name="ID_Grupo" required onchange="atualizarPreview()">
                                <option value="" disabled <?= empty($form_data['ID_Grupo']) ? 'selected' : '' ?>>Selecione...</option>
                                <?php
                                try {
                                    $stmt = $conn->query("SELECT ID_Grupo, Nome_Grupo FROM grupo ORDER BY Nome_Grupo");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $sel = ($form_data['ID_Grupo'] ?? '') == $row['ID_Grupo'] ? 'selected' : '';
                                        echo "<option value='{$row['ID_Grupo']}' $sel>" . htmlspecialchars($row['Nome_Grupo']) . "</option>";
                                    }
                                } catch (Exception $e) {
                                    echo "<option value=''>Erro ao carregar grupos</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 3 · PERMISSÕES -->
            <section class="section">
                <div class="section-header">
                    <span class="section-number">3</span>
                    <i class="fas fa-shield-halved"></i>
                    <h2>Permissões & Status</h2>
                </div>
                <div class="section-body">
                    <div class="row cols-2">
                        <div class="field">
                            <label for="Permissao">Nível de Permissão <span class="req">*</span></label>
                            <select id="Permissao" name="Permissao" required onchange="atualizarFuncao()">
                                <option value="" disabled <?= empty($form_data['Permissao']) ? 'selected' : '' ?>>Selecione...</option>
                                <?php
                                $permissoes = [1=>'SUPERADMIN',2=>'ADMIN',3=>'GESTOR-EDITOR',4=>'EDITOR',5=>'CORRETOR',6=>'CORRETOR-EDITOR',7=>'VISUALIZADOR'];
                                $perm_atual = $form_data['Permissao'] ?? '';
                                foreach ($permissoes as $v => $txt) {
                                    $sel = ($perm_atual == $v) ? 'selected' : '';
                                    echo "<option value='$v' $sel>$txt</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Status do Usuário</label>
                            <div style="padding-top: 0.55rem;">
                                <label class="switch">
                                    <input type="checkbox" id="Ativo" name="Ativo" value="1"
                                           <?= (!isset($form_data['Ativo']) || $form_data['Ativo'] == '1') ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span id="labelAtivo">ATIVO</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row cols-2">
                        <div class="field">
                            <label for="Funcao">Função</label>
                            <input type="text" id="Funcao" name="Funcao" readonly
                                   value="<?= htmlspecialchars($form_data['Funcao'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Badge</label>
                            <div style="padding-top: 0.5rem;">
                                <span class="perm-badge empty" id="badgeFuncao">
                                    <i class="fas fa-user-tag"></i> Selecione a permissão
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="Acoes">Ações Permitidas</label>
                        <textarea id="Acoes" name="Acoes" rows="3" readonly><?= htmlspecialchars($form_data['Acoes'] ?? '') ?></textarea>
                        <div class="helper-text">
                            <i class="fas fa-info-circle"></i>
                            Definidas automaticamente pelo nível de permissão
                        </div>
                    </div>
                </div>
            </section>

            <!-- AÇÕES -->
            <div class="action-bar">
                <a href="usr_listar.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-floppy-disk"></i> Criar Usuário
                </button>
            </div>

        </main>

        <!-- ========== COLUNA DIREITA (PREVIEW) ========== -->
        <aside class="form-summary">
            <div class="summary-card">
                <div class="summary-header">
                    <i class="fas fa-eye"></i>
                    <span>Pré-visualização</span>
                </div>
                <div class="summary-body">

                    <div class="avatar-preview">
                        <div class="avatar-circle" id="avatarInitial">?</div>
                        <div class="avatar-name" id="previewNome">Nome do Usuário</div>
                        <div class="avatar-creci" id="previewCreci"></div>
                    </div>

                    <div class="preview-field">
                        <div class="preview-label"><i class="fas fa-at"></i> Usuário</div>
                        <div class="preview-value mono empty" id="previewUsuario">—</div>
                    </div>

                    <div class="preview-field">
                        <div class="preview-label"><i class="fas fa-key"></i> Senha inicial</div>
                        <div class="preview-value password-value empty" id="previewSenha">—</div>
                        <div class="strength-meter"><div class="strength-meter-bar" id="strengthBar"></div></div>
                    </div>

                    <div class="preview-field">
                        <div class="preview-label"><i class="fas fa-shield-halved"></i> Permissão</div>
                        <div id="previewPermissao">
                            <span class="perm-badge empty">— não definida —</span>
                        </div>
                    </div>

                    <div class="preview-field">
                        <div class="preview-label"><i class="fas fa-building"></i> Empresa</div>
                        <div class="preview-value empty" id="previewEmpresa">— não selecionada —</div>
                    </div>

                    <div class="preview-field">
                        <div class="preview-label"><i class="fas fa-users"></i> Grupo</div>
                        <div class="preview-value empty" id="previewGrupo">— não selecionado —</div>
                    </div>

                    <div class="summary-divider"><span>Status</span></div>

                    <div id="previewStatus">
                        <span class="status-pill active"><i class="fas fa-check-circle"></i> ATIVO</span>
                    </div>

                </div>
            </div>
        </aside>

    </form>
</div>

<script>
// ============================================================
// FUNÇÕES AUXILIARES (mantidas)
// ============================================================
function removerAcentos(texto) {
    return texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

function gerarNomeUsuario(nomeCompleto) {
    if (!nomeCompleto.trim()) return '';
    const partes = nomeCompleto.toLowerCase().trim().split(/\s+/);
    const primeiro = removerAcentos(partes[0]);
    let iniciais = '';
    for (let i = 1; i < partes.length; i++) {
        if (partes[i]) iniciais += partes[i][0];
    }
    return primeiro + iniciais;
}

function gerarSenha(nomeCompleto) {
    if (!nomeCompleto.trim()) return '';
    const partes = nomeCompleto.trim().split(/\s+/);
    const primeiro = removerAcentos(partes[0].toLowerCase());
    const num = Math.floor(Math.random() * 99) + 1;
    return primeiro + '@tv' + num.toString().padStart(2, '0');
}

// ============================================================
// PERMISSÕES (mantidas)
// ============================================================
function atualizarFuncao() {
    const perm = document.getElementById("Permissao")?.value || "";
    const funcaoEl = document.getElementById("Funcao");
    const acoesEl = document.getElementById("Acoes");
    const badgeEl = document.getElementById("badgeFuncao");
    let funcao = "", acoes = "", classe = "";

    switch(perm) {
        case "1": funcao="SUPERADMIN"; acoes="/GESTAO/SISTEMA/USUARIOS/BACKUP/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/"; classe="superadmin"; break;
        case "2": funcao="ADMIN"; acoes="/USUARIOS/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/"; classe="admin"; break;
        case "3": funcao="GESTOR-EDITOR"; acoes="/GESTOR/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/"; classe="gestor-editor"; break;
        case "4": funcao="EDITOR"; acoes="/CONSULTAR/INCLUIR/EDITAR/EXCLUIR/"; classe="editor"; break;
        case "5": funcao="CORRETOR"; acoes="/CONSULTAR/"; classe="corretor"; break;
        case "6": funcao="CORRETOR-EDITOR"; acoes="/CONSULTAR/EDITAR/"; classe="corretor-editor"; break;
        case "7": funcao="VISUALIZADOR"; acoes="/CONSULTAR/"; classe="visualizador"; break;
    }

    if (funcaoEl) funcaoEl.value = funcao;
    if (acoesEl) acoesEl.value = acoes;
    if (badgeEl) {
        if (classe) {
            badgeEl.className = `perm-badge ${classe}`;
            badgeEl.innerHTML = '<i class="fas fa-user-tag"></i> ' + funcao;
        } else {
            badgeEl.className = "perm-badge empty";
            badgeEl.innerHTML = '<i class="fas fa-user-tag"></i> Selecione a permissão';
        }
    }

    // Atualiza preview da permissão
    const previewPerm = document.getElementById("previewPermissao");
    if (previewPerm) {
        if (classe) {
            previewPerm.innerHTML = `<span class="perm-badge ${classe}"><i class="fas fa-user-tag"></i> ${funcao}</span>`;
        } else {
            previewPerm.innerHTML = '<span class="perm-badge empty">— não definida —</span>';
        }
    }
}

// ============================================================
// PREVIEW EM TEMPO REAL
// ============================================================
function atualizarPreview() {
    const nome = document.getElementById("Nome")?.value.trim() || "";
    const usuario = document.getElementById("Usuario")?.value.trim() || "";
    const senha = document.getElementById("Senha")?.value || "";
    const creci = document.getElementById("CRECI")?.value.trim() || "";

    // Avatar com iniciais
    const avatarEl = document.getElementById("avatarInitial");
    if (avatarEl) {
        if (nome) {
            const partes = nome.split(/\s+/).filter(Boolean);
            const iniciais = (partes[0]?.[0] || '') + (partes.length > 1 ? partes[partes.length - 1][0] : '');
            avatarEl.textContent = iniciais.toUpperCase() || '?';
        } else {
            avatarEl.textContent = '?';
        }
    }

    // Nome
    const previewNome = document.getElementById("previewNome");
    if (previewNome) {
        if (nome) {
            previewNome.textContent = nome;
            previewNome.style.color = '';
            previewNome.style.fontStyle = '';
        } else {
            previewNome.textContent = 'Nome do Usuário';
            previewNome.style.color = '#94a3b8';
            previewNome.style.fontStyle = 'italic';
        }
    }

    // CRECI
    const previewCreci = document.getElementById("previewCreci");
    if (previewCreci) {
        if (creci) {
            previewCreci.innerHTML = '<i class="fas fa-id-card"></i> CRECI ' + creci;
        } else {
            previewCreci.innerHTML = '';
        }
    }

    // Usuário
    const previewUsuario = document.getElementById("previewUsuario");
    if (previewUsuario) {
        if (usuario) {
            previewUsuario.textContent = usuario;
            previewUsuario.classList.remove("empty");
        } else {
            previewUsuario.textContent = "—";
            previewUsuario.classList.add("empty");
        }
    }

    // Senha
    const previewSenha = document.getElementById("previewSenha");
    if (previewSenha) {
        if (senha) {
            previewSenha.textContent = senha;
            previewSenha.classList.remove("empty");
        } else {
            previewSenha.textContent = "—";
            previewSenha.classList.add("empty");
        }
    }

    // Força da senha
    const bar = document.getElementById("strengthBar");
    if (bar) {
        let strength = 0;
        if (senha.length >= 6) strength += 25;
        if (senha.length >= 10) strength += 15;
        if (/[a-z]/.test(senha) && /[A-Z]/.test(senha)) strength += 20;
        if (/[0-9]/.test(senha)) strength += 20;
        if (/[^a-zA-Z0-9]/.test(senha)) strength += 20;
        strength = Math.min(strength, 100);
        bar.style.width = strength + '%';
        if (strength < 30) bar.style.background = '#dc2626';
        else if (strength < 60) bar.style.background = '#f59e0b';
        else if (strength < 80) bar.style.background = '#06b6d4';
        else bar.style.background = '#16a34a';
    }

    // Empresa
    const previewEmpresa = document.getElementById("previewEmpresa");
    const selEmp = document.getElementById("idEmp");
    if (previewEmpresa && selEmp) {
        const txt = selEmp.options[selEmp.selectedIndex]?.text || '';
        if (selEmp.value && txt) {
            previewEmpresa.textContent = txt;
            previewEmpresa.classList.remove("empty");
        } else {
            previewEmpresa.textContent = "— não selecionada —";
            previewEmpresa.classList.add("empty");
        }
    }

    // Grupo
    const previewGrupo = document.getElementById("previewGrupo");
    const selGrupo = document.getElementById("ID_Grupo");
    if (previewGrupo && selGrupo) {
        const txt = selGrupo.options[selGrupo.selectedIndex]?.text || '';
        if (selGrupo.value && txt) {
            previewGrupo.textContent = txt;
            previewGrupo.classList.remove("empty");
        } else {
            previewGrupo.textContent = "— não selecionado —";
            previewGrupo.classList.add("empty");
        }
    }

    // Status
    const previewStatus = document.getElementById("previewStatus");
    const ativoCheck = document.getElementById("Ativo");
    if (previewStatus && ativoCheck) {
        if (ativoCheck.checked) {
            previewStatus.innerHTML = '<span class="status-pill active"><i class="fas fa-check-circle"></i> ATIVO</span>';
        } else {
            previewStatus.innerHTML = '<span class="status-pill inactive"><i class="fas fa-ban"></i> INATIVO</span>';
        }
    }
}

// ============================================================
// INICIALIZAÇÃO
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
    const nomeInput = document.getElementById("Nome");
    if (nomeInput) {
        nomeInput.addEventListener("input", function() {
            const valor = this.value.trim();
            const u = valor ? gerarNomeUsuario(valor) : "";
            const s = valor ? gerarSenha(valor) : "";
            const elU = document.getElementById("Usuario");
            const elS = document.getElementById("Senha");
            // Só regenera se o campo estava vazio ou foi gerado automaticamente
            if (elU && (!elU.value || elU.dataset.auto !== 'false')) {
                elU.value = u;
            }
            if (elS && (!elS.value || elS.dataset.auto !== 'false')) {
                elS.value = s;
            }
            atualizarPreview();
        });
    }

    // Se o usuário editar manualmente, marca como "não automático"
    document.getElementById("Usuario")?.addEventListener("input", function() {
        this.dataset.auto = 'false';
        atualizarPreview();
    });
    document.getElementById("Senha")?.addEventListener("input", function() {
        this.dataset.auto = 'false';
        atualizarPreview();
    });

    // Toggle senha
    const toggle = document.getElementById("togglePassword");
    const senhaInput = document.getElementById("Senha");
    if (toggle && senhaInput) {
        toggle.addEventListener("click", () => {
            const type = senhaInput.type === "password" ? "text" : "password";
            senhaInput.type = type;
            const icon = toggle.querySelector("i");
            icon.classList.toggle("fa-eye");
            icon.classList.toggle("fa-eye-slash");
        });
    }

    // Switch Ativo
    const ativoCheck = document.getElementById("Ativo");
    if (ativoCheck) {
        ativoCheck.addEventListener("change", function() {
            const label = document.getElementById("labelAtivo");
            if (label) label.textContent = this.checked ? "ATIVO" : "INATIVO";
            atualizarPreview();
        });
        ativoCheck.dispatchEvent(new Event('change'));
    }

    // Listeners para preview
    ["idEmp", "ID_Grupo", "CRECI"].forEach(id => {
        document.getElementById(id)?.addEventListener("change", atualizarPreview);
        document.getElementById(id)?.addEventListener("input", atualizarPreview);
    });

    // Aplicar máscara de telefone (opcional)
    document.getElementById("Telefones")?.addEventListener("input", function() {
        let v = this.value.replace(/\D/g, '');
        if (v.length > 10) {
            this.value = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
        } else if (v.length > 6) {
            this.value = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
        } else if (v.length > 2) {
            this.value = v.replace(/^(\d{2})(\d{0,5}).*/, '($1) $2');
        } else {
            this.value = v;
        }
    });

    // Inicializa
    atualizarFuncao();
    atualizarPreview();
});
</script>

</body>
</html>

<?php $conn = null; ?>