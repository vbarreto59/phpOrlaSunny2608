<?php
// Iniciar sessão
session_start();

// Incluir arquivos necessários
require_once 'conn.php';

// Verifica se o usuário está logado
if (empty($_SESSION['Usuario'])) {
    header("Location: login_p1.php"); // Atualizado para PDO
    exit();
}

// Envio de email de login
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    $assunto = "SUN-M2-M-" . strtoupper($_SESSION['Usuario']) . " - " . 
               $_SERVER['REMOTE_ADDR'] . " - " . date('Y-m-d') . " - " . date('H:i:s');
    
    $headers = "From: sendmail@gabnetweb.com.br\r\n";
    $headers .= "Reply-To: sendmail@gabnetweb.com.br\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    @mail("sendmail@gabnetweb.com.br, valterpb@hotmail.com", $assunto, "", $headers);
}

// Função para executar consultas PDO
function executarConsulta($sql, $params = []) {
    global $conn;
    
    try {
        $stmt = $conn->prepare($sql);
        
        // Vincular parâmetros se houver
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } elseif (is_float($value)) {
                    $stmt->bindValue($key, $value);
                } elseif (is_bool($value)) {
                    $stmt->bindValue($key, $value, PDO::PARAM_BOOL);
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }
        }
        
        $stmt->execute();
        return $stmt;
        
    } catch (PDOException $e) {
        error_log("Erro na consulta PDO: " . $e->getMessage());
        die("<div class='alert alert-danger'>Erro na consulta: " . htmlspecialchars($e->getMessage()) . "</div>");
    }
}

// 1. Obter dados detalhados para a primeira tabela
$sqlDetalhes = "
    SELECT F.ID, E.NomeEmpreendimento, E.Localidade, F.UnidadeM2, 
           F.ValorUnidade, F.DataCriacao, 
           (F.ValorUnidade/F.UnidadeM2) AS VM2 
    FROM fluxocalculadora F 
    INNER JOIN empreendimento E ON F.Empreendimento_Id = E.Empreend_ID 
    WHERE UnidadeM2 > 15 
    AND F.ID IN (
        SELECT MAX(ID) 
        FROM fluxocalculadora 
        GROUP BY Empreendimento_Id, UnidadeM2, ValorUnidade
    ) 
    ORDER BY F.ID DESC
";

