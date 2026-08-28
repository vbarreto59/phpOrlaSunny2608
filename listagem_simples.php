<?php
session_start();

// Configuração de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==================== CONEXÃO COM PDO E TEMA ====================
try {
    require_once 'conn.php';
    require_once 'theme_config.php'; // <-- CARREGA O TEMA
} catch (Exception $e) {
    die("<div class='alert alert-danger'>Erro ao carregar configurações: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Função para remover números
function removerNumeros($strTexto) {
    return preg_replace('/[0-9]/', '', $strTexto);
}

// ==================== CONSULTA AO BANCO DE DADOS ====================
try {
    $sql = "SELECT * FROM empreendimento WHERE NomeEmpresa <> '001-VB-IMOVEIS' ORDER BY NomeEmpresa, NomeEmpreendimento";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>Erro ao conectar ao banco de dados: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listagem de Empreendimentos por Empresa</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
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
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-body);
            color: var(--text-color);
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }

        /* Header neobrutalista */
        .header-neo {
            background: var(--primary);
            color: #fff;
            padding: 1rem 0;
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 0px 0px var(--shadow-color);
            margin-bottom: 2rem;
        }

        .header-neo .logo-container img {
            max-height: 50px;
            width: auto;
            background: #fff;
            padding: 6px;
            border-radius: 10px;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }

        .header-neo h1 {
            font-weight: 800;
            font-size: 1.8rem;
            margin: 0;
        }

        .btn-neo-voltar {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.5rem;
            font-weight: 700;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-neo-voltar:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
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
        .card-neo .card-body {
            padding: 1.5rem;
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

        .company-name {
            font-weight: 700;
            color: var(--primary);
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

        /* Footer neobrutalista */
        .footer-neo {
            background: var(--card-bg);
            color: var(--text-color);
            border-top: 4px solid var(--shadow-color);
            box-shadow: 0 -4px 0px 0px var(--shadow-color);
            padding: 1.5rem 0;
            margin-top: 2rem;
        }
        .footer-neo strong {
            color: var(--primary);
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .header-neo h1 { font-size: 1.2rem; }
            .header-neo .logo-container img { max-height: 40px; }
            .card-neo .card-body { padding: 1rem; }
            .table-neo { font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<!-- HEADER NEOBRUTALISTA -->
<header class="header-neo">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="logo-container">
                    <img src="<?php echo (!empty($_SESSION['Conf_ImgLogo'])) ? $_SESSION['Conf_ImgLogo'] : 'logoSunny66a.JPG'; ?>" alt="Logo">
                </div>
                <h1><i class="fas fa-building me-2"></i>Listagem de Empreendimentos</h1>
            </div>
            <a href="javascript:window.history.back();" class="btn-neo-voltar">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </div>
</header>

<!-- CONTEÚDO PRINCIPAL -->
<main class="container mb-5">
    <div class="card-neo">
        <div class="card-body">
            <?php if (empty($empreendimentos)): ?>
                <div class="alert alert-info text-center" style="border: 2px solid var(--shadow-color); box-shadow: 4px 4px 0px 0px var(--shadow-color); border-radius: 12px; background: var(--card-bg);">
                    Nenhum empreendimento cadastrado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tabelaEmpreendimentos" class="table-neo table-striped">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Empreendimento</th>
                                <th>Localização</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $currentEmpresa = "";
                            foreach ($empreendimentos as $empreendimento): 
                                $currentEmpresa = $empreendimento['NomeEmpresa'];
                            ?>
                                <tr>
                                    <td class="align-middle company-name"><?= htmlspecialchars($currentEmpresa) ?></td>
                                    <td><?= htmlspecialchars(strtoupper(removerNumeros($empreendimento['NomeEmpreendimento']))) ?></td>
                                    <td><?= htmlspecialchars(removerNumeros($empreendimento['Localidade'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="mt-4 text-end">
                <small class="text-muted" style="color: var(--text-color); opacity: 0.6; font-weight: 600;">
                    Atualizado em: <?= date('d/m/Y H:i:s') ?>
                </small>
            </div>
        </div>
    </div>
</main>

<!-- FOOTER NEOBRUTALISTA -->
<footer class="footer-neo">
    <div class="container text-center">
        <p class="mb-0">
            &copy; <?= date('Y') ?> <strong><?= $_SESSION['EmpresaNome'] ?? 'Sistema de Empreendimentos' ?></strong>
            <br>
            <small style="opacity: 0.6;">Desenvolvido por Valter Barreto</small>
        </p>
    </div>
</footer>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#tabelaEmpreendimentos').DataTable({
            "pageLength": 100,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json"
            },
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>'
        });
    });
</script>

</body>
</html>