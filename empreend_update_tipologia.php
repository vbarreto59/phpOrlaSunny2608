<?php
require_once 'conn.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA
session_start();

// Verificação básica de sessão (opcional)
// if (empty($_SESSION['Usuario'])) {
//     header("Location: login_v3a.php");
//     exit();
// }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Atualizar Tipologia</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
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
            padding: 20px;
            min-height: 100vh;
        }

        /* Header neobrutalista */
        .header-neo {
            background: var(--primary);
            color: #fff;
            padding: 0.8rem 0;
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 0px 0px var(--shadow-color);
            margin-bottom: 2rem;
        }
        .header-neo .logo-img {
            height: 50px;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            background: #fff;
            padding: 4px;
            margin-right: 15px;
        }
        .header-neo h1 {
            font-weight: 800;
            font-size: 1.6rem;
            margin: 0;
        }
        .header-neo p {
            margin: 0;
            opacity: 0.9;
        }
        .btn-neo-close {
            background: #dc3545;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.5rem;
            font-weight: 700;
            transition: all 0.15s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-neo-close:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #b02a37;
            color: #fff;
        }

        /* Tabela neobrutalista */
        .table-neo {
            border-collapse: collapse;
            width: 100%;
            background: var(--card-bg);
            color: var(--text-color);
        }
        .table-neo th {
            background: var(--primary);
            color: #fff;
            border: 2px solid var(--shadow-color);
            font-weight: 700;
            padding: 0.8rem;
        }
        .table-neo td {
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

        /* Botões neobrutalistas */
        .btn-neo {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 1rem;
            font-weight: 700;
            transition: all 0.15s;
            cursor: pointer;
        }
        .btn-neo:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo:active {
            transform: translate(2px, 2px);
            box-shadow: 1px 1px 0px 0px var(--shadow-color);
        }
        .btn-neo:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Campos de formulário */
        .form-control {
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            padding: 0.4rem 0.8rem;
            background: #fff;
            color: var(--text-color);
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
        }
        .form-control-sm {
            font-size: 0.9rem;
            padding: 0.2rem 0.6rem;
        }

        /* DataTables neobrutalista */
        .dataTables_wrapper {
            color: var(--text-color);
        }
        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid var(--shadow-color);
            border-radius: 30px;
            padding: 0.3rem 1.2rem;
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
            border-radius: 8px;
            padding: 0.3rem;
            background: #fff;
            color: var(--text-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: var(--text-color) !important;
            border: 2px solid var(--shadow-color) !important;
            border-radius: 8px !important;
            padding: 0.3rem 0.8rem !important;
            margin: 0 2px !important;
            font-weight: 600 !important;
            transition: all 0.15s !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary) !important;
            color: #fff !important;
            transform: translateY(-2px);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--shadow-color) !important;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }

        /* Container */
        .container-neo {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .header-neo h1 { font-size: 1.2rem; }
            .header-neo .logo-img { height: 40px; }
        }
    </style>
</head>
<body>

<!-- HEADER NEOBRUTALISTA -->
<header class="header-neo">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="mb-2 mb-md-0 d-flex align-items-center">
                <img src="img/logotocca.jpg" alt="Logotipo Tocca" class="logo-img">
                <div>
                    <h1><i class="fas fa-building me-2"></i> Atualizar Tipologia</h1>
                    <p>SunnyImob</p>
                </div>
            </div>
            <div>
                <a href="#" class="btn-neo-close" onclick="window.close()">
                    <i class="fas fa-times me-1"></i> Fechar
                </a>
            </div>
        </div>
    </div>
</header>

<div class="container container-neo mt-4">
    <div class="table-container">
        <table id="tabelaTipologia" class="table-neo table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Empresa</th>
                    <th>Empreendimento</th>
                    <th>Tipologia</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $sql = "SELECT Empreend_ID, NomeEmpresa, NomeEmpreendimento, Tipologia 
                            FROM empreendimento 
                            ORDER BY NomeEmpreendimento";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($empreendimentos as $empreendimento):
                        $id = htmlspecialchars($empreendimento['Empreend_ID']);
                        $empresa = htmlspecialchars($empreendimento['NomeEmpresa']);
                        $nome = htmlspecialchars($empreendimento['NomeEmpreendimento']);
                        $tipologia = htmlspecialchars($empreendimento['Tipologia'] ?? '');
                ?>
                <tr id="linha_<?php echo $id; ?>">
                    <td><?php echo $id; ?></td>
                    <td><?php echo $empresa; ?></td>
                    <td><?php echo $nome . ' - ' . $empresa; ?></td>
                    <td>
                        <input type="text" class="form-control form-control-sm" 
                               id="tipologia_<?php echo $id; ?>" 
                               value="<?php echo $tipologia; ?>"
                               data-original="<?php echo $tipologia; ?>">
                    </td>
                    <td>
                        <button class="btn-neo" 
                                onclick="atualizarTipologia(<?php echo $id; ?>, this)">
                            <i class="fas fa-sync-alt me-1"></i> Atualizar
                        </button>
                    </td>
                </tr>
                <?php
                    endforeach;
                    
                    if (empty($empreendimentos)) {
                        echo '<tr><td colspan="5" class="text-center">Nenhum empreendimento encontrado</td></tr>';
                    }
                    
                } catch(PDOException $e) {
                    echo '<tr><td colspan="5" class="text-danger">Erro ao carregar dados: ' . 
                         htmlspecialchars($e->getMessage()) . '</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#tabelaTipologia').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json"
        },
        "pageLength": 100,
        "lengthMenu": [[50, 100, 200, -1], [50, 100, 200, "Todos"]],
        "order": [], // Remove ordenação inicial
        "columnDefs": [
            { "orderable": false, "targets": [4] } // Desabilita ordenação na coluna Ação
        ]
    });
});

function atualizarTipologia(id, button) {
    var input = document.getElementById("tipologia_" + id);
    var novaTipologia = input.value;
    var tipologiaOriginal = input.getAttribute('data-original');
    
    // Validação no cliente
    if (novaTipologia.trim() === '') {
        alert("A tipologia não pode estar vazia!");
        return;
    }
    
    if (novaTipologia === tipologiaOriginal) {
        alert("A tipologia não foi alterada!");
        return;
    }
    
    // Formatar para maiúsculas
    novaTipologia = novaTipologia.toUpperCase();
    
    // Mostrar loading no botão
    var originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    button.disabled = true;
    
    $.ajax({
        url: "atualizar_tipologia.php",
        type: "POST",
        data: {
            id: id,
            tipologia: novaTipologia
        },
        success: function(response) {
            try {
                var result = JSON.parse(response);
                if (result.success) {
                    alert(result.message);
                    input.setAttribute('data-original', novaTipologia);
                } else {
                    alert("Erro: " + result.message);
                    input.value = tipologiaOriginal;
                }
            } catch (e) {
                if (response.trim() === "ok") {
                    alert("Tipologia atualizada com sucesso!");
                    input.setAttribute('data-original', novaTipologia);
                } else {
                    alert("Erro: " + response);
                    input.value = tipologiaOriginal;
                }
            }
        },
        error: function(xhr, status, error) {
            alert("Erro na requisição: " + error);
            input.value = tipologiaOriginal;
        },
        complete: function() {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}
</script>
</body>
</html>