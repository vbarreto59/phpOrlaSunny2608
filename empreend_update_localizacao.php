<?php
require_once 'conn.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Atualizar Localização e Mapa</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">
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
            display: inline-block;
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
        .btn-neo-primary {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 1rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-primary:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }
        .btn-neo-info {
            background: #17a2b8;
            color: #fff;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 1rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-info:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: #138496;
            color: #fff;
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
        .small-input {
            width: 200px;
        }
        .very-small-input {
            width: 150px;
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
        .dataTables_wrapper .dt-buttons .dt-button {
            background: var(--btn-bg) !important;
            color: var(--btn-text) !important;
            border: 2px solid var(--shadow-color) !important;
            box-shadow: 2px 2px 0px 0px var(--shadow-color) !important;
            border-radius: 8px !important;
            padding: 0.3rem 1rem !important;
            font-weight: 700 !important;
            transition: all 0.15s !important;
            margin-right: 4px;
        }
        .dataTables_wrapper .dt-buttons .dt-button:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 4px 4px 0px 0px var(--shadow-color) !important;
            background: var(--btn-hover) !important;
            color: #fff !important;
        }

        .action-buttons {
            white-space: nowrap;
        }

        /* Container */
        .container-neo {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .header-neo h1 { font-size: 1.2rem; }
            .header-neo .logo-img { height: 40px; }
            .small-input, .very-small-input { width: 100%; }
            .action-buttons .btn-neo { margin-bottom: 4px; }
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
                    <h1><i class="fas fa-building me-2"></i> Editar Localização dos Empreendimentos</h1>
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
    <?php
    try {
        $sql = "SELECT Empreend_ID, NomeEmpresa, NomeEmpreendimento, Localidade, Localizacao, LinkMapa 
                FROM empreendimento 
                ORDER BY NomeEmpreendimento";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <table id="tabelaLocalizacao" class="table-neo table-bordered table-striped" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Empresa</th>
                <th>Empreendimento</th>
                <th>Localização</th>
                <th>Link do Mapa</th>
                <th class="action-buttons">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($empreendimentos as $empreendimento): 
                $id = htmlspecialchars($empreendimento['Empreend_ID']);
                $empresa = htmlspecialchars($empreendimento['NomeEmpresa']);
                $nome = htmlspecialchars($empreendimento['NomeEmpreendimento']);
                $localidade = htmlspecialchars($empreendimento['Localidade'] ?? '');
                $localizacao = htmlspecialchars($empreendimento['Localizacao'] ?? '');
                $linkmapa = htmlspecialchars($empreendimento['LinkMapa'] ?? '');
            ?>
            <tr id="linha_<?php echo $id; ?>">
                <td><?php echo $id; ?></td>
                <td><?php echo $empresa; ?></td>
                <td><?php echo $localidade . '- ' . $nome; ?></td>
                <td>
                    <input type="text" class="form-control form-control-sm small-input" 
                           id="localizacao_<?php echo $id; ?>" 
                           value="<?php echo $localizacao; ?>"
                           data-original="<?php echo $localizacao; ?>">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm very-small-input" 
                           id="linkmapa_<?php echo $id; ?>" 
                           value="<?php echo $linkmapa; ?>"
                           data-original="<?php echo $linkmapa; ?>">
                </td>
                <td class="action-buttons">
                    <button class="btn-neo-primary mr-2" 
                            onclick="atualizarCampos(<?php echo $id; ?>, this)">
                        <i class="fas fa-sync-alt me-1"></i> Atualizar
                    </button>
                    <button class="btn-neo-info" 
                            onclick="abrirMapa(<?php echo $id; ?>)">
                        <i class="fas fa-map-marked-alt me-1"></i> Mapa
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($empreendimentos)): ?>
            <tr>
                <td colspan="6" class="text-center">Nenhum empreendimento encontrado</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger" style="border: 2px solid var(--shadow-color); box-shadow: 4px 4px 0px 0px var(--shadow-color); border-radius: 12px; background: var(--card-bg); color: var(--text-color);">Erro ao carregar dados: ' . 
             htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>
</div>

<!-- jQuery (PRIMEIRO) -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<!-- DataTables Core -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<!-- DEPENDÊNCIAS para botões Excel/PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

<!-- DataTables Buttons -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#tabelaLocalizacao').DataTable({
        "language": {
            "emptyTable": "Nenhum dado disponível na tabela",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 a 0 de 0 registros",
            "infoFiltered": "(filtrado de _MAX_ registros no total)",
            "infoThousands": ".",
            "lengthMenu": "Mostrar _MENU_ registros",
            "loadingRecords": "Carregando...",
            "processing": "Processando...",
            "search": "Buscar:",
            "searchPlaceholder": "Digite para pesquisar...",
            "zeroRecords": "Nenhum registro encontrado",
            "paginate": {
                "first": "Primeiro",
                "last": "Último",
                "next": "Próximo",
                "previous": "Anterior"
            },
            "aria": {
                "sortAscending": ": ativar para ordenar coluna ascendente",
                "sortDescending": ": ativar para ordenar coluna descendente"
            }
        },
        "pageLength": 25,
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": [3, 4, 5] },
            { "width": "80px", "targets": [0] },
            { "width": "100px", "targets": [5] }
        ],
        "dom": 'Bfrtip',
        "buttons": [
            'copy', 'excel', 'pdf', 'print'
        ]
    });
});

