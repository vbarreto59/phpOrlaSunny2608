<?php
// relatorio_fluxo_calculadora.php – com suporte a temas (theme_config.php)

session_start();
header('Content-Type: text/html; charset=UTF-8');

require_once 'conn.php';
require_once 'theme_config.php'; // Tema centralizado

// Obter todos os registros da FluxoCalculadora
try {
    $sql = "SELECT ID, DataCriacao, Usuario, Empreendimento_Id, Empreendimento_Nome, 
                   Cliente, Corretor, Unidade, UnidadeM2, ValorUnidade, 
                   ValorUnidade/UnidadeM2 AS VM2 
            FROM fluxocalculadora 
            ORDER BY ID DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $registros = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Erro ao buscar registros: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Fluxo Calculadora - Relatório</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
  
  <style>
    /* ===== VARIÁVEIS DO TEMA ===== */
    :root {
      --primary: <?php echo $theme['primary']; ?>;
      --primary-dark: <?php echo $theme['primary_dark']; ?>;
      --primary-light: <?php echo $theme['primary_light']; ?>;
      --accent: <?php echo $theme['accent']; ?>;
      --body-bg: <?php echo $theme['body_bg']; ?>;
    }

    body {
      background: var(--body-bg);
      font-size: 0.9rem;
      min-height: 100vh;
      font-family: 'Segoe UI', Roboto, sans-serif;
    }

    /* Cabeçalho com tema */
    .header-theme {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 1.2rem 1.5rem;
      border-radius: 12px 12px 0 0;
      margin-bottom: 0;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .header-theme h4 {
      margin: 0;
      font-weight: 600;
    }

    /* Botões do cabeçalho */
    .btn-fechar {
      background-color: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.3);
      color: white;
      transition: 0.3s;
    }
    .btn-fechar:hover {
      background-color: rgba(255,255,255,0.3);
      border-color: white;
      color: white;
    }

    .btn-exportar {
      background-color: var(--accent);
      border-color: var(--accent);
      color: white;
    }
    .btn-exportar:hover {
      background-color: var(--primary-dark);
      border-color: var(--primary-dark);
      color: white;
    }

    /* Área da tabela */
    .table-responsive {
      background-color: white;
      border-radius: 0 0 12px 12px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.08);
      padding: 20px;
      margin-top: 0;
      border-top: 3px solid var(--primary);
    }

    .table th {
      background-color: var(--primary-light);
      color: var(--primary-dark);
      font-weight: 600;
      border-bottom: 2px solid var(--primary);
    }

    /* DataTables – campos de pesquisa e seleção com a cor primária */
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
      border-color: var(--primary);
    }
    .dataTables_wrapper .dataTables_filter input:focus,
    .dataTables_wrapper .dataTables_length select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.2rem rgba(<?php echo hexdec(substr($theme['primary'],1,2)) . ', ' . hexdec(substr($theme['primary'],3,2)) . ', ' . hexdec(substr($theme['primary'],5,2)); ?>, 0.25);
    }

    /* Rodapé */
    footer {
      background: var(--primary-dark);
      color: rgba(255,255,255,0.8);
      padding: 1rem 0;
      margin-top: 30px;
      border-radius: 8px;
    }
    footer .text-muted {
      color: rgba(255,255,255,0.7) !important;
    }

    /* Badge de status (se houver) – mantido */
    .badge-status {
      font-size: 0.85em;
      padding: 0.5em 0.75em;
      border-radius: 50px;
      min-width: 70px;
      display: inline-block;
      text-align: center;
    }
    .valor {
      text-align: right;
      white-space: nowrap;
    }
    .header-actions {
      margin-bottom: 20px;
    }
    .dataTables_length select {
      width: 70px;
    }
  </style>
</head>
<body>

