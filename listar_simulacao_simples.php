<?php
// relatorio_preco_m2.php

// Iniciar sessão se necessário
session_start();

// Definir codificação
header('Content-Type: text/html; charset=UTF-8');

// Incluir arquivo de conexão
require_once 'conn.php';

// Obter todos os registros da FluxoCalculadora com INNER JOIN
try {
    // Usando sua conexão $conn do arquivo conn.php
    $conn = GetOpenConnectionLog();
    
    // Primeira query: dados completos
    $sqlFluxo = "SELECT f.ID, f.DataCriacao, e.Localidade, e.NomeEmpreendimento, 
                        f.UnidadeM2, f.ValorUnidade, f.ValorUnidade/f.UnidadeM2 AS VM2 
                 FROM fluxocalculadora f 
                 INNER JOIN empreendimento e ON f.Empreendimento_Id = e.Empreend_ID 
                 WHERE f.ID >= 111 
                 ORDER BY f.ID DESC";
    
    $stmtFluxo = $conn->prepare($sqlFluxo);
    $stmtFluxo->execute();
    $registrosFluxo = $stmtFluxo->fetchAll();
    
    // Segunda query: dados para cálculo da média
    $sqlDadosMedia = "SELECT f.DataCriacao, f.UnidadeM2, f.ValorUnidade, e.Localidade 
                      FROM fluxocalculadora f 
                      INNER JOIN empreendimento e ON f.Empreendimento_Id = e.Empreend_ID 
                      WHERE f.ID >= 111";
    
    $stmtDadosMedia = $conn->prepare($sqlDadosMedia);
    $stmtDadosMedia->execute();
    $dadosParaMedia = $stmtDadosMedia->fetchAll();
    
} catch (PDOException $e) {
    die("Erro ao buscar registros: " . $e->getMessage());
}

// Funções de formatação (já que seu conn.php não tem estas funções)
function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function formatNumber($value, $decimals = 2) {
    return number_format($value, $decimals, ',', '.');
}

// --- Preparação dos dados para a segunda tabela (Média de M2 por Localidade, Ano e Mês) ---
$mediaData = [];

// Processar dados para calcular média
foreach ($dadosParaMedia as $dado) {
    $localidade = $dado['Localidade'];
    $dataCriacao = new DateTime($dado['DataCriacao']);
    $ano = $dataCriacao->format('Y');
    $mes = $dataCriacao->format('n');
    
    $valorUnidade = floatval($dado['ValorUnidade']);
    $unidadeM2 = floatval($dado['UnidadeM2']);
    
    // Calcular VM2
    $vm2Calculado = 0;
    if ($unidadeM2 > 0) {
        $tempVM2 = $valorUnidade / $unidadeM2;
        
        // Aplicar filtro: Ignorar VM2 abaixo de 5000
        if ($tempVM2 >= 5000) {
            $vm2Calculado = $tempVM2;
        }
    }
    
    // Se VM2 válido e >= 5000, adicionar para cálculo da média
    if ($vm2Calculado > 0) {
        $chave = $localidade . '_' . $ano . '_' . $mes;
        
        if (!isset($mediaData[$chave])) {
            $mediaData[$chave] = [
                'localidade' => $localidade,
                'ano' => (int)$ano,
                'mes' => (int)$mes,
                'soma' => 0,
                'contagem' => 0
            ];
        }
        
        $mediaData[$chave]['soma'] += $vm2Calculado;
        $mediaData[$chave]['contagem']++;
    }
}

// Calcular média e preparar array para exibição
$mediaVM2 = [];
foreach ($mediaData as $item) {
    $media = ($item['contagem'] > 0) ? $item['soma'] / $item['contagem'] : 0;
    
    $mediaVM2[] = [
        'localidade' => $item['localidade'],
        'ano' => $item['ano'],
        'mes' => $item['mes'],
        'media' => $media
    ];
}

// Ordenar por localidade, ano descendente, mês descendente
usort($mediaVM2, function($a, $b) {
    if ($a['localidade'] === $b['localidade']) {
        if ($a['ano'] === $b['ano']) {
            return $b['mes'] - $a['mes'];
        }
        return $b['ano'] - $a['ano'];
    }
    return strcmp($a['localidade'], $b['localidade']);
});
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Relatório Valor M2</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
  
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.4/css/buttons.bootstrap4.min.css">
  
  <!-- ECharts -->
  <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
  
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
      margin-bottom: 30px;
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
    #graficoMediaM2 {
      height: 500px;
      min-height: 500px;
    }
  </style>
