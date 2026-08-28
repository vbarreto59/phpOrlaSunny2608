<?php
// historico_navegacao.php
session_start();

// Configurar headers para UTF-8
header('Content-Type: text/html; charset=utf-8');

// =========================================================
// INCLUIR CONEXÃO COM BANCO DE DADOS
// =========================================================
require_once 'conn.php';

// =========================================================
// FUNÇÕES AUXILIARES
// =========================================================

// Função para extrair apenas o nome do arquivo de uma URL completa.
// Ex: "/ImobVendas/mainpage_v4d-carrossel.asp?id=123" -> "mainpage_v4d-carrossel.asp"
function cleanUrlPath($fullPath) {
    // Converte para string
    $fullPath = (string)$fullPath;
    
    // Encontra a posição da última barra
    $lastSlashPos = strrpos($fullPath, '/');
    
    if ($lastSlashPos !== false) {
        // Pega a parte da string após a última barra
        $cleanedPath = substr($fullPath, $lastSlashPos + 1);
    } else {
        // Se não houver barra, a URL é o próprio nome do arquivo
        $cleanedPath = $fullPath;
    }
    
    // Verifica se há uma string de consulta (?)
    $queryPos = strpos($cleanedPath, '?');
    if ($queryPos !== false) {
        // Remove a string de consulta, pegando apenas a parte antes do ?
        $cleanedPath = substr($cleanedPath, 0, $queryPos);
    }
    
    return $cleanedPath;
}

// Função para determinar o sistema operacional pelo User-Agent
function detectSistemaOperacional($userAgentString) {
    $userAgentString = strtolower($userAgentString);
    
    if (strpos($userAgentString, 'windows') !== false) {
        return "Windows";
    } elseif (strpos($userAgentString, 'android') !== false) {
        return "Android";
    } elseif (strpos($userAgentString, 'mac os') !== false || strpos($userAgentString, 'macos') !== false) {
        return "macOS";
    } elseif (strpos($userAgentString, 'linux') !== false) {
        return "Linux";
    } elseif (strpos($userAgentString, 'iphone') !== false || strpos($userAgentString, 'ipad') !== false) {
        return "iOS";
    } else {
        return "Outro Sistema";
    }
}

// =========================================================
// PROCESSAMENTO DE MENSAGENS
// =========================================================

$mensagem = $_GET['msg'] ?? '';
$alertClass = '';
$displayMessage = '';

if (!empty($mensagem)) {
    $prefixo = substr($mensagem, 0, 3);
    switch ($prefixo) {
        case 'suc':
            $alertClass = 'alert-success';
            $displayMessage = substr($mensagem, 3);
            break;
        case 'err':
            $alertClass = 'alert-danger';
            $displayMessage = substr($mensagem, 3);
            break;
        default:
            $alertClass = 'alert-info';
            $displayMessage = $mensagem;
    }
}

// =========================================================
// CONSULTA AO BANCO DE DADOS
// =========================================================

$dados = [];
$erro = '';

