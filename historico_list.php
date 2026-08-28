<?php
// historico_alteracoes.php
session_start();
require_once 'conn.php';

// Verificar se o usuário está logado
if (empty($_SESSION['Usuario'])) {
    header('Location: login_p1.php');
    exit();
}

// Configurar headers para UTF-8
header('Content-Type: text/html; charset=utf-8');

// =========================================================
// FUNÇÕES AUXILIARES
// =========================================================

// Função para retornar o nome do dia da semana em português
function getDayOfWeekName($dateValue) {
    $dayOfWeekNum = date('N', strtotime($dateValue)); // 1=Segunda, 2=Terça, ..., 7=Domingo
    $days = [
        1 => "Segunda-feira",
        2 => "Terça-feira", 
        3 => "Quarta-feira",
        4 => "Quinta-feira",
        5 => "Sexta-feira",
        6 => "Sábado",
        7 => "Domingo"
    ];
    return $days[$dayOfWeekNum] ?? "";
}

// =========================================================
// CONEXÃO E CONSULTAS
// =========================================================

try {
    // Atualizar os campos (mantido como no original)
    $sqlAtualiza = "UPDATE empreendimento 
                   INNER JOIN histatulog ON (empreendimento.Empresa_Id = histatulog.IdEmpresa) 
                   AND (empreendimento.Empreend_ID = histatulog.IdEmpreend) 
                   SET histatulog.Empresa = empreendimento.NomeEmpreendimento, 
                       histatulog.Empreendimento = empreendimento.NomeEmpresa";
    $conn->exec($sqlAtualiza);

    // Conta o total de acessos por dia, excluindo usuários vazios
    $sqlAcessosPorData = "SELECT DATE(DataHora) AS DataAcesso, COUNT(*) AS TotalAcessosDiarios 
                         FROM histatulog 
                         WHERE Usuario IS NOT NULL AND Usuario <> '' 
                         GROUP BY DATE(DataHora) 
                         ORDER BY DATE(DataHora) DESC";
    $stmtAcessosPorData = $conn->prepare($sqlAcessosPorData);
    $stmtAcessosPorData->execute();
    $acessosPorData = $stmtAcessosPorData->fetchAll(PDO::FETCH_ASSOC);

    // Busca todas as páginas acessadas
    $sqlPaginas = "SELECT Historico FROM histatulog 
                   WHERE Historico LIKE 'Acessou a página: %' 
                   AND Usuario IS NOT NULL AND Usuario <> ''";
    $stmtPaginas = $conn->prepare($sqlPaginas);
    $stmtPaginas->execute();
    $paginas = $stmtPaginas->fetchAll(PDO::FETCH_ASSOC);

    // Processa as páginas mais acessadas
    $paginasContagem = [];
    foreach ($paginas as $pagina) {
        // Extrai a URL completa do campo Historico
        $pageUrl = str_replace('Acessou a página: ', '', $pagina['Historico']);
        
        // Remove a string de query parameters (tudo após '?')
        $pos = strpos($pageUrl, '?');
        if ($pos !== false) {
            $pageUrl = substr($pageUrl, 0, $pos);
        }
        
        // Extrai o nome do arquivo da URL (tudo após a última '/')
        $pageName = basename($pageUrl);

        // Garante que o nome da página não esteja vazio após a extração
        if (!empty($pageName)) {
            if (isset($paginasContagem[$pageName])) {
                $paginasContagem[$pageName]++;
            } else {
                $paginasContagem[$pageName] = 1;
            }
        }
    }

    // Ordena as páginas por contagem decrescente
    arsort($paginasContagem);

    // Buscar os dados para a tabela de contagem de acessos por usuário
    $sqlContagemAcessos = "SELECT Usuario, COUNT(*) AS TotalAcessos 
                          FROM histatulog 
                          WHERE Usuario IS NOT NULL AND Usuario <> '' 
                          GROUP BY Usuario 
                          ORDER BY TotalAcessos DESC";
    $stmtContagemAcessos = $conn->prepare($sqlContagemAcessos);
    $stmtContagemAcessos->execute();
    $contagemAcessos = $stmtContagemAcessos->fetchAll(PDO::FETCH_ASSOC);

    // Buscar os dados para a tabela de contagem de "sucesso"
    $sqlContagemSucesso = "SELECT Usuario, Historico, COUNT(IdHistorico) AS ContarDeIdHistorico 
                          FROM histatulog 
                          WHERE Usuario IS NOT NULL AND Usuario <> '' 
                          AND Historico LIKE '%sucesso%' 
                          GROUP BY Usuario, Historico";
    $stmtContagemSucesso = $conn->prepare($sqlContagemSucesso);
    $stmtContagemSucesso->execute();
    $contagemSucesso = $stmtContagemSucesso->fetchAll(PDO::FETCH_ASSOC);

    // Buscar os dados do histórico completo
    $sqlHistorico = "SELECT h.idHistorico, h.Historico, h.IdEmpresa, h.Empresa, 
                     h.IdEmpreend, h.Empreendimento, h.IdUser, h.Usuario, h.DataHora 
                     FROM histatulog h 
                     WHERE h.Usuario IS NOT NULL AND h.Usuario <> '' 
                     ORDER BY h.idHistorico DESC";
    $stmtHistorico = $conn->prepare($sqlHistorico);
    $stmtHistorico->execute();
    $historico = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Alterações</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .table-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border-top: 4px solid #007bff;
        }
        h2 {
            margin-bottom: 20px;
            color: #343a40;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
        }
        .btn-voltar {
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-card h5 {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .stat-card h3 {
            margin: 5px 0 0 0;
            font-weight: bold;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            margin-bottom: 20px;
            border-radius: 8px;
            padding: 15px;
            color: white;
        }
        .badge-count {
            background-color: #28a745;
            font-size: 0.8em;
            padding: 4px 8px;
            border-radius: 10px;
        }
        .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar-custom">
            <div class="container-fluid">
                <div class="d-flex justify-content-between w-100 align-items-center">
                    <div>
                        <h4 class="mb-0"><i class="fas fa-history me-2"></i>Histórico de Alterações</h4>
                        <small class="opacity-75">Dashboard de atividades do sistema</small>
                    </div>
                    <div>
                        <a href="javascript:history.back()" class="btn btn-light btn-sm me-2">
                            <i class="fas fa-arrow-left me-1"></i> Voltar
                        </a>
                        <a href="logout.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-sign-out-alt me-1"></i> Sair
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h5><i class="fas fa-calendar-alt me-1"></i> Dias com Registros</h5>
                    <h3><?php echo count($acessosPorData); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <h5><i class="fas fa-users me-1"></i> Usuários Ativos</h5>
                    <h3><?php echo count($contagemAcessos); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                    <h5><i class="fas fa-file-alt me-1"></i> Páginas Únicas</h5>
                    <h3><?php echo count($paginasContagem); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);">
                    <h5><i class="fas fa-check-circle me-1"></i> Eventos Sucesso</h5>
                    <h3><?php echo count($contagemSucesso); ?></h3>
                </div>
            </div>
        </div>

        <div class="table-container">
            <h2><i class="fas fa-calendar-day me-2"></i>Acessos por Data</h2>
            <table id="tabelaAcessosPorData" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Data</th>
                        <th>Dia da Semana</th>
                        <th>Total de Acessos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($acessosPorData as $acesso): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($acesso['DataAcesso'])); ?></td>
                        <td><?php echo getDayOfWeekName($acesso['DataAcesso']); ?></td>
                        <td>
                            <span class="badge bg-primary rounded-pill"><?php echo $acesso['TotalAcessosDiarios']; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="table-container">
                    <h2><i class="fas fa-chart-bar me-2"></i>Páginas Mais Acessadas</h2>
                    <table id="tabelaPaginasMaisAcessadas" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Página</th>
                                <th>Total de Acessos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginasContagem as $pagina => $contagem): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-file me-2 text-muted"></i>
                                    <?php echo htmlspecialchars($pagina); ?>
                                </td>
                                <td>
                                    <span class="badge bg-info rounded-pill"><?php echo $contagem; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="table-container">
                    <h2><i class="fas fa-user-chart me-2"></i>Contagem de Acessos por Usuário</h2>
                    <table id="tabelaContagemAcessos" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Usuário</th>
                                <th>Total de Acessos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contagemAcessos as $acesso): ?>
                            <tr>
                                <td>
                                    <i class="fas fa-user me-2 text-muted"></i>
                                    <?php echo htmlspecialchars($acesso['Usuario']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-success rounded-pill"><?php echo $acesso['TotalAcessos']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="table-container">
            <h2><i class="fas fa-check-double me-2"></i>Eventos com "Sucesso"</h2>
            <table id="tabelaSucesso" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Usuário</th>
                        <th>Histórico</th>
                        <th>Contagem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contagemSucesso as $sucesso): ?>
                    <tr>
                        <td>
                            <i class="fas fa-user-check me-2 text-muted"></i>
                            <?php echo htmlspecialchars($sucesso['Usuario']); ?>
                        </td>
                        <td>
                            <i class="fas fa-history me-2 text-muted"></i>
                            <?php echo htmlspecialchars($sucesso['Historico']); ?>
                        </td>
                        <td>
                            <span class="badge bg-warning rounded-pill"><?php echo $sucesso['ContarDeIdHistorico']; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0"><i class="fas fa-list-alt me-2"></i>Histórico de Alterações</h2>
                <div>
                    <small class="text-muted me-3">
                        Total de registros: <span class="badge bg-secondary"><?php echo count($historico); ?></span>
                    </small>
                    <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-print me-1"></i> Imprimir
                    </button>
                </div>
            </div>

            <table id="tabelaHistorico" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Empresa</th>
                        <th>Empreendimento</th>
                        <th>Histórico</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico as $registro): ?>
                    <tr>
                        <td><small class="text-muted">#<?php echo $registro['idHistorico']; ?></small></td>
                        <td>
                            <small>
                                <?php echo date('d/m/Y', strtotime($registro['DataHora'])); ?><br>
                                <?php echo date('H:i:s', strtotime($registro['DataHora'])); ?>
                            </small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($registro['Usuario']); ?></strong><br>
                            <small class="text-muted">ID: <?php echo $registro['IdUser']; ?></small>
                        </td>
                        <td>
                            <?php echo !empty($registro['Empresa']) ? htmlspecialchars($registro['Empresa']) : '<span class="text-muted">-</span>'; ?><br>
                            <small class="text-muted">ID: <?php echo $registro['IdEmpresa']; ?></small>
                        </td>
                        <td>
                            <?php echo !empty($registro['empreendimento']) ? htmlspecialchars($registro['empreendimento']) : '<span class="text-muted">-</span>'; ?><br>
                            <small class="text-muted">ID: <?php echo $registro['IdEmpreend']; ?></small>
                        </td>
                        <td>
                            <div class="text-break">
                                <?php echo htmlspecialchars($registro['Historico']); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <footer class="text-center text-muted mt-4 py-3">
            <div class="container">
                <p class="mb-0">
                    <i class="fas fa-sun me-1"></i> Sunny System &copy; <?php echo date('Y'); ?> 
                    | Relatório gerado em: <?php echo date('d/m/Y H:i:s'); ?>
                </p>
            </div>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
    $(document).ready(function() {
        // Configuração comum para todas as tabelas
        var dataTableConfig = {
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json"
            },
            "responsive": true,
            "dom": '<"top"<"d-flex justify-content-between"lf>>rt<"bottom"ip><"clear">',
            "pageLength": 10,
            "order": [],
            "initComplete": function() {
                // Adicionar classes aos controles
                $(this).closest('.dataTables_wrapper').find('.dataTables_filter input')
                    .addClass('form-control form-control-sm')
                    .attr('placeholder', 'Pesquisar...');
                $(this).closest('.dataTables_wrapper').find('.dataTables_length select')
                    .addClass('form-control form-control-sm');
            }
        };

        // Tabela de Acessos por Data
        $('#tabelaAcessosPorData').DataTable({
            ...dataTableConfig,
            "order": [[0, "desc"]],
            "columnDefs": [
                { "orderable": true, "targets": [0, 1, 2] }
            ]
        });

        // Tabela de Páginas Mais Acessadas
        $('#tabelaPaginasMaisAcessadas').DataTable({
            ...dataTableConfig,
            "order": [[1, "desc"]],
            "columnDefs": [
                { "orderable": true, "targets": [0, 1] }
            ]
        });

        // Tabela de Contagem de Acessos
        $('#tabelaContagemAcessos').DataTable({
            ...dataTableConfig,
            "order": [[1, "desc"]],
            "columnDefs": [
                { "orderable": true, "targets": [0, 1] }
            ]
        });

        // Tabela de Sucesso
        $('#tabelaSucesso').DataTable({
            ...dataTableConfig,
            "order": [[2, "desc"]],
            "columnDefs": [
                { "orderable": true, "targets": [0, 1, 2] }
            ]
        });

        // Tabela de Histórico
        $('#tabelaHistorico').DataTable({
            ...dataTableConfig,
            "order": [[0, "desc"]],
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            "columnDefs": [
                { "width": "5%", "targets": 0 },
                { "width": "10%", "targets": 1 },
                { "width": "15%", "targets": 2 },
                { "width": "15%", "targets": 3 },
                { "width": "15%", "targets": 4 },
                { "width": "40%", "targets": 5 }
            ]
        });

        // Adicionar tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();

        // Adicionar funcionalidade de expandir/colapsar seções
        $('h2').click(function() {
            $(this).next('table').closest('.table-container').toggleClass('collapsed');
        });

        // Configurar impressão
        window.print = function() {
            $('.table-container').addClass('print-mode');
            window.print();
            setTimeout(function() {
                $('.table-container').removeClass('print-mode');
            }, 1000);
        };
    });

    // Função para exportar dados (exemplo)
    function exportarParaExcel(tabelaId, nomeArquivo) {
        var table = document.getElementById(tabelaId);
        var html = table.outerHTML;
        var blob = new Blob([html], {type: 'application/vnd.ms-excel'});
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = nomeArquivo + '.xls';
        a.click();
        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>