</head>
<body>

  <div class="container">
    <div class="d-flex justify-content-between align-items-center header-actions">
      <h4 class="mb-0"><i class="fas fa-calculator mr-2"></i>Relatório Preço M2</h4>
      <div>
        <a href="#" class="btn btn-info" onclick="window.close(); return false;">
          <i class="fas fa-times mr-1"></i> Fechar
        </a>
        <button id="btnExportFluxo" class="btn btn-success">
          <i class="fas fa-file-excel mr-1"></i> Exportar Fluxo
        </button>
      </div>
    </div>
    
    <!-- Tabela 1: Dados Completos -->
    <div class="table-responsive">
      <table id="tabelaFluxo" class="table table-striped table-bordered table-hover" style="width:100%">
        <thead class="thead-light">
          <tr>
            <th>ID</th>
            <th>Data Criação</th>
            <th>Empreendimento</th>
            <th>Localidade</th>
            <th>M²</th>
            <th>Valor Unidade</th>
            <th>Valor M²</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          foreach ($registrosFluxo as $registro):
            $vm2 = isset($registro['VM2']) ? floatval($registro['VM2']) : 0;
            
            // Aplicar filtro: só mostrar se VM2 >= 5000
            if ($vm2 >= 5000):
                $dataCriacao = isset($registro['DataCriacao']) ? date('d/m/Y H:i:s', strtotime($registro['DataCriacao'])) : '';
          ?>
          <tr>
            <td><?php echo htmlspecialchars($registro['ID'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($dataCriacao); ?></td>
            <td><?php echo htmlspecialchars($registro['NomeEmpreendimento'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($registro['Localidade'] ?? ''); ?></td>
            <td class="valor"><?php echo formatNumber($registro['UnidadeM2'] ?? 0); ?></td>
            <td class="valor"><?php echo formatCurrency($registro['ValorUnidade'] ?? 0); ?></td>
            <td class="valor"><?php echo formatCurrency($vm2); ?></td>
          </tr>
          <?php 
            endif;
          endforeach; 
          ?>
        </tbody>
      </table>
    </div>

    <!-- Tabela 2: Média por Localidade, Ano e Mês -->
    <div class="d-flex justify-content-between align-items-center header-actions mt-5">
      <h4 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Média do Valor M² por Localidade e Mês</h4>
      <div>
        <button id="btnExportMedia" class="btn btn-success">
          <i class="fas fa-file-excel mr-1"></i> Exportar Média
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table id="tabelaMediaM2" class="table table-striped table-bordered table-hover" style="width:100%">
        <thead class="thead-light">
          <tr>
            <th>Localidade</th>
            <th>Ano</th>
            <th>Mês</th>
            <th>Média Valor M²</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          foreach ($mediaVM2 as $media):
            $nomeMes = [
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
            ];
          ?>
          <tr>
            <td><?php echo htmlspecialchars($media['localidade']); ?></td>
            <td><?php echo htmlspecialchars($media['ano']); ?></td>
            <td><?php echo $nomeMes[$media['mes']] . ' (' . $media['mes'] . ')'; ?></td>
            <td class="valor"><?php echo formatCurrency($media['media']); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Gráfico: Média do Valor M² -->
    <div class="d-flex justify-content-between align-items-center header-actions mt-5">
      <h4 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Gráfico - Média do Valor M²</h4>
      <div>
        <button id="toggleSeriesBtn" class="btn btn-primary btn-sm">
          <i class="fas fa-eye-slash mr-1"></i> Ocultar/Mostrar Localidades
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <div id="graficoMediaM2"></div>
    </div>
    
    <footer class="text-center text-muted small mb-3">
      Relatório gerado em <?php echo date('d/m/Y H:i:s'); ?> &copy; Sunny System
    </footer>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  
  <!-- DataTables -->
  <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
  
  <!-- Exportação Excel -->
  <script src="https://cdn.datatables.net/buttons/1.6.4/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.bootstrap4.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/1.6.4/js/buttons.html5.min.js"></script>
  
  <script>
    $(document).ready(function() {
        // DataTables para a primeira tabela
        var tabelaFluxo = $('#tabelaFluxo').DataTable({
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
                $('#tabelaFluxo_filter input').addClass('form-control').attr('placeholder', 'Pesquisar...');
                $('#tabelaFluxo_length select').addClass('form-control');
            },
            "columnDefs": [
                { "targets": [4,5,6], "className": "text-right" }
            ],
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
        
        $('#btnExportFluxo').click(function() {
            tabelaFluxo.button('.buttons-excel').trigger();
        });
        
        // DataTables para a segunda tabela
        var tabelaMediaM2 = $('#tabelaMediaM2').DataTable({
            "pageLength": 50,
            "lengthMenu": [ [25, 50, 100, -1], [25, 50, 100, "Todos"] ],
            "order": [[0, "asc"], [1, "desc"], [2, "desc"]],
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
                $('#tabelaMediaM2_filter input').addClass('form-control').attr('placeholder', 'Pesquisar...');
                $('#tabelaMediaM2_length select').addClass('form-control');
            },
            "columnDefs": [
                { "targets": [3], "className": "text-right" }
            ],
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Media_VM2_por_Localidade_Ano_Mes',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ]
        });

        $('#btnExportMedia').click(function() {
            tabelaMediaM2.button('.buttons-excel').trigger();
        });
        
        // Preparar dados para o gráfico
        function prepareChartData() {
            var chartData = {};
            var mesesAnos = new Set();
            
            // Converter dados da tabela PHP para JavaScript
            var mediaData = <?php echo json_encode($mediaVM2); ?>;
            
            mediaData.forEach(function(item) {
                var localidade = item.localidade;
                var ano = item.ano;
                var mes = item.mes;
                var media = parseFloat(item.media);
                
                var mesAno = mes + '/' + ano;
                mesesAnos.add(mesAno);
                
                if (!chartData[localidade]) {
                    chartData[localidade] = [];
                }
                
                // Encontrar ou adicionar o valor para este mês/ano
                var foundIndex = chartData[localidade].findIndex(function(d) {
                    return d.mesAno === mesAno;
                });
                
                if (foundIndex === -1) {
                    chartData[localidade].push({
                        mesAno: mesAno,
                        valor: media
                    });
                }
            });
            
            // Ordenar meses/anos
            var sortedMesesAnos = Array.from(mesesAnos).sort(function(a, b) {
                var partsA = a.split('/');
                var partsB = b.split('/');
                var dateA = new Date(partsA[1], partsA[0] - 1);
                var dateB = new Date(partsB[1], partsB[0] - 1);
                return dateA - dateB;
            });
            
            // Garantir que todas as localidades tenham dados para todos os meses
            for (var localidade in chartData) {
                var dadosLocalidade = chartData[localidade];
                var novosDados = [];
                
                sortedMesesAnos.forEach(function(mesAno) {
                    var encontrado = dadosLocalidade.find(function(d) {
                        return d.mesAno === mesAno;
                    });
                    
                    novosDados.push({
                        mesAno: mesAno,
                        valor: encontrado ? encontrado.valor : null
                    });
                });
                
                chartData[localidade] = novosDados;
            }
            
            return {
                chartData: chartData,
                categories: sortedMesesAnos
            };
        }
        
        // Inicializar gráfico
        function initChart() {
            var data = prepareChartData();
            var chartDom = document.getElementById('graficoMediaM2');
            var myChart = echarts.init(chartDom);
            
            var series = [];
            var legendData = [];
            
            // Criar séries para cada localidade
            for (var localidade in data.chartData) {
                legendData.push(localidade);
                
                var serieData = data.chartData[localidade].map(function(item) {
                    return item.valor;
                });
                
                series.push({
                    name: localidade,
                    type: 'bar',
                    data: serieData,
                    emphasis: {
                        focus: 'series'
                    },
                    animationDelay: function(idx) {
                        return idx * 100;
                    }
                });
            }
            
            var option = {
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow'
                    },
                    formatter: function(params) {
                        var result = params[0].axisValue + '<br/>';
                        params.forEach(function(item) {
                            if (item.value !== null) {
                                result += item.marker + ' ' + item.seriesName + ': R$ ' + 
                                          item.value.toLocaleString('pt-BR', {
                                              minimumFractionDigits: 2,
                                              maximumFractionDigits: 2
                                          }) + '<br/>';
                            }
                        });
                        return result;
                    }
                },
                legend: {
                    data: legendData,
                    selected: JSON.parse(localStorage.getItem('selectedSeries')) || {},
                    right: 10,
                    top: 20
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '10%',
                    containLabel: true
                },
                xAxis: {
                    type: 'category',
                    data: data.categories,
                    axisLabel: {
                        rotate: 45,
                        interval: 0
                    }
                },
                yAxis: {
                    type: 'value',
                    axisLabel: {
                        formatter: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        }
                    }
                },
                series: series,
                animationEasing: 'elasticOut',
                animationDelayUpdate: function(idx) {
                    return idx * 5;
                }
            };
            
            myChart.setOption(option);
            
            // Botão para mostrar/ocultar todas as séries
            $('#toggleSeriesBtn').click(function() {
                var legend = myChart.getOption().legend[0];
                var newSelected = {};
                var allHidden = Object.values(legend.selected).every(function(val) {
                    return val === false;
                });
                
                if (allHidden) {
                    for (var name in legend.selected) {
                        newSelected[name] = true;
                    }
                } else {
                    for (var name in legend.selected) {
                        newSelected[name] = false;
                    }
                }
                
                myChart.setOption({
                    legend: {
                        selected: newSelected
                    }
                });
                
                localStorage.setItem('selectedSeries', JSON.stringify(newSelected));
            });
            
            myChart.on('legendselectchanged', function(params) {
                localStorage.setItem('selectedSeries', JSON.stringify(params.selected));
            });
            
            window.addEventListener('resize', function() {
                myChart.resize();
            });
        }
        
        // Inicializar gráfico
        setTimeout(initChart, 100);
    });
  </script>
</body>
</html>