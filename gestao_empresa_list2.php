<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php';

// ============================================================
// CONEXÃO — fallback robusto
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
// CSRF
// ============================================================
if (empty($_SESSION['csrf_empresa'])) {
    $_SESSION['csrf_empresa'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_empresa'];

// ============================================================
// PROCESSAMENTO POST (CREATE / UPDATE / DELETE) — PRG pattern
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $_SESSION['msg_erro'] = "Falha de segurança (CSRF). Recarregue a página.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        // ---------- CREATE ----------
        if ($action === 'create') {
            $camposValidos = ['NomeEmpresa','Descricao','CNPJ','Endereco','Cidade',
                              'Bairro','CEP','Contato','Telefone','Email','Obs'];

            $nome = trim($_POST['NomeEmpresa'] ?? '');
            if ($nome === '') throw new Exception("O nome da empresa é obrigatório.");

            $campos = [];
            $params = [];
            foreach ($camposValidos as $c) {
                $v = trim($_POST[$c] ?? '');
                if ($v !== '') {
                    $campos[] = $c;
                    $params[] = $v;
                }
            }

            $sql = "INSERT INTO empresa (" . implode(',', $campos) . ")
                    VALUES (" . implode(',', array_fill(0, count($campos), '?')) . ")";
            $db->prepare($sql)->execute($params);

            $_SESSION['msg_sucesso'] = "Empresa <strong>" . htmlspecialchars($nome) . "</strong> cadastrada com sucesso!";
        }

        // ---------- UPDATE ----------
        elseif ($action === 'update') {
            $id = (int)($_POST['Empresa_ID'] ?? 0);
            if ($id <= 0) throw new Exception("ID inválido.");

            $nome = trim($_POST['NomeEmpresa'] ?? '');
            if ($nome === '') throw new Exception("O nome da empresa é obrigatório.");

            $sql = "UPDATE empresa
                       SET NomeEmpresa = ?, Descricao = ?, CNPJ = ?, Endereco = ?,
                           Cidade = ?, Bairro = ?, CEP = ?, Contato = ?,
                           Telefone = ?, Email = ?, Obs = ?
                     WHERE Empresa_ID = ?";

            $db->prepare($sql)->execute([
                $nome,
                trim($_POST['Descricao'] ?? ''),
                trim($_POST['CNPJ'] ?? ''),
                trim($_POST['Endereco'] ?? ''),
                trim($_POST['Cidade'] ?? ''),
                trim($_POST['Bairro'] ?? ''),
                trim($_POST['CEP'] ?? ''),
                trim($_POST['Contato'] ?? ''),
                trim($_POST['Telefone'] ?? ''),
                trim($_POST['Email'] ?? ''),
                trim($_POST['Obs'] ?? ''),
                $id
            ]);

            $_SESSION['msg_sucesso'] = "Empresa <strong>" . htmlspecialchars($nome) . "</strong> atualizada!";
        }

        // ---------- DELETE ----------
        elseif ($action === 'delete') {
            $id = (int)($_POST['Empresa_ID'] ?? 0);
            if ($id <= 0) throw new Exception("ID inválido.");

            $stmt = $db->prepare("SELECT NomeEmpresa FROM empresa WHERE Empresa_ID = ?");
            $stmt->execute([$id]);
            $nome = $stmt->fetchColumn() ?: ("#{$id}");

            $stmt = $db->prepare("DELETE FROM empresa WHERE Empresa_ID = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['msg_sucesso'] = "Empresa <strong>" . htmlspecialchars($nome) . "</strong> excluída.";
            } else {
                throw new Exception("Não foi possível excluir. A empresa pode estar vinculada a outros registros.");
            }
        }
    } catch (Exception $e) {
        $_SESSION['msg_erro'] = $e->getMessage();
        error_log("[EMPRESA_CRUD] " . $e->getMessage());
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ============================================================
// FLASH MESSAGES
// ============================================================
$msgSucesso = $_SESSION['msg_sucesso'] ?? '';
unset($_SESSION['msg_sucesso']);
$msgErro = $_SESSION['msg_erro'] ?? '';
unset($_SESSION['msg_erro']);

// ============================================================
// CARREGAR EMPRESAS
// ============================================================
try {
    $empresas = $db->query("SELECT * FROM empresa ORDER BY Empresa_ID DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar empresas: " . $e->getMessage());
}

// JSON para o JS (populate modal de edição)
$empresasJson = json_encode($empresas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if (json_last_error() !== JSON_ERROR_NONE) $empresasJson = '[]';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Empresas</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: <?= $themeSafe['primary'] ?>;
            --primary-dark: <?= $themeSafe['primary_dark'] ?>;
            --secondary: <?= $themeSafe['secondary'] ?>;
            --accent: <?= $themeSafe['accent'] ?>;
            --bg-body: <?= $themeSafe['bg_body'] ?>;
            --text-color: <?= $themeSafe['text_color'] ?>;
            --card-bg: <?= $themeSafe['card_bg'] ?>;
            --shadow-color: <?= $themeSafe['shadow_color'] ?>;
            --btn-bg: <?= $themeSafe['btn_bg'] ?>;
            --btn-hover: <?= $themeSafe['btn_hover'] ?>;
            --btn-text: <?= $themeSafe['btn_text'] ?>;
            --success: #16a34a;
            --danger: #dc2626;
            --info: #3b82f6;
            --warning: #f59e0b;
        }

        body {
            background: var(--bg-body);
            color: var(--text-color);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            padding-top: 80px;
            min-height: 100vh;
        }

        /* Navbar neobrutalista */
        .navbar-neo {
            background: var(--primary);
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 0 0 var(--shadow-color);
            padding: 0.7rem 0;
        }
        .navbar-neo .navbar-brand { font-weight: 800; color: #fff; }
        .navbar-neo .navbar-brand i { color: #fff; }
        .navbar-neo .nav-link {
            color: rgba(255,255,255,0.85);
            font-weight: 600;
            transition: all 0.15s;
        }
        .navbar-neo .nav-link:hover { color: #fff; transform: translateY(-2px); }
        .navbar-neo .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
        }
        .navbar-neo .dropdown-menu {
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
        }
        .navbar-neo .dropdown-item { color: var(--text-color); font-weight: 600; border-radius: 8px; }
        .navbar-neo .dropdown-item:hover { background: var(--primary); color: #fff; }

        /* Card neobrutalista */
        .card-neo {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0 0 var(--shadow-color);
            overflow: hidden;
        }
        .card-neo .card-header {
            background: var(--card-bg);
            border-bottom: 3px solid var(--shadow-color);
            padding: 1rem 1.5rem;
            font-weight: 700;
            color: var(--text-color);
        }
        .card-neo .card-body { padding: 1.5rem; }

        /* Botões neobrutalistas */
        .btn-neo {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.5rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo-primary {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 0.8rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-primary:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo-danger {
            background: var(--danger);
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 0.8rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-danger:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 0 var(--shadow-color);
            background: #b91c1c;
        }
        .btn-neo-outline {
            background: transparent;
            color: var(--text-color);
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0 0 var(--shadow-color);
            border-radius: 30px;
            padding: 0.3rem 1.2rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-outline:hover {
            transform: translate(-2px, -2px);
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }

        /* Tabela neobrutalista */
        .table-neo {
            border-collapse: collapse;
            font-size: 0.9rem;
            width: 100%;
        }
        .table-neo thead th {
            background: var(--primary);
            color: #fff;
            border: 2px solid var(--shadow-color);
            font-weight: 700;
            padding: 0.8rem;
            white-space: nowrap;
        }
        .table-neo tbody td {
            border: 1px solid var(--shadow-color);
            padding: 0.6rem;
            vertical-align: middle;
        }
        .table-neo tbody tr:hover { background: rgba(0,0,0,0.03); }
        .table-neo tbody tr:nth-child(even) { background: rgba(0,0,0,0.02); }

        /* DataTables */
        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.2rem;
            background: #fff;
            color: var(--text-color);
            transition: all 0.2s;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            box-shadow: 4px 4px 0 0 var(--shadow-color);
            border-color: var(--primary);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 0.3rem;
            background: #fff;
            color: var(--text-color);
            font-weight: 700;
        }
        .dataTables_wrapper .dataTables_info {
            color: var(--text-color);
            opacity: 0.7;
            font-weight: 600;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 2px solid var(--shadow-color) !important;
            border-radius: 8px !important;
            padding: 0.3rem 0.8rem !important;
            margin: 0 2px !important;
            font-weight: 600 !important;
            background: #fff !important;
            color: var(--text-color) !important;
            transition: all 0.15s;
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

        .truncate-text {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .page-title { font-weight: 800; color: var(--primary); }
        .page-title i { color: var(--primary); }

        /* Modal neobrutalista */
        .modal-content {
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 8px 8px 0 0 var(--shadow-color);
            overflow: hidden;
        }
        .modal-header {
            background: var(--primary);
            color: #fff;
            border-bottom: 3px solid var(--shadow-color);
            padding: 16px 22px;
        }
        .modal-title {
            font-weight: 900;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header .btn-close {
            filter: invert(1) brightness(2);
            opacity: 1;
        }
        .modal-body { padding: 22px; background: var(--card-bg); }
        .modal-footer {
            background: var(--card-bg);
            border-top: 3px solid var(--shadow-color);
            padding: 16px 22px;
            gap: 10px;
        }

        /* Fields */
        .field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-color);
            opacity: 0.85;
            margin-bottom: 6px;
        }
        .field-label i { color: var(--primary); font-size: 0.8rem; }
        .field-label .req { color: var(--danger); }

        .form-control, .form-select {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 9px 12px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text-color);
            background: #fff;
            transition: all 0.15s;
            box-shadow: 2px 2px 0 0 var(--shadow-color);
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 3px 3px 0 0 var(--shadow-color);
        }
        .form-control::placeholder { color: #b8bcc4; font-weight: 400; }
        textarea.form-control { resize: vertical; min-height: 70px; }

        /* Responsivo */
        @media (max-width: 768px) {
            body { padding-top: 70px; }
            .navbar-neo .navbar-brand { font-size: 1rem; }
            .card-neo .card-body { padding: 1rem; }
            .table-neo { font-size: 0.8rem; }
            .truncate-text { max-width: 100px; }
            .modal-body { padding: 16px; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-neo fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-building me-2"></i>Gestão de Empresas
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="gestao_vendas.php">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="gestao_empresa_list2.php">
                        <i class="fas fa-building me-1"></i>Empresas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="gestao_empreend_list_card.php">
                        <i class="fas fa-city me-1"></i>Empreendimentos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:window.close()">
                        <i class="fas fa-arrow-left me-1"></i>Voltar
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>Usuário
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Conteúdo -->
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h2 class="page-title mb-0">
                    <i class="fas fa-building me-2"></i>Lista de Empresas
                </h2>
                <button type="button" class="btn-neo" onclick="abrirCriacao()">
                    <i class="fas fa-plus me-2"></i>Nova Empresa
                </button>
            </div>

            <div class="card-neo">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="fas fa-list me-2"></i>Todas as Empresas</span>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" id="searchInput" class="form-control form-control-sm"
                               placeholder="Pesquisar..." style="min-width:220px;">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="empresasTable" class="table-neo table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>CNPJ</th>
                                    <th>Telefone</th>
                                    <th>E-mail</th>
                                    <th>Cidade</th>
                                    <th style="width:110px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empresas as $empresa): ?>
                                <tr data-id="<?= (int)$empresa['Empresa_ID'] ?>">
                                    <td><?= (int)$empresa['Empresa_ID'] ?></td>
                                    <td><strong><?= htmlspecialchars(strtoupper($empresa['NomeEmpresa'] ?? '')) ?></strong></td>
                                    <td>
                                        <?php
                                        $cnpj = $empresa['CNPJ'] ?? '';
                                        if (strlen($cnpj) == 14) {
                                            echo substr($cnpj, 0, 2) . '.' .
                                                 substr($cnpj, 2, 3) . '.' .
                                                 substr($cnpj, 5, 3) . '/' .
                                                 substr($cnpj, 8, 4) . '-' .
                                                 substr($cnpj, 12, 2);
                                        } else {
                                            echo htmlspecialchars($cnpj);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $tel = $empresa['Telefone'] ?? '';
                                        if (strlen($tel) == 11) {
                                            echo '(' . substr($tel, 0, 2) . ') ' .
                                                 substr($tel, 2, 5) . '-' .
                                                 substr($tel, 7, 4);
                                        } elseif (strlen($tel) == 10) {
                                            echo '(' . substr($tel, 0, 2) . ') ' .
                                                 substr($tel, 2, 4) . '-' .
                                                 substr($tel, 6, 4);
                                        } else {
                                            echo htmlspecialchars($tel);
                                        }
                                        ?>
                                    </td>
                                    <td class="truncate-text"><?= htmlspecialchars($empresa['Email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($empresa['Cidade'] ?? '') ?></td>
                                    <td style="white-space: nowrap;">
                                        <button type="button" class="btn btn-neo-primary btn-sm"
                                                title="Editar"
                                                onclick="abrirEdicao(<?= (int)$empresa['Empresa_ID'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-neo-danger btn-sm"
                                                title="Excluir"
                                                onclick="confirmarExclusao(<?= (int)$empresa['Empresa_ID'] ?>, <?= htmlspecialchars(json_encode($empresa['NomeEmpresa'] ?? ''), ENT_QUOTES) ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL CRIAR / EDITAR                                        -->
<!-- ============================================================ -->
<div class="modal fade" id="modalEmpresa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="formEmpresa">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="Empresa_ID" id="formId" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="fas fa-plus-circle"></i> Nova Empresa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="field-label" for="fieldNomeEmpresa">
                                <i class="fas fa-building"></i> Nome da Empresa <span class="req">*</span>
                            </label>
                            <input type="text" class="form-control" id="fieldNomeEmpresa"
                                   name="NomeEmpresa" required maxlength="255"
                                   placeholder="Ex.: Sunny Imóveis Ltda">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label" for="fieldCNPJ">
                                <i class="fas fa-id-card"></i> CNPJ
                            </label>
                            <input type="text" class="form-control" id="fieldCNPJ"
                                   name="CNPJ" placeholder="00.000.000/0000-00">
                        </div>

                        <div class="col-12">
                            <label class="field-label" for="fieldDescricao">
                                <i class="fas fa-align-left"></i> Descrição
                            </label>
                            <textarea class="form-control" id="fieldDescricao"
                                      name="Descricao" rows="2"
                                      placeholder="Breve descrição da empresa"></textarea>
                        </div>

                        <div class="col-md-8">
                            <label class="field-label" for="fieldEndereco">
                                <i class="fas fa-map-pin"></i> Endereço
                            </label>
                            <input type="text" class="form-control" id="fieldEndereco"
                                   name="Endereco" placeholder="Rua, avenida, nº, complemento">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label" for="fieldBairro">
                                <i class="fas fa-city"></i> Bairro
                            </label>
                            <input type="text" class="form-control" id="fieldBairro"
                                   name="Bairro" placeholder="Ex.: Boa Viagem">
                        </div>

                        <div class="col-md-5">
                            <label class="field-label" for="fieldCidade">
                                <i class="fas fa-map"></i> Cidade
                            </label>
                            <input type="text" class="form-control" id="fieldCidade"
                                   name="Cidade" placeholder="Ex.: Recife">
                        </div>
                        <div class="col-md-3">
                            <label class="field-label" for="fieldCEP">
                                <i class="fas fa-mail-bulk"></i> CEP
                            </label>
                            <input type="text" class="form-control" id="fieldCEP"
                                   name="CEP" placeholder="00000-000">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label" for="fieldContato">
                                <i class="fas fa-user"></i> Contato
                            </label>
                            <input type="text" class="form-control" id="fieldContato"
                                   name="Contato" placeholder="Nome do responsável">
                        </div>

                        <div class="col-md-4">
                            <label class="field-label" for="fieldTelefone">
                                <i class="fas fa-phone"></i> Telefone
                            </label>
                            <input type="text" class="form-control" id="fieldTelefone"
                                   name="Telefone" placeholder="(00) 00000-0000">
                        </div>
                        <div class="col-md-8">
                            <label class="field-label" for="fieldEmail">
                                <i class="fas fa-envelope"></i> E-mail
                            </label>
                            <input type="email" class="form-control" id="fieldEmail"
                                   name="Email" placeholder="contato@empresa.com.br">
                        </div>

                        <div class="col-12">
                            <label class="field-label" for="fieldObs">
                                <i class="fas fa-comment-alt"></i> Observações
                            </label>
                            <textarea class="form-control" id="fieldObs"
                                      name="Obs" rows="2"
                                      placeholder="Informações complementares..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-neo-outline" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn-neo" id="btnSubmit">
                        <i class="fas fa-save me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- FORM OCULTO PARA DELETE                                     -->
<!-- ============================================================ -->
<form method="POST" id="formDelete" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="Empresa_ID" id="deleteId" value="">
</form>

<!-- ============================================================ -->
<!-- SCRIPTS                                                      -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

<script>
(function () {
    'use strict';

    const EMPRESAS = <?= $empresasJson ?>;

    $(document).ready(function () {

        // ============================================================
        // DataTables
        // ============================================================
        const table = $('#empresasTable').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json' },
            order: [[0, 'desc']],
            pageLength: 100,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Todos"]],
            columnDefs: [
                { orderable: false, targets: [6] },
                { searchable: false, targets: [6] }
            ],
            dom: '<"top"lf>rt<"bottom"ip>'
        });

        $('#searchInput').on('keyup', function () {
            table.search(this.value).draw();
        });

        // ============================================================
        // Máscaras
        // ============================================================
        function bindMasks() {
            $('#fieldCNPJ').mask('00.000.000/0000-00');
            $('#fieldCEP').mask('00000-000');
            $('#fieldTelefone').mask('(00) 00000-0000');
        }
        bindMasks();

        // ============================================================
        // MODAL
        // ============================================================
        const modalEl = document.getElementById('modalEmpresa');
        const modal = new bootstrap.Modal(modalEl);

        // ============================================================
        // ABRIR CRIAÇÃO
        // ============================================================
        window.abrirCriacao = function () {
            document.getElementById('modalTitle').innerHTML =
                '<i class="fas fa-plus-circle"></i> Nova Empresa';
            document.getElementById('formAction').value = 'create';
            document.getElementById('formId').value = '';
            document.getElementById('formEmpresa').reset();
            document.getElementById('btnSubmit').disabled = false;
            document.getElementById('btnSubmit').innerHTML =
                '<i class="fas fa-save me-2"></i>Salvar';

            // Reaplica máscaras após reset
            setTimeout(() => {
                bindMasks();
                document.getElementById('fieldNomeEmpresa').focus();
            }, 300);

            modal.show();
        };

        // ============================================================
        // ABRIR EDIÇÃO
        // ============================================================
        window.abrirEdicao = function (id) {
            const emp = EMPRESAS.find(function (e) {
                return Number(e.Empresa_ID) === Number(id);
            });
            if (!emp) return;

            document.getElementById('modalTitle').innerHTML =
                '<i class="fas fa-pen"></i> Editar Empresa';
            document.getElementById('formAction').value = 'update';
            document.getElementById('formId').value = emp.Empresa_ID;

            document.getElementById('fieldNomeEmpresa').value = emp.NomeEmpresa || '';
            document.getElementById('fieldCNPJ').value        = emp.CNPJ        || '';
            document.getElementById('fieldDescricao').value   = emp.Descricao   || '';
            document.getElementById('fieldEndereco').value    = emp.Endereco    || '';
            document.getElementById('fieldBairro').value      = emp.Bairro      || '';
            document.getElementById('fieldCidade').value      = emp.Cidade      || '';
            document.getElementById('fieldCEP').value         = emp.CEP         || '';
            document.getElementById('fieldContato').value     = emp.Contato     || '';
            document.getElementById('fieldTelefone').value    = emp.Telefone    || '';
            document.getElementById('fieldEmail').value       = emp.Email       || '';
            document.getElementById('fieldObs').value         = emp.Obs         || '';

            document.getElementById('btnSubmit').disabled = false;
            document.getElementById('btnSubmit').innerHTML =
                '<i class="fas fa-save me-2"></i>Salvar Alterações';

            // Reaplica máscaras
            setTimeout(bindMasks, 100);

            modal.show();
        };

        // ============================================================
        // EXCLUSÃO (SweetAlert + POST)
        // ============================================================
        window.confirmarExclusao = function (id, nome) {
            Swal.fire({
                title: 'Excluir empresa?',
                html:
                    `A empresa <b>${nome}</b> será removida <b>permanentemente</b>.<br>` +
                    `<span style="color:#ef4444;font-size:0.85rem;font-weight:700;">` +
                    `Empresas com registros vinculados não podem ser excluídas.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Sim, excluir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                focusCancel: true
            }).then(function (r) {
                if (r.isConfirmed) {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('formDelete').submit();
                }
            });
        };

        // ============================================================
        // SUBMIT — bloqueia botão durante envio
        // ============================================================
        document.getElementById('formEmpresa')?.addEventListener('submit', function (e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                this.reportValidity();
                return;
            }
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Salvando…';
        });

        // ============================================================
        // TOASTS (mensagens flash)
        // ============================================================
        <?php if (!empty($msgSucesso)): ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: <?= json_encode(strip_tags($msgSucesso)) ?>,
            timer: 4000,
            timerProgressBar: true,
            showConfirmButton: false
        });
        <?php endif; ?>

        <?php if (!empty($msgErro)): ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: <?= json_encode(strip_tags($msgErro)) ?>,
            timer: 5000,
            timerProgressBar: true,
            showConfirmButton: false
        });
        <?php endif; ?>

    });
})();
</script>
</body>
</html>