<?php
session_start();
require_once 'conn.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Atualizar Nome Empreendimento</title>
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
                    <h1><i class="fas fa-building me-2"></i> Atualizar Nome do Empreendimento</h1>
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
    <table id="tabelaTipologia" class="table-neo table-bordered table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Empresa</th>
                <th>Empreendimento</th>
                <th>Novo Nome</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php
            try {
                $sql = "SELECT * FROM empreendimento ORDER BY NomeEmpreendimento";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($empreendimentos as $empreendimento):
            ?>
            <tr id="linha_<?php echo $empreendimento['Empreend_ID']; ?>">
                <td><?php echo htmlspecialchars($empreendimento['Empreend_ID']); ?></td>
                <td><?php echo htmlspecialchars($empreendimento['NomeEmpresa']); ?></td>
                <td><?php echo htmlspecialchars($empreendimento['NomeEmpreendimento']); ?></td>
                <td>
                    <input type="text" class="form-control form-control-sm" 
                           id="nome_<?php echo $empreendimento['Empreend_ID']; ?>" 
                           value="<?php echo htmlspecialchars($empreendimento['NomeEmpreendimento']); ?>">
                </td>
                <td>
                    <button class="btn-neo" 
                            onclick="atualizarNome(<?php echo $empreendimento['Empreend_ID']; ?>)">
                        <i class="fas fa-sync-alt me-1"></i> Atualizar
                    </button>
                </td>
            </tr>
            <?php
                endforeach;
            } catch(PDOException $e) {
                echo "<tr><td colspan='5' class='text-danger'>Erro ao carregar dados: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
            }
            ?>
        </tbody>
    </table>
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
        "order": [] // Remove ordenação inicial
    });
});

function atualizarNome(id) {
    var novoNome = document.getElementById("nome_" + id).value;
    console.log("Atualizando ID: " + id + ", Novo nome: " + novoNome);
    
    if (novoNome.trim() === '') {
        alert("O nome não pode estar vazio!");
        return;
    }
    
    $.ajax({
        url: "atualizar_nomeempreendimento.php",
        type: "POST",
        data: {
            id: id,
            nome: novoNome
        },
        success: function(response) {
            try {
                var result = JSON.parse(response);
                if (result.success) {
                    alert(result.message);
                    // Atualiza o nome na coluna de exibição também
                    $("#linha_" + id + " td:nth-child(3)").text(novoNome);
                } else {
                    alert("Erro: " + result.message);
                }
            } catch (e) {
                alert("Resposta do servidor: " + response);
            }
        },
        error: function(xhr, status, error) {
            alert("Erro na requisição: " + error);
        }
    });
}
</script>
</body>
</html>