function atualizarCampos(id, button) {
    var inputLocalizacao = document.getElementById("localizacao_" + id);
    var inputLinkMapa = document.getElementById("linkmapa_" + id);
    var localizacao = inputLocalizacao.value;
    var linkmapa = inputLinkMapa.value;
    var localizacaoOriginal = inputLocalizacao.getAttribute('data-original');
    var linkmapaOriginal = inputLinkMapa.getAttribute('data-original');
    
    if (localizacao === localizacaoOriginal && linkmapa === linkmapaOriginal) {
        alert("Nenhum dado foi alterado.");
        return;
    }
    
    var originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    button.disabled = true;
    
    $.ajax({
        url: "atualizar_localizacao.php",
        type: "POST",
        dataType: "text",
        data: {
            id: id,
            localizacao: localizacao,
            linkmapa: linkmapa
        },
        success: function(response) {
            try {
                var result = JSON.parse(response);
                if (result.success === "OK") {
                    alert("Atualizado com sucesso!");
                    inputLocalizacao.setAttribute('data-original', localizacao);
                    inputLinkMapa.setAttribute('data-original', linkmapa);
                } else {
                    alert("Erro: " + result.message);
                    inputLocalizacao.value = localizacaoOriginal;
                    inputLinkMapa.value = linkmapaOriginal;
                }
            } catch (e) {
                var resposta = String(response || "").trim();
                if (resposta.toUpperCase() === "OK") {
                    alert("Atualizado com sucesso!");
                    inputLocalizacao.setAttribute('data-original', localizacao);
                    inputLinkMapa.setAttribute('data-original', linkmapa);
                } else {
                    alert("Erro: " + resposta);
                    inputLocalizacao.value = localizacaoOriginal;
                    inputLinkMapa.value = linkmapaOriginal;
                }
            }
        },
        error: function(xhr, status, error) {
            alert("Erro ao atualizar: " + error);
            inputLocalizacao.value = localizacaoOriginal;
            inputLinkMapa.value = linkmapaOriginal;
        },
        complete: function() {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    });
}

function abrirMapa(id) {
    var linkmapa = document.getElementById("linkmapa_" + id).value.trim();
    var localizacao = document.getElementById("localizacao_" + id).value.trim();
    
    if (linkmapa !== "") {
        window.open(linkmapa, '_blank');
    } else if (localizacao !== "") {
        var url = "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(localizacao);
        window.open(url, '_blank');
    } else {
        alert("Nenhum link de mapa ou localização definida para este empreendimento.");
    }
}
</script>
</body>
</html>