<div class="container-fluid">

  <!-- ===== CABEÇALHO COM TEMA ===== -->
  <div class="header-theme d-flex justify-content-between align-items-center">
    <h4><i class="fas fa-calculator mr-2"></i>Relatório Fluxo Calculadora</h4>
    <div>
      <a href="#" class="btn btn-fechar btn-sm" onclick="window.close(); return false;">
        <i class="fas fa-times mr-1"></i> Fechar
      </a>
      <button id="btnExport" class="btn btn-exportar btn-sm">
        <i class="fas fa-file-excel mr-1"></i> Exportar
      </button>
    </div>
  </div>

  <!-- ===== TABELA ===== -->
  <div class="table-responsive">
    <table id="tabelaFluxo" class="table table-striped table-bordered table-hover" style="width:100%">
      <thead>
        <tr>
          <th>ID</th>
          <th>Data Criação</th>
          <th>Usuário</th>
          <th>Empreendimento</th>
          <th>Cliente</th>
          <th>Corretor</th>
          <th>Unidade</th>
          <th>M²</th>
          <th>Valor Unidade</th>
          <th>Valor M²</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        foreach ($registros as $row):
          $dataCriacao = date('d/m/Y H:i:s', strtotime($row['DataCriacao']));
          $unidadeM2 = number_format($row['UnidadeM2'] ?? 0, 2, ',', '.');
          $valorUnidade = number_format($row['ValorUnidade'] ?? 0, 2, ',', '.');
          $vm2 = number_format($row['VM2'] ?? 0, 2, ',', '.');
        ?>
        <tr>
          <td><?php echo htmlspecialchars($row['ID'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($dataCriacao); ?></td>
          <td><?php echo htmlspecialchars($row['Usuario'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($row['Empreendimento_Nome'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($row['Cliente'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($row['Corretor'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($row['Unidade'] ?? ''); ?></td>
          <td class="valor"><?php echo $unidadeM2; ?></td>
          <td class="valor">R$ <?php echo $valorUnidade; ?></td>
          <td class="valor">R$ <?php echo $vm2; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ===== RODAPÉ ===== -->
  <footer class="text-center small">
    Relatório gerado em <?php echo date('d/m/Y H:i:s'); ?> &copy; Sunny System
  </footer>
</div>

<!-- ===== SCRIPTS ===== -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.4/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.html5.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#tabelaFluxo').DataTable({
        "pageLength": 100,
        "lengthMenu": [ [50, 100, 200, -1], [50, 100, 200, "Todos"] ],
        "order": [[0, "desc"]],
        "language": {
            "sEmptyTable": "Nenhum registro encontrado",
            "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
            "sInfoEmpty": "Mostrando 0 até 0 de 0 registros",
            "sInfoFiltered": "(Filtrados de _MAX_ registros)",
            "sInfoPostFix": "",
            "sInfoThousands": ".",
            "sLengthMenu": "_MENU_ resultados por página",
            "sLoadingRecords": "Carregando...",
            "sProcessing": "Processando...",
            "sZeroRecords": "Nenhum registro encontrado",
            "sSearch": "Pesquisar:",
            "oPaginate": {
                "sNext": "Próximo",
                "sPrevious": "Anterior",
                "sFirst": "Primeiro",
                "sLast": "Último"
            },
            "oAria": {
                "sSortAscending": ": Ordenar colunas de forma ascendente",
                "sSortDescending": ": Ordenar colunas de forma descendente"
            },
            "decimal": ",",
            "thousands": "."
        },
        "dom": '<"top"f>rt<"bottom"lip><"clear">',
        "initComplete": function() {
            $('.dataTables_filter input').addClass('form-control').attr('placeholder', 'Pesquisar...');
            $('.dataTables_length select').addClass('form-control');
        },
        "columnDefs": [
            { "targets": [7,8,9], "className": "text-right" },
            { "responsivePriority": 1, "targets": [0,1,2] },
            { "responsivePriority": 2, "targets": [3,4,5] }
        ]
    });

    // Botão de exportação
    $('#btnExport').click(function() {
        table.button('.buttons-excel').trigger();
    });

    // Configurar botões de exportação
    table.buttons().container()
        .appendTo('#tabelaFluxo_wrapper .col-md-6:eq(0)')
        .addClass('btn-group');

    table.buttons({
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm',
                title: 'Relatorio_Fluxo_Calculadora',
                exportOptions: {
                    columns: ':visible'
                }
            }
        ]
    });
});
</script>
</body>
</html>