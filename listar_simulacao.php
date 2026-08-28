<?php
// relatorio_fluxo_calculadora.php

// Iniciar sessão se necessário
session_start();

// Definir codificação
header('Content-Type: text/html; charset=UTF-8');

// Incluir arquivo de conexão
require_once 'conn.php';

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
  
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
  
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
  
  <style>
    body {
      background-color: #f8f9fa;
      font-size: 0.9rem;
    }
    .table-responsive {
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      padding: 20px;
      margin-top: 20px;
    }
    .table-header {
      background-color: #343a40;
      color: white;
      border-radius: 10px 10px 0 0;
      padding: 15px 20px;
      margin-bottom: 0;
    }
    .table th {
      white-space: nowrap;
      background-color: #f8f9fa;
    }
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
    <div class="d-flex justify-content-between align-items-center header-actions">
      <h4 class="mb-0"><i class="fas fa-calculator mr-2"></i>Relatório Fluxo Calculadora</h4>
      <div>
        <a href="#" class="btn btn-info" onclick="window.close(); return false;">
          <i class="fas fa-times mr-1"></i> Fechar
        </a>
        <button id="btnExport" class="btn btn-success">
          <i class="fas fa-file-excel mr-1"></i> Exportar
        </button>
      </div>
    </div>
    
    <div class="table-responsive">
      <table id="tabelaFluxo" class="table table-striped table-bordered table-hover" style="width:100%">
        <thead class="thead-light">
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
            // Formatar data
            $dataCriacao = date('d/m/Y H:i:s', strtotime($row['DataCriacao']));
            
            // Formatar números
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
    
    <footer class="text-center text-muted small mb-3">
      Relatório gerado em <?php echo date('d/m/Y H:i:s'); ?> &copy; Sunny System
    </footer>
  </div>

  <!-- jQuery first, then Popper.js, then Bootstrap JS -->
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  
  <!-- DataTables JS -->
  <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
  
  <!-- Exportação para Excel -->
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
            
        table.buttons( {
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
        } );
    });
  </script>
</body>
</html>