try {
    if (!$conn) {
        throw new Exception("Não foi possível conectar ao banco de dados.");
    }
    
    // CORREÇÃO: Usar LIMIT em vez de TOP para MySQL/MariaDB
    $sql = "SELECT ID_Registro, UserID, Usuario, Caminho_Pagina, IP_Usuario, 
                   Data_Hora_Visita, User_Agent 
            FROM historico_navegacao 
            ORDER BY ID_Registro DESC
            LIMIT 3000"; // LIMIT em vez de TOP
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $erro = "Erro ao carregar histórico: " . $e->getMessage();
    error_log($erro);
} catch(Exception $e) {
    $erro = $e->getMessage();
    error_log($erro);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Navegação</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- DataTables CSS com tema Bootstrap 5 -->
    <link href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            padding-top: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1600px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
        }
        .header-section {
            background: linear-gradient(135deg, #0d6efd 0%, #198754 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        h1 {
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        .subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #f2f7ff;
        }
        .table-hover tbody tr:hover {
            background-color: #e8f4ff !important;
            transform: scale(1.002);
            transition: all 0.2s ease;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #fff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background-color: #e9ecef !important;
            border-color: #dee2e6 !important;
        }
        .dataTables_wrapper .dataTables_filter input {
            border-radius: .25rem;
            border: 2px solid #0d6efd;
            padding: .5rem 1rem;
            transition: all 0.3s ease;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }
        .badge-os {
            font-size: 0.75em;
            padding: 4px 8px;
            border-radius: 10px;
        }
        .badge-windows { background-color: #0078d4; color: white; }
        .badge-android { background-color: #3ddc84; color: #333; }
        .badge-macos { background-color: #999; color: white; }
        .badge-linux { background-color: #f56c42; color: white; }
        .badge-ios { background-color: #000; color: white; }
        .badge-outro { background-color: #6c757d; color: white; }
        .card-stat {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid #0d6efd;
        }
        .card-stat h5 {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .card-stat h3 {
            color: #0d6efd;
            font-weight: 700;
            margin: 0;
        }
        .btn-voltar {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-voltar:hover {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .filter-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        @media print {
            .no-print { display: none !important; }
            .container { box-shadow: none; }
            .table-hover tbody tr:hover { background-color: inherit !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-history me-2"></i>Histórico de Navegação</h1>
                    <p class="subtitle mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Registros de acesso ao sistema - Últimas 3000 entradas
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex justify-content-end gap-2">
                        <button onclick="window.print()" class="btn btn-light">
                            <i class="fas fa-print me-1"></i> Imprimir
                        </button>
                        <button onclick="exportarParaExcel()" class="btn btn-success">
                            <i class="fas fa-file-excel me-1"></i> Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($displayMessage)): ?>
        <div class="alert <?php echo $alertClass; ?> alert-dismissible fade show" role="alert">
            <i class="fas <?php echo $alertClass == 'alert-success' ? 'fa-check-circle' : ($alertClass == 'alert-danger' ? 'fa-exclamation-circle' : 'fa-info-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($displayMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row mb-4 no-print">
            <div class="col-md-3">
                <div class="card-stat">
                    <h5><i class="fas fa-database me-1"></i> Total de Registros</h5>
                    <h3><?php echo count($dados); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-stat">
                    <h5><i class="fas fa-users me-1"></i> Usuários Únicos</h5>
                    <h3>
                        <?php
                        $usuariosUnicos = [];
                        foreach ($dados as $registro) {
                            if (!empty($registro['Usuario'])) {
                                $usuariosUnicos[$registro['Usuario']] = true;
                            }
                        }
                        echo count($usuariosUnicos);
                        ?>
                    </h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-stat">
                    <h5><i class="fas fa-globe me-1"></i> IPs Diferentes</h5>
                    <h3>
                        <?php
                        $ipsUnicos = [];
                        foreach ($dados as $registro) {
                            if (!empty($registro['IP_Usuario'])) {
                                $ipsUnicos[$registro['IP_Usuario']] = true;
                            }
                        }
                        echo count($ipsUnicos);
                        ?>
                    </h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-stat">
                    <h5><i class="fas fa-file-alt me-1"></i> Páginas Únicas</h5>
                    <h3>
                        <?php
                        $paginasUnicas = [];
                        foreach ($dados as $registro) {
                            $pagina = cleanUrlPath($registro['Caminho_Pagina']);
                            if (!empty($pagina)) {
                                $paginasUnicas[$pagina] = true;
                            }
                        }
                        echo count($paginasUnicas);
                        ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="filter-card no-print">
            <div class="row">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-filter"></i>
                        </span>
                        <input type="text" id="searchBox" class="form-control" placeholder="Pesquise em qualquer coluna...">
                        <button class="btn btn-outline-secondary" type="button" onclick="limparFiltro()">
                            <i class="fas fa-times"></i> Limpar
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select id="filterOS" class="form-select" onchange="filtrarPorOS()">
                        <option value="">Todos os Sistemas</option>
                        <option value="Windows">Windows</option>
                        <option value="Android">Android</option>
                        <option value="macOS">macOS</option>
                        <option value="Linux">Linux</option>
                        <option value="iOS">iOS</option>
                        <option value="Outro Sistema">Outro</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="tabelaHistorico" class="table table-striped table-bordered table-hover" style="width:100%">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>UserID</th>
                        <th>Usuário</th>
                        <th>Página Visitada</th>
                        <th>IP</th>
                        <th>Data/Hora</th>
                        <th>Sistema Operacional</th>
                        <th class="no-print">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($erro)): ?>
                    <tr>
                        <td colspan="8" class="text-danger text-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($erro); ?>
                        </td>
                    </tr>
                    <?php elseif (empty($dados)): ?>
                    <tr>
                        <td colspan="8" class="text-muted text-center">
                            <i class="fas fa-inbox me-2"></i>
                            Nenhum registro de navegação encontrado.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($dados as $registro): 
                            $sistemaOperacional = detectSistemaOperacional($registro['User_Agent'] ?? '');
                            $cleanedPagePath = cleanUrlPath($registro['Caminho_Pagina'] ?? '');
                            $badgeClass = 'badge-' . strtolower(str_replace(' ', '-', $sistemaOperacional));
                            $dataHora = date('d/m/Y H:i:s', strtotime($registro['Data_Hora_Visita']));
                        ?>
                        <tr>
                            <td><strong>#<?php echo $registro['ID_Registro']; ?></strong></td>
                            <td><?php echo htmlspecialchars($registro['UserID'] ?? ''); ?></td>
                            <td>
                                <?php if (!empty($registro['Usuario'])): ?>
                                <i class="fas fa-user me-1 text-muted"></i>
                                <?php echo htmlspecialchars($registro['Usuario']); ?>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fas fa-file me-1 text-muted"></i>
                                <?php echo htmlspecialchars($cleanedPagePath); ?>
                            </td>
                            <td>
                                <code><?php echo htmlspecialchars($registro['IP_Usuario'] ?? ''); ?></code>
                            </td>
                            <td>
                                <small><?php echo $dataHora; ?></small>
                            </td>
                            <td>
                                <span class="badge <?php echo $badgeClass; ?> badge-os">
                                    <i class="fas fa-desktop me-1"></i>
                                    <?php echo $sistemaOperacional; ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <button class="btn btn-sm btn-outline-info" 
                                        onclick="verDetalhes(<?php echo $registro['ID_Registro']; ?>)"
                                        title="Ver detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-center no-print">
            <a href="javascript:history.back()" class="btn btn-voltar">
                <i class="fas fa-arrow-left me-2"></i> Voltar
            </a>
        </div>

        <footer class="text-center text-muted mt-5">
            <p class="mb-0">
                <i class="fas fa-sun me-1"></i> Sunny System &copy; <?php echo date('Y'); ?> 
                | Relatório gerado em: <?php echo date('d/m/Y H:i:s'); ?>
            </p>
        </footer>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.min.js"></script>
    <!-- DataTables Bootstrap 5 integration -->
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- SheetJS para exportação Excel -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>

    <script>
        let dataTable;
        
        $(document).ready(function() {
            dataTable = $('#tabelaHistorico').DataTable({
                "order": [[ 0, "desc" ]],
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/2.0.7/i18n/pt-BR.json"
                },
                "pageLength": 100,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                "responsive": true,
                "columnDefs": [
                    { "orderable": true, "targets": [0, 1, 2, 3, 4, 5, 6] },
                    { "orderable": false, "targets": [7] },
                    { "className": "dt-center", "targets": [0, 1, 6, 7] },
                    { "className": "dt-left", "targets": [2, 3, 4, 5] }
                ],
                "initComplete": function() {
                    // Aplicar filtro de busca personalizado
                    $('#searchBox').on('keyup', function() {
                        dataTable.search(this.value).draw();
                    });
                    
                    // Adicionar tooltips
                    $('[title]').tooltip();
                }
            });
            
            // Configurar filtro por Sistema Operacional
            $('#filterOS').on('change', function() {
                filtrarPorOS();
            });
        });
        
        function filtrarPorOS() {
            const os = $('#filterOS').val();
            if (os) {
                dataTable.column(6).search('^' + os + '$', true, false).draw();
            } else {
                dataTable.column(6).search('').draw();
            }
        }
        
        function limparFiltro() {
            $('#searchBox').val('');
            $('#filterOS').val('');
            dataTable.search('').columns().search('').draw();
        }
        
        function verDetalhes(id) {
            // Aqui você pode implementar um modal com detalhes do registro
            alert('Detalhes do registro #' + id + ' serão exibidos em um modal.\nImplemente a lógica de busca por ID.');
        }
        
        function exportarParaExcel() {
            const data = [];
            
            // Cabeçalhos
            data.push(['ID', 'UserID', 'Usuário', 'Página Visitada', 'IP', 'Data/Hora', 'Sistema Operacional']);
            
            // Dados
            dataTable.rows({ search: 'applied' }).every(function() {
                const rowData = this.data();
                data.push([
                    rowData[0].replace('<strong>#', '').replace('</strong>', ''),
                    rowData[1],
                    $(rowData[2]).text().trim(),
                    $(rowData[3]).text().trim(),
                    $(rowData[4]).text().trim(),
                    $(rowData[5]).text().trim(),
                    $(rowData[6]).text().trim()
                ]);
            });
            
            // Criar workbook
            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Histórico");
            
            // Gerar nome do arquivo
            const fileName = `historico_navegacao_${new Date().toISOString().slice(0,10)}.xlsx`;
            
            // Salvar arquivo
            XLSX.writeFile(wb, fileName);
        }
        
        // Função para atualizar estatísticas em tempo real
        function atualizarEstatisticas() {
            const totalRegistros = dataTable.rows({ search: 'applied' }).count();
            const usuariosUnicos = new Set();
            const ipsUnicos = new Set();
            const paginasUnicas = new Set();
            
            dataTable.rows({ search: 'applied' }).every(function() {
                const rowData = this.data();
                const usuario = $(rowData[2]).text().trim();
                const ip = $(rowData[4]).text().trim();
                const pagina = $(rowData[3]).text().trim();
                
                if (usuario) usuariosUnicos.add(usuario);
                if (ip) ipsUnicos.add(ip);
                if (pagina) paginasUnicas.add(pagina);
            });
            
            // Atualizar cards (implemente se necessário)
            console.log('Estatísticas atualizadas:', {
                totalRegistros,
                usuariosUnicos: usuariosUnicos.size,
                ipsUnicos: ipsUnicos.size,
                paginasUnicas: paginasUnicas.size
            });
        }
        
        // Atualizar estatísticas quando a tabela for desenhada
        $(document).on('draw.dt', function() {
            atualizarEstatisticas();
        });
    </script>
</body>
</html>