try {
    $stmtDetalhes = $conn->prepare($sqlDetalhes);
    $stmtDetalhes->execute();
    $dadosDetalhados = $stmtDetalhes->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>Erro na consulta de detalhes: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// 2. Preparar dados para o gráfico e médias
$sqlGrafico = "
    SELECT E.Localidade, 
           YEAR(F.DataCriacao) AS Ano, 
           MONTH(F.DataCriacao) AS Mes, 
           F.ValorUnidade/F.UnidadeM2 AS VM2 
    FROM fluxocalculadora F 
    INNER JOIN empreendimento E ON F.Empreendimento_Id = E.Empreend_ID 
    WHERE F.ID >= :id_param AND F.UnidadeM2 > 0 
    ORDER BY E.Localidade, YEAR(F.DataCriacao), MONTH(F.DataCriacao)
";

try {
    $stmtGrafico = $conn->prepare($sqlGrafico);
    $stmtGrafico->bindValue(':id_param', 111, PDO::PARAM_INT);
    $stmtGrafico->execute();
    $dadosGrafico = $stmtGrafico->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>Erro na consulta do gráfico: " . htmlspecialchars($e->getMessage()) . "</div>");
}

// Dicionários para armazenar dados
$dictGrafico = [];
$dictMedias = [];

foreach ($dadosGrafico as $row) {
    if (!empty($row['VM2']) && $row['VM2'] >= 5000) {
        $localidade = strtoupper($row['Localidade']);
        $ano = $row['Ano'];
        $mes = $row['Mes'];
        $chave = $localidade . "|" . $ano . "|" . $mes;
        $vm2 = $row['VM2'];
        
        // Preparar dados para o gráfico
        if (!isset($dictGrafico[$localidade])) {
            $dictGrafico[$localidade] = [];
        }
        
        $mesAno = $mes . "/" . $ano;
        if (!isset($dictGrafico[$localidade][$mesAno])) {
            $dictGrafico[$localidade][$mesAno] = ['soma' => 0, 'contagem' => 0];
        }
        
        $dictGrafico[$localidade][$mesAno]['soma'] += $vm2;
        $dictGrafico[$localidade][$mesAno]['contagem']++;
        
        // Preparar dados para a tabela de médias
        if (!isset($dictMedias[$chave])) {
            $dictMedias[$chave] = [
                'soma' => 0,
                'contagem' => 0,
                'localidade' => $localidade,
                'ano' => $ano,
                'mes' => $mes
            ];
        }
        
        $dictMedias[$chave]['soma'] += $vm2;
        $dictMedias[$chave]['contagem']++;
    }
}

// Criar array para as médias
$mediasArray = [];
foreach ($dictMedias as $chave => $data) {
    $media = $data['soma'] / $data['contagem'];
    
    $mediasArray[] = [
        'Localidade' => $data['localidade'],
        'Ano' => $data['ano'],
        'Mes' => $data['mes'],
        'MediaVM2' => round($media, 2),
        'TotalRegistros' => $data['contagem'],
        'SomaTotal' => round($data['soma'], 2)
    ];
}

// Ordenar médias
usort($mediasArray, function($a, $b) {
    if ($a['Localidade'] === $b['Localidade']) {
        if ($a['Ano'] === $b['Ano']) {
            return $b['Mes'] <=> $a['Mes'];
        }
        return $b['Ano'] <=> $a['Ano'];
    }
    return strcmp($a['Localidade'], $b['Localidade']);
});

// Preparar dados para o gráfico (JSON)
$legendas = array_keys($dictGrafico);

// Extrair todos os meses/anos únicos
$mesesAnos = [];
foreach ($dictGrafico as $localidade => $dados) {
    foreach (array_keys($dados) as $mesAno) {
        if (!in_array($mesAno, $mesesAnos)) {
            $mesesAnos[] = $mesAno;
        }
    }
}

// Ordenar meses/anos
usort($mesesAnos, function($a, $b) {
    list($mesA, $anoA) = explode('/', $a);
    list($mesB, $anoB) = explode('/', $b);
    return ($anoA * 100 + $mesA) <=> ($anoB * 100 + $mesB);
});

// Preparar séries
$series = [];
foreach ($dictGrafico as $localidade => $dados) {
    $serie = [
        'name' => $localidade,
        'type' => 'bar',
        'data' => []
    ];
    
    foreach ($mesesAnos as $mesAno) {
        if (isset($dados[$mesAno])) {
            $media = $dados[$mesAno]['soma'] / $dados[$mesAno]['contagem'];
            $serie['data'][] = round($media, 2);
        } else {
            $serie['data'][] = null;
        }
    }
    
    $series[] = $serie;
}

// Criar objeto JSON
$jsonGrafico = json_encode([
    'legendas' => $legendas,
    'categorias' => $mesesAnos,
    'series' => $series
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// Função para formatar números
function formatarNumero($numero, $decimais = 2) {
    return number_format($numero, $decimais, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Relatório Completo de Valores M²</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.4/css/buttons.bootstrap4.min.css">
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

    <style>
        body { background-color: #f8f9fa; font-size: 0.9rem; }
        .table-container { background-color: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 20px; margin: 20px 0; }
        .valor { text-align: right; white-space: nowrap; }
        .header-actions { margin-bottom: 20px; }
        #graficoMediaM2 { height: 400px; margin: 20px 0; }
        
        @media (max-width: 768px) {
            #tabelaDetalhes td:nth-child(1), #tabelaDetalhes th:nth-child(1),
            #tabelaDetalhes td:nth-child(3), #tabelaDetalhes th:nth-child(3),
            #tabelaDetalhes td:nth-child(5), #tabelaDetalhes th:nth-child(5),
            #tabelaDetalhes td:nth-child(6), #tabelaDetalhes th:nth-child(6) {
                display: none !important;
            }
            #tabelaMedias td:nth-child(2), #tabelaMedias th:nth-child(2),
            #tabelaMedias td:nth-child(3), #tabelaMedias th:nth-child(3) {
                display: none !important;
            }
            .dataTables_wrapper .row {
                flex-direction: column;
            }
            .dataTables_wrapper .dataTables_filter {
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-actions">
        <h4><i class="fas fa-table mr-2"></i>M2 Datas</h4>
    </div>
    
    <div class="table-container">
        <div class="table-responsive">
            <table id="tabelaDetalhes" class="table table-striped table-bordered w-100">
                <thead class="thead-light">
                    <tr>
                        <th>F.ID</th>
                        <th>Data</th>
                        <th>Empreendimento</th>
                        <th>Localidade</th>
                        <th>Área (m²)</th>
                        <th>Valor (R$)</th>
                        <th>Valor M² (R$)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($dadosDetalhados as $row): ?>
                    <?php 
                    if ($row['UnidadeM2'] != 0) {
                        $vm2 = $row['ValorUnidade'] / $row['UnidadeM2'];
                    } else {
                        $vm2 = 0;
                    }
                    
                    if ($vm2 >= 5000): 
                        $dataCriacao = new DateTime($row['DataCriacao']);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['ID']) ?></td>
                        <td><?= $dataCriacao->format('Y-m-d') ?></td>
                        <td><?= htmlspecialchars(strtoupper($row['NomeEmpreendimento'])) ?></td>
                        <td><?= htmlspecialchars(strtoupper($row['Localidade'])) ?></td>
                        <td class="valor"><?= formatarNumero($row['UnidadeM2'], 2) ?></td>
                        <td class="valor">R$ <?= formatarNumero($row['ValorUnidade'], 2) ?></td>
                        <td class="valor">R$ <?= formatarNumero($vm2, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="header-actions mt-5">
        <h4><i class="fas fa-chart-bar mr-2"></i>Médias por Localidade, Ano e Mês</h4>
    </div>
    
    <div class="table-container">
        <div class="table-responsive">
            <table id="tabelaMedias" class="table table-striped table-bordered w-100">
                <thead class="thead-light">
                    <tr>
                        <th>Localidade</th>
                        <th>Ano</th>
                        <th>Mês</th>
                        <th>Média M² (R$)</th>
                        <th>Registros</th>
                        <th>Soma Total (R$)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mediasArray as $media): ?>
                    <tr>
                        <td><?= htmlspecialchars($media['Localidade']) ?></td>
                        <td><?= htmlspecialchars($media['Ano']) ?></td>
                        <td><?= htmlspecialchars($media['Mes']) ?></td>
                        <td class="valor">R$ <?= formatarNumero($media['MediaVM2'], 2) ?></td>
                        <td class="valor"><?= htmlspecialchars($media['TotalRegistros']) ?></td>
                        <td class="valor">R$ <?= formatarNumero($media['SomaTotal'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="header-actions mt-5">
        <h4><i class="fas fa-chart-line mr-2"></i>Evolução das Médias</h4>
        <button id="toggleChart" class="btn btn-sm btn-primary">Alternar Visualização</button>
    </div>
    
    <div id="graficoMediaM2"></div>
</div>

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
    $('#tabelaDetalhes').DataTable({
        dom: 'Bfrtip',
        responsive: true,
        buttons: [],
        order: [[0, 'desc']],
        pageLength: 100,
        columnDefs: [
            { responsivePriority: 1, targets: 1 },
            { responsivePriority: 2, targets: 3 },
            { responsivePriority: 3, targets: 6 }
        ],
        language: {
            "sEmptyTable": "Nenhum registro encontrado",
            "sInfo": "Mostrando _START_ até _END_ de _TOTAL_ registros",
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
            }
        }
    });
    
    $('#tabelaMedias').DataTable({
        dom: 'Bfrtip',
        responsive: true,
        buttons: [],
        pageLength: 100,
        order: [[1, 'desc'],[2, 'desc']],
        columnDefs: [
            { responsivePriority: 1, targets: 0 },
            { responsivePriority: 2, targets: 3 },
            { responsivePriority: 3, targets: 4 }
        ],
        language: {
            "sEmptyTable": "Nenhum registro encontrado",
            "sInfo": "Mostrando _START_ até _END_ de _TOTAL_ registros",
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
            }
        }
    });

    var chartDom = document.getElementById('graficoMediaM2');
    var myChart = echarts.init(chartDom);
    
    var dadosGrafico = <?= $jsonGrafico ?>;
    
    var option = {
        tooltip: {
            trigger: 'axis',
            formatter: function(params) {
                var result = params[0].axisValue + '<br/>';
                params.forEach(function(item) {
                    if (item.value !== null) {
                        result += item.marker + ' ' + item.seriesName + ': R$ ' +  
                        item.value.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + '<br/>';
                    }
                });
                return result;
            }
        },
        legend: {    
            data: dadosGrafico.legendas,    
            right: 'auto',
            top: 'auto',
            orient: 'vertical',
            selected: JSON.parse(localStorage.getItem('selectedSeries')) || {}
        },
        grid: {    
            left: '3%',    
            right: '15%',
            bottom: '3%',    
            containLabel: true    
        },
        xAxis: {    
            type: 'category',    
            data: dadosGrafico.categorias,
            axisLabel: {    
                rotate: 45,
                interval: 0
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: 'R$ {value}'
            }
        },
        series: dadosGrafico.series,
        animationEasing: 'elasticOut'
    };
    
    myChart.setOption(option);
    
    $('#toggleChart').click(function() {
        var currentOption = myChart.getOption();
        var newSeriesType = currentOption.series[0].type === 'bar' ? 'line' : 'bar';
        
        for(var i=0; i<currentOption.series.length; i++) {
            currentOption.series[i].type = newSeriesType;
        }
        
        myChart.setOption(currentOption);
    });
    
    myChart.on('legendselectchanged', function(params) {
        localStorage.setItem('selectedSeries', JSON.stringify(params.selected));
    });
    
    window.addEventListener('resize', function() {
        myChart.resize();
    });
});
</script>

</body>
</html>
<?php
