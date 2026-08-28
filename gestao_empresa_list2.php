<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA

// Verifica se é para excluir
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $idToDelete = $_GET['id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM empresa WHERE Empresa_ID = ?");
        $stmt->execute([$idToDelete]);
        
        if ($stmt->rowCount() > 0) {
            echo "<script>alert('Empresa excluída com sucesso!');</script>";
        } else {
            echo "<script>alert('Não foi possível excluir. A empresa pode estar vinculada a registros.');</script>";
        }
    } catch(PDOException $e) {
        echo "<script>alert('Erro ao excluir: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Recupera a lista de empresas
try {
    $stmt = $pdo->query("SELECT * FROM empresa ORDER BY Empresa_ID DESC");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erro ao buscar empresas: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="600">
    <title>Gestão de Empresas</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================================ */
        /* ESTILOS BASEADOS NO TEMA (via variáveis CSS)                  */
        /* ============================================================ */
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
            box-shadow: 0 4px 0px 0px var(--shadow-color);
            padding: 0.7rem 0;
        }
        .navbar-neo .navbar-brand {
            font-weight: 800;
            color: #fff;
        }
        .navbar-neo .navbar-brand i {
            color: #fff;
        }
        .navbar-neo .nav-link {
            color: rgba(255,255,255,0.85);
            font-weight: 600;
            transition: all 0.15s;
        }
        .navbar-neo .nav-link:hover {
            color: #fff;
            transform: translateY(-2px);
        }
        .navbar-neo .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
        }
        .navbar-neo .dropdown-menu {
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 12px;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
        }
        .navbar-neo .dropdown-item {
            color: var(--text-color);
            font-weight: 600;
            border-radius: 8px;
        }
        .navbar-neo .dropdown-item:hover {
            background: var(--primary);
            color: #fff;
        }

        /* Card neobrutalista */
        .card-neo {
            background: var(--card-bg);
            border: 3px solid var(--shadow-color);
            border-radius: 16px;
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            overflow: hidden;
        }
        .card-neo .card-header {
            background: var(--card-bg);
            border-bottom: 3px solid var(--shadow-color);
            padding: 1rem 1.5rem;
            font-weight: 700;
            color: var(--text-color);
        }
        .card-neo .card-header .btn {
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            border-radius: 8px;
            font-weight: 700;
        }
        .card-neo .card-body {
            padding: 1.5rem;
        }

        /* Botões neobrutalistas */
        .btn-neo {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.5rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo-success {
            background: #28a745;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 0.8rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-success:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #218838;
        }
        .btn-neo-primary {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 0.8rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-primary:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo-danger {
            background: #dc3545;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 0.8rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-danger:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #b02a37;
        }
        .btn-neo-outline {
            background: transparent;
            color: var(--text-color);
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.3rem 1.2rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-outline:hover {
            transform: translate(-2px, -2px);
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
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
        }
        .table-neo tbody td {
            border: 1px solid var(--shadow-color);
            padding: 0.6rem;
            vertical-align: middle;
        }
        .table-neo tbody tr:hover {
            background: rgba(0,0,0,0.03);
        }
        .table-neo tbody tr:nth-child(even) {
            background: rgba(0,0,0,0.02);
        }

        /* DataTables personalizado */
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
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            padding: 0.3rem;
            background: #fff;
            color: var(--text-color);
        }
        .dataTables_wrapper .dataTables_info {
            color: var(--text-color);
            opacity: 0.7;
            font-weight: 600;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 0.8rem;
            margin: 0 2px;
            font-weight: 600;
            transition: all 0.15s;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary);
            color: #fff !important;
            transform: translateY(-2px);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary);
            color: #fff !important;
            border-color: var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }

        .truncate-text {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Título da página */
        .page-title {
            font-weight: 800;
            color: var(--primary);
        }
        .page-title i {
            color: var(--primary);
        }

        /* Responsivo */
        @media (max-width: 768px) {
            body { padding-top: 70px; }
            .navbar-neo .navbar-brand { font-size: 1rem; }
            .card-neo .card-body { padding: 1rem; }
            .table-neo { font-size: 0.8rem; }
            .truncate-text { max-width: 100px; }
        }
    </style>
</head>
<body>
    <!-- Navbar neobrutalista -->
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

    <!-- Conteúdo Principal -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="page-title"><i class="fas fa-building me-2"></i>Lista de Empresas</h2>
                    <a href="gestao_empresa_create.php" class="btn-neo">
                        <i class="fas fa-plus me-2"></i>Nova Empresa
                    </a>
                </div>

                <div class="card-neo">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-list me-2"></i>Todas as Empresas</span>
                        <div class="d-flex">
                            <input type="text" id="searchInput" class="form-control form-control-sm me-2" placeholder="Pesquisar..." style="border: 2px solid var(--shadow-color); border-radius: 30px; padding: 0.3rem 1rem;">
                            <button class="btn btn-sm btn-neo-outline">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="empresasTable" class="table-neo table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>CNPJ</th>
                                        <th>Telefone</th>
                                        <th>E-mail</th>
                                        <th>Cidade</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($empresas as $empresa): ?>
                                    <tr>
                                        <td><?php echo $empresa['Empresa_ID']; ?></td>
                                        <td><strong><?php echo strtoupper($empresa['NomeEmpresa']); ?></strong></td>
                                        <td>
                                            <?php 
                                            $cnpj = $empresa['CNPJ'];
                                            if (strlen($cnpj) == 14) {
                                                echo substr($cnpj, 0, 2) . '.' . 
                                                     substr($cnpj, 2, 3) . '.' . 
                                                     substr($cnpj, 5, 3) . '/' . 
                                                     substr($cnpj, 8, 4) . '-' . 
                                                     substr($cnpj, 12, 2);
                                            } else {
                                                echo $cnpj;
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $tel = $empresa['Telefone'];
                                            if (strlen($tel) == 11) {
                                                echo '(' . substr($tel, 0, 2) . ') ' . 
                                                     substr($tel, 2, 5) . '-' . 
                                                     substr($tel, 7, 4);
                                            } elseif (strlen($tel) == 10) {
                                                echo '(' . substr($tel, 0, 2) . ') ' . 
                                                     substr($tel, 2, 4) . '-' . 
                                                     substr($tel, 6, 4);
                                            } else {
                                                echo $tel;
                                            }
                                            ?>
                                        </td>
                                        <td class="truncate-text"><?php echo $empresa['Email']; ?></td>
                                        <td><?php echo $empresa['Cidade']; ?></td>
                                        <td class="action-btns" style="white-space: nowrap;">
                                            <a href="gestao_empresa_update.php?id=<?php echo $empresa['Empresa_ID']; ?>" class="btn btn-neo-primary btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=delete&id=<?php echo $empresa['Empresa_ID']; ?>" class="btn btn-neo-danger btn-sm" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este registro?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
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

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#empresasTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                responsive: true,
                "order": [[0, "desc"]],
                "pageLength": 100,
                dom: '<"top"lf>rt<"bottom"ip>',
                initComplete: function() {
                    $('#searchInput').on('keyup', function() {
                        $('#empresasTable').DataTable().search(this.value).draw();
                    });
                }
            });
        });
    </script>
</body>
</html>