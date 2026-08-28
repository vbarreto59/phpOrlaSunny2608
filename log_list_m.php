<?php
session_start();
require_once "conn.php";

// Verificação de login
if (!isset($_SESSION["Usuario"]) || $_SESSION["Usuario"] == "") {
    header("Location: login_p1.php"); // Atualizado para PDO
    exit();
}

// Envio de e-mail de login (Lógica de segurança)
$remote_addr = $_SERVER['REMOTE_ADDR'];
if ($remote_addr !== "127.0.0.1" && $remote_addr !== "::1") {
    $to = "sendmail@gabnetweb.com.br, valterpb@hotmail.com";
    $subject = "SUN-PAGES-P-" . strtoupper($_SESSION["Usuario"]) . " - $remote_addr - " . date("d/m/Y H:i:s");
    $headers = "From: sendmail@gabnetweb.com.br";
    
    @mail($to, $subject, "Acesso registrado em " . date("Y-m-d H:i:s"), $headers);
}

// Tratamento de mensagens via GET
$alertHtml = "";
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $prefix = substr($msg, 0, 3);
    $displayMessage = substr($msg, 3);
    
    $class = match($prefix) {
        'suc' => 'alert-success',
        'err' => 'alert-danger',
        default => 'alert-info'
    };
    
    $alertHtml = "<div class='alert $class alert-dismissible fade show' role='alert'>" 
               . htmlspecialchars($displayMessage) . 
               "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Acessos por Data e Arquivo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { padding-top: 20px; background-color: #f8f9fa; }
        .container { max-width: 1400px; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h1 { color: #0d6efd; font-weight: 600; margin-bottom: 30px; }
        .table-striped > tbody > tr:nth-of-type(odd) > * { background-color: #f2f7ff; }
        .badge-count { 
            background-color: #0d6efd; 
            color: white; 
            padding: 0.25em 0.5em; 
            border-radius: 10px; 
            font-size: 0.85em; 
        }
        .filter-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .export-buttons {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">
            <i class="fas fa-chart-line"></i> Relatório de Acessos por Data e Arquivo
        </h1>
        
        <!-- Adicionar Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        
        <?php echo $alertHtml; ?>
        
        <!-- Filtros -->
        <div class="filter-container">
            <div class="row">
                <div class="col-md-4">
                    <label for="filterData" class="form-label">Filtrar por Data:</label>
                    <input type="date" id="filterData" class="form-control" onchange="filterTable()">
                </div>
                <div class="col-md-4">
                    <label for="filterArquivo" class="form-label">Filtrar por Arquivo:</label>
                    <input type="text" id="filterArquivo" class="form-control" placeholder="Digite parte do nome do arquivo..." onkeyup="filterTable()">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estatísticas:</label>
                    <div class="d-flex gap-3">
                        <span class="badge bg-primary" id="totalRegistros">0 registros</span>
                        <span class="badge bg-success" id="totalArquivos">0 arquivos</span>
                        <span class="badge bg-info" id="totalAcessos">0 acessos</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botões de exportação -->
        <div class="export-buttons text-center">
            <button onclick="exportToExcel()" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Exportar para Excel
            </button>
            <button onclick="exportToCSV()" class="btn btn-warning ms-2">
                <i class="fas fa-file-csv"></i> Exportar para CSV
            </button>
            <button onclick="printTable()" class="btn btn-primary ms-2">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
        
        <div class="table-responsive">
            <table id="tabelaAcessos" class="table table-striped table-bordered table-hover" style="width:100%">
                <thead class="table-primary">
                    <tr>
                        <th>Data</th>
                        <th>Arquivo</th>
                        <th>Total de Acessos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        // Usar a conexão global $conn do conn.php
                        global $conn;
                        
                        // SQL Otimizada
                        $sql = "SELECT 
                                    DATE(Data_Hora_Visita) AS Data, 
                                    Arquivo, 
                                    COUNT(ID_Registro) AS Total 
                                FROM historico_navegacao 
                                WHERE Arquivo IS NOT NULL AND Arquivo <> ''
                                GROUP BY DATE(Data_Hora_Visita), Arquivo 
                                ORDER BY Data DESC, Total DESC";

                        $stmt = $conn->prepare($sql);
                        $stmt->execute();
                        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Variáveis para estatísticas
                        $totalRegistros = count($resultados);
                        $totalAcessos = 0;
                        $arquivosUnicos = [];
                        
                        if (count($resultados) > 0) {
                            foreach ($resultados as $row) {
                                $totalAcessos += $row['Total'];
                                $arquivosUnicos[$row['Arquivo']] = true;
                                
                                echo "<tr>";
                                echo "<td><i class='fas fa-calendar-day text-primary me-2'></i>" . htmlspecialchars($row['Data']) . "</td>";
                                echo "<td><i class='fas fa-file text-secondary me-2'></i>" . htmlspecialchars($row['Arquivo']) . "</td>";
                                echo "<td class='text-end'><span class='badge-count'>" . number_format($row['Total']) . "</span></td>";
                                echo "</tr>";
                            }
                            
                            // Atualizar estatísticas via JavaScript
                            echo "<script>
                                document.getElementById('totalRegistros').textContent = '$totalRegistros registros';
                                document.getElementById('totalArquivos').textContent = '" . count($arquivosUnicos) . " arquivos';
                                document.getElementById('totalAcessos').textContent = '" . number_format($totalAcessos) . " acessos';
                            </script>";
                        } else {
                            echo "<tr><td colspan='3' class='text-muted text-center py-4'>
                                <i class='fas fa-database fa-2x mb-3'></i><br>
                                Nenhum registro encontrado.
                            </td></tr>";
                        }
                        
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='3' class='text-danger text-center py-4'>
                            <i class='fas fa-exclamation-triangle fa-2x mb-3'></i><br>
                            Erro ao carregar dados: " . htmlspecialchars($e->getMessage()) . "
                        </td></tr>";
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr class="table-dark">
                        <th colspan="2" class="text-end">Total Geral:</th>
                        <th class="text-end" id="totalGeral">0</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="mt-4 text-center">
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <a href="index.php" class="btn btn-outline-primary ms-2">
                <i class="fas fa-home"></i> Página Inicial
            </a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            var table = $('#tabelaAcessos').DataTable({
                "order": [[0, "desc"], [2, "desc"]],
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/2.0.7/i18n/pt-BR.json",
                    "search": "Pesquisar:",
                    "lengthMenu": "Mostrar _MENU_ registros por página",
                    "zeroRecords": "Nenhum registro encontrado",
                    "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                    "infoEmpty": "Nenhum registro disponível",
                    "infoFiltered": "(filtrado de _MAX_ registros totais)",
                    "paginate": {
                        "first": "Primeira",
                        "last": "Última",
                        "next": "Próxima",
                        "previous": "Anterior"
                    }
                },
                "pageLength": 50,
                "responsive": true,
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                "initComplete": function() {
                    // Calcular total geral
                    var api = this.api();
                    var total = api.column(2, {search: 'applied'}).data().reduce(function(a, b) {
                        return parseInt(a) + parseInt(b);
                    }, 0);
                    $('#totalGeral').text(total.toLocaleString('pt-BR'));
                    
                    // Atualizar ao filtrar
                    api.on('draw', function() {
                        var total = api.column(2, {search: 'applied'}).data().reduce(function(a, b) {
                            return parseInt(a) + parseInt(b);
                        }, 0);
                        $('#totalGeral').text(total.toLocaleString('pt-BR'));
                        
                        // Atualizar contagens
                        var filteredRows = api.rows({search: 'applied'}).count();
                        $('#totalRegistros').text(filteredRows + ' registros');
                    });
                }
            });
            
            // Inicializar estatísticas
            updateStatistics();
        });
        
        function filterTable() {
            var table = $('#tabelaAcessos').DataTable();
            var dataFilter = $('#filterData').val();
            var arquivoFilter = $('#filterArquivo').val().toLowerCase();
            
            // Combinar filtros
            table.column(0).search(dataFilter).draw();
            table.column(1).search(arquivoFilter).draw();
            
            updateStatistics();
        }
        
        function updateStatistics() {
            var table = $('#tabelaAcessos').DataTable();
            var filteredRows = table.rows({search: 'applied'}).count();
            
            // Contar arquivos únicos
            var arquivos = new Set();
            table.rows({search: 'applied'}).data().each(function(value) {
                arquivos.add(value[1]);
            });
            
            // Calcular total de acessos
            var totalAcessos = table
                .column(2, {search: 'applied'})
                .data()
                .reduce(function(a, b) {
                    return parseInt(a) + parseInt(b);
                }, 0);
            
            // Atualizar badges
            $('#totalArquivos').text(arquivos.size + ' arquivos');
            $('#totalRegistros').text(filteredRows + ' registros');
            $('#totalAcessos').text(totalAcessos.toLocaleString('pt-BR') + ' acessos');
        }
        
        function exportToExcel() {
            var table = $('#tabelaAcessos').DataTable();
            var data = table.rows({search: 'applied'}).data().toArray();
            
            // Adicionar cabeçalhos
            var wsData = [
                ['Data', 'Arquivo', 'Total de Acessos']
            ];
            
            // Adicionar dados
            data.forEach(function(row) {
                wsData.push([row[0], row[1], row[2]]);
            });
            
            // Criar worksheet
            var ws = XLSX.utils.aoa_to_sheet(wsData);
            
            // Criar workbook
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Relatorio_Acessos");
            
            // Gerar e fazer download
            var fileName = 'relatorio_acessos_' + new Date().toISOString().slice(0, 10) + '.xlsx';
            XLSX.writeFile(wb, fileName);
        }
        
        function exportToCSV() {
            var table = $('#tabelaAcessos').DataTable();
            var data = table.rows({search: 'applied'}).data().toArray();
            
            // Criar conteúdo CSV
            var csvContent = "Data;Arquivo;Total de Acessos\n";
            
            data.forEach(function(row) {
                csvContent += row[0] + ";" + row[1] + ";" + row[2] + "\n";
            });
            
            // Criar blob e fazer download
            var blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
            var link = document.createElement("a");
            var url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "relatorio_acessos_" + new Date().toISOString().slice(0, 10) + ".csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function printTable() {
            var originalTable = $('#tabelaAcessos').clone();
            var printWindow = window.open('', '_blank');
            
            printWindow.document.write('<html><head><title>Relatório de Acessos</title>');
            printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">');
            printWindow.document.write('<style>body { padding: 20px; } @media print { .no-print { display: none; } }</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<h1 class="text-center">Relatório de Acessos por Data e Arquivo</h1>');
            printWindow.document.write('<div class="table-responsive">');
            printWindow.document.write(originalTable.prop('outerHTML'));
            printWindow.document.write('</div>');
            printWindow.document.write('<div class="text-center mt-4 no-print">');
            printWindow.document.write('<button onclick="window.print()" class="btn btn-primary me-2">Imprimir</button>');
            printWindow.document.write('<button onclick="window.close()" class="btn btn-secondary">Fechar</button>');
            printWindow.document.write('</div>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            printWindow.focus();
        }
        
        // Auto-close alerts
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    </script>
</body>
</html>
<?php
// Limpar recursos PDO
if (isset($stmt)) {
    $stmt = null;
}
// A conexão será fechada automaticamente quando o script terminar
?>