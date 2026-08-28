<?php
// Inclui a conexão PDO e o tema
require_once 'conn.php';
require_once 'theme_config.php'; // <-- CARREGA O TEMA

// Consulta com PDO
$sql = "SELECT empreendimento.*, empresa.NomeEmpresa 
        FROM empreendimento 
        INNER JOIN empresa ON empreendimento.Empresa_Id = empresa.Empresa_Id 
        ORDER BY empreendimento.NomeEmpreendimento";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $empreendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cont = 0;
} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="600">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Empreendimentos</title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
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
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
        }

        /* Estilos Gerais */
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            background: var(--bg-body);
            color: var(--text-color);
            padding: 20px;
            min-height: 100vh;
        }

        /* Header neobrutalista */
        header.container-fluid {
            background: var(--primary);
            color: #fff;
            border-bottom: 4px solid var(--shadow-color);
            box-shadow: 0 4px 0px 0px var(--shadow-color);
            padding: 0.8rem 0;
        }
        header .logo-img {
            height: 50px;
            border: 2px solid var(--shadow-color);
            border-radius: 10px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            background: #fff;
            padding: 4px;
            margin-right: 15px;
        }
        header h1 {
            font-weight: 800;
            font-size: 1.6rem;
            margin: 0;
        }
        header p {
            margin: 0;
            opacity: 0.9;
        }
        .btn-neo-close {
            background: var(--danger);
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

        /* Botões neobrutalistas */
        .btn {
            padding: 6px 14px;
            border-radius: 8px;
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            display: inline-block;
            transition: all 0.15s;
        }
        .btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
        }
        .btn-success {
            background: var(--success);
            color: #fff;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-info {
            background: var(--info);
            color: #fff;
        }
        .btn-info:hover {
            background: #138496;
        }
        .btn-warning {
            background: var(--warning);
            color: #000;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-danger {
            background: var(--danger);
            color: #fff;
        }
        .btn-danger:hover {
            background: #b02a37;
        }
        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-dark {
            background: var(--dark);
            color: #fff;
        }
        .btn-dark:hover {
            background: #23272b;
        }
        .btn-small {
            padding: 3px 10px;
            font-size: 12px;
        }
        .btn-neo-toggle {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: 2px solid var(--shadow-color);
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
            border-radius: 30px;
            padding: 0.4rem 1.5rem;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-neo-toggle:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px 0px var(--shadow-color);
            background: var(--btn-hover);
            color: #fff;
        }

        .disabled-link {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Tabela neobrutalista */
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            color: var(--text-color);
        }
        th, td {
            padding: 8px;
            border: 2px solid var(--shadow-color);
            font-size: 14px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: var(--primary);
            color: #fff;
        }
        img {
            max-width: 100px;
            height: auto;
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            box-shadow: 3px 3px 0px 0px var(--shadow-color);
        }

        .btns-links {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        /* Badges */
        .badge-vendido {
            background: var(--success);
            color: #fff;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.75rem;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            display: inline-block;
        }
        .badge-oculto {
            background: var(--danger);
            color: #fff;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.75rem;
            border: 2px solid var(--shadow-color);
            box-shadow: 2px 2px 0px 0px var(--shadow-color);
            display: inline-block;
        }

        /* Cards neobrutalistas */
        .card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .card {
            background: var(--card-bg);
            color: var(--text-color);
            border-radius: 16px;
            padding: 15px;
            width: 300px;
            border: 3px solid var(--shadow-color);
            box-shadow: 6px 6px 0px 0px var(--shadow-color);
            transition: all 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 8px 8px 0px 0px var(--shadow-color);
        }
        .card img {
            max-width: 100%;
            height: auto;
            margin-top: 10px;
        }
        .card h4 {
            margin: 10px 0 5px;
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
        }
        .card .btns-links {
            margin-top: 10px;
        }

        /* DataTables neobrutalista */
        .dataTables_wrapper {
            color: var(--text-color);
        }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            color: var(--text-color);
            background: var(--card-bg);
            border: 2px solid var(--shadow-color);
            border-radius: 8px;
            padding: 0.3rem 0.8rem;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            box-shadow: 4px 4px 0px 0px var(--shadow-color);
            border-color: var(--primary);
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

        /* Responsivo */
        @media (max-width: 768px) {
            body { padding: 10px; }
            header h1 { font-size: 1.2rem; }
            .card { width: 100%; }
        }
    </style>
</head>
<body>

<!-- HEADER NEOBRUTALISTA -->
<header class="container-fluid">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="mb-2 mb-md-0 d-flex align-items-center">
                <img src="img/logotocca.jpg" alt="Logotipo Tocca" class="logo-img">
                <div>
                    <h1><i class="fas fa-building me-2"></i> Empreendimentos</h1>
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

<div class="container mt-4">
    <a href="gestao_empreend_create.php" class="btn btn-success" target="_blank">Novo Empreendimento</a>
    <button id="toggleView" class="btn-neo-toggle ms-2">Alternar para Cards</button>

    <br><br>

    <!-- TABELA NEOBRUTALISTA -->
    <table id="tabelaEmpreendimentos" class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>#</th>               
                <th>ID</th>
                <th>Localidade</th>
                <th>Empreendimento</th>
                <th>Links</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($empreendimentos as $emp): ?>
            <?php $cont++; ?>
            <tr>
                <td><?php echo $cont; ?></td>
                <td><?php echo htmlspecialchars($emp['Empreend_ID']); ?></td>
                <td><?php echo mb_strtoupper(htmlspecialchars($emp['Localidade'])); ?></td>
                <td>
                    <?php echo mb_strtoupper(htmlspecialchars($emp['NomeEmpresa'])); ?> - <?php echo mb_strtoupper(htmlspecialchars($emp['NomeEmpreendimento'])); ?>
                    <?php if (!empty($emp['Vendido']) && $emp['Vendido']): ?>
                        <span class="badge-vendido">100% Vendido</span>
                    <?php endif; ?>
                    <?php if (!empty($emp['Ocultar']) && $emp['Ocultar']): ?>
                        <span class="badge-oculto">Oculto</span>
                    <?php endif; ?>    
                    <br>
                    <?php if (file_exists('card/' . $emp['Empreend_ID'] . '.jpg')): ?>
                        <img src="card/<?php echo $emp['Empreend_ID']; ?>.jpg" alt="Imagem" style="margin-top:5px;">
                    <?php endif; ?>
                </td>
                <td class="btns-links">
                    <?php // LinkMapa ?>
                    <?php if (!empty($emp['LinkMapa'])): ?>
                        <a href="<?php echo htmlspecialchars($emp['LinkMapa']); ?>" target="_blank" class="btn btn-small btn-secondary">Mapa</a>
                    <?php else: ?>
                        <span title="Mapa indisponível" class="btn btn-small btn-secondary disabled-link">🚫</span>
                    <?php endif; ?>

                    <?php // LinkFicha ?>
                    <?php if (!empty($emp['LinkFicha'])): ?>
                        <a href="<?php echo htmlspecialchars($emp['LinkFicha']); ?>" target="_blank" class="btn btn-small btn-success">Ficha</a>
                    <?php else: ?>
                        <span title="Ficha indisponível" class="btn btn-small btn-success disabled-link">🚫</span>
                    <?php endif; ?>

                    <?php // LinkSite ?>
                    <?php if (!empty($emp['LinkSite'])): ?>
                        <a href="<?php echo htmlspecialchars($emp['LinkSite']); ?>" target="_blank" class="btn btn-small btn-danger">Site</a>
                    <?php else: ?>
                        <span title="Site indisponível" class="btn btn-small btn-danger disabled-link">🚫</span>
                    <?php endif; ?>

                    <?php // LinkVideo ?>
                    <?php if (!empty($emp['LinkVideo'])): ?>
                        <a href="<?php echo htmlspecialchars($emp['LinkVideo']); ?>" target="_blank" class="btn btn-small btn-warning">Vídeo</a>
                    <?php else: ?>
                        <span title="Vídeo indisponível" class="btn btn-small btn-warning disabled-link">🚫</span>
                    <?php endif; ?>

                    <?php // LinkTocca ?>
                    <?php if (!empty($emp['LinkTocca'])): ?>
                        <a href="<?php echo htmlspecialchars($emp['LinkTocca']); ?>" target="_blank" class="btn btn-small btn-info">Drive</a>
                    <?php else: ?>
                        <span title="Drive indisponível" class="btn btn-small btn-info disabled-link">🚫</span>
                    <?php endif; ?>

                    <?php // LinkConstrutora ?>
                    <?php if (!empty($emp['LinkConstrutora'])): ?>
                        <a href="<?php echo htmlspecialchars($emp['LinkConstrutora']); ?>" target="_blank" class="btn btn-small btn-dark">Drive Const.</a>
                    <?php else: ?>
                        <span title="Drive Const. indisponível" class="btn btn-small btn-dark disabled-link">🚫</span>
                    <?php endif; ?>

                    <?php // LinkTabela ?>
                    <?php if (!empty($emp['LinkTabela'])): ?>
                        <a href="<?php echo htmlspecialchars($emp['LinkTabela']); ?>" target="_blank" class="btn btn-small btn-secondary">Tabela</a>
                    <?php else: ?>
                        <span title="Tabela indisponível" class="btn btn-small btn-secondary disabled-link">🚫</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php 
                    // Verificação de permissão (adapte conforme sua lógica)
                    // if (UsuarioPodeEditar()): 
                    if (true): // Substituir pela sua verificação real
                    ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                        <a href="gestao_empreend_update.php?id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-warning" target="_blank">Editar</a>
                        <a href="empreend_ficha1.php?id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-info" target="_blank">Card</a>
                        <a href="empreend_v3_imagem_paste.php?Empreend_id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-info" target="_blank">Imagem</a>
                        <a href="gestao_empreend_caract_update2.php?id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-success" target="_blank">Caract.</a>
                        <a href="gestao_empreend_links_update.php?id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-success" target="_blank">Links</a>
                        <a href="gestao_empreend_delete.php?id=<?php echo $emp['Empreend_ID']; ?>" class="btn btn-danger" onclick="return confirm('Deseja excluir?');">Excluir</a>    
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div id="cardView" style="display:none; margin-top: 20px;"></div>
</div>

<script>
$(document).ready(function() {
    // Inicialização do DataTables
    if (!$.fn.DataTable.isDataTable('#tabelaEmpreendimentos')) {
        $('#tabelaEmpreendimentos').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json"
            },
            "pageLength": 100,
            "order": [[ 1, "desc" ]]
        });
    }

    // Função para gerar os cards
    function gerarCards() {
        const table = $('#tabelaEmpreendimentos').DataTable();
        const filteredData = table.rows({ search: 'applied' }).data().toArray();
        const cardView = $('#cardView');
        cardView.empty();

        const container = $('<div class="card-container"></div>');

        filteredData.forEach(row => {
            const localidade = row[2];
            const empreendimentoHtml = row[3];
            const linksHtml = row[4];
            const acoesHtml = row[5];

            const card = $(`
                <div class="card">
                    <h4>${localidade}</h4>
                    ${empreendimentoHtml}
                    <div class="btns-links">${linksHtml}</div>
                    <div style="margin-top:10px;">${acoesHtml}</div>
                </div>
            `);
            container.append(card);
        });
        cardView.append(container);
    }

    // --- Lógica para alternar a visualização ---
    function alternarVisualizacao(isMobile) {
        const tabela = $('#tabelaEmpreendimentos');
        const cards = $('#cardView');
        const wrapper = $('#tabelaEmpreendimentos_wrapper');
        const filter = wrapper.find('.dataTables_filter');
        const length = wrapper.find('.dataTables_length');
        const info = wrapper.find('.dataTables_info');
        const paginate = wrapper.find('.dataTables_paginate');
        const toggleButton = $('#toggleView');

        if (isMobile) {
            tabela.hide();
            gerarCards();
            cards.show();
            toggleButton.text('Alternar para Tabela');
            toggleButton.off('click').on('click', function() {
                tabela.show();
                cards.hide();
                $(this).text('Alternar para Cards');
            });
        } else {
            tabela.show();
            cards.hide();
            toggleButton.text('Alternar para Cards');
            toggleButton.off('click').on('click', function() {
                if (tabela.is(':visible')) {
                    tabela.hide();
                    gerarCards();
                    cards.show();
                    $(this).text('Alternar para Tabela');
                } else {
                    tabela.show();
                    cards.hide();
                    $(this).text('Alternar para Cards');
                }
            });
        }

        // Mantém os controles do DataTables visíveis em ambos os modos
        filter.show();
        length.show();
        info.show();
        paginate.show();
    }

    // Verifica o tamanho da tela na carga da página
    const isMobile = window.matchMedia("(max-width: 768px)").matches;
    alternarVisualizacao(isMobile);

    // Atualiza a visualização quando o tamanho da tela muda
    $(window).on('resize', function() {
        const isMobile = window.matchMedia("(max-width: 768px)").matches;
        alternarVisualizacao(isMobile);
    });

    // Atualiza os cards ao digitar no campo de busca
    $('#tabelaEmpreendimentos_filter input').on('keyup', function () {
        if ($('#cardView').is(':visible')) {
            gerarCards();
        }
    });
});
</script>
</body>
</html>