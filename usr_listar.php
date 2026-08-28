<?php
session_start();
// Inclui o arquivo de conexão
require_once 'conn.php';

// Consulta para obter todos os usuários com LEFT JOIN
$sql_users = "SELECT u.*, e.* FROM usuarios u LEFT JOIN sunnyempresas e ON u.IdEmp = e.IdEmp ORDER BY u.Usuario ASC";
$stmt_users = $conn->prepare($sql_users);
$stmt_users->execute();
$usuarios = $stmt_users->fetchAll();

// Função para obter grupos de um usuário
function obterGruposUsuario($userId, $conn) {
    $sql_grupos = "SELECT g.ID_Grupo, g.Nome_Grupo FROM grupo g 
                   INNER JOIN usuario_grupo ug ON g.ID_Grupo = ug.ID_Grupo 
                   WHERE ug.UserId = :userId ORDER BY g.Nome_Grupo";
    
    $stmt_grupos = $conn->prepare($sql_grupos);
    $stmt_grupos->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt_grupos->execute();
    return $stmt_grupos->fetchAll();
}

// Função para obter contagem por grupo
function obterContagemPorGrupo($conn) {
    $sql = "SELECT 
                g.Nome_Grupo,
                COUNT(DISTINCT ug.UserId) as total_usuarios,
                COUNT(DISTINCT CASE WHEN u.Ativo = 1 THEN ug.UserId END) as ativos,
                COUNT(DISTINCT CASE WHEN u.Ativo = 0 THEN ug.UserId END) as inativos
            FROM grupo g
            INNER JOIN usuario_grupo ug ON g.ID_Grupo = ug.ID_Grupo
            INNER JOIN usuarios u ON ug.UserId = u.UserId
            GROUP BY g.ID_Grupo, g.Nome_Grupo
            ORDER BY g.Nome_Grupo";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Calcular estatísticas
$total_usuarios = count($usuarios);
$usuarios_ativos = 0;
$usuarios_inativos = 0;

foreach ($usuarios as $usuario) {
    if ($usuario['Ativo']) {
        $usuarios_ativos++;
    } else {
        $usuarios_inativos++;
    }
}

// Obter contagem por grupo
$contagem_grupos = obterContagemPorGrupo($conn);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Sunny - Lista de Usuários</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/dataTables.bootstrap4.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
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
        .btn-sm {
            min-width: 70px;
        }
        .table {
            width: 100%;
        }
        .table th {
            white-space: nowrap;
        }
        .badge-permissao {
            font-size: 0.85em;
            padding: 0.35em 0.65em;
        }
        .badge-grupo {
            font-size: 0.8em;
            margin-right: 3px;
            margin-bottom: 3px;
            display: inline-block;
        }
        .grupos-container {
            max-width: 250px;
        }
        .header-actions {
            margin-bottom: 20px;
        }
        .badge-status {
            font-size: 0.85em;
            padding: 0.5em 0.75em;
            border-radius: 50px;
            min-width: 70px;
            display: inline-block;
            text-align: center;
        }
        .badge-ativo {
            background-color: #28a745;
            color: white;
        }
        .badge-inativo {
            background-color: #dc3545;
            color: white;
        }
        .user-inativo {
            opacity: 0.7;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-title {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .stats-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .stats-total {
            color: #6c757d;
        }
        .stats-ativos {
            color: #28a745;
        }
        .stats-inativos {
            color: #dc3545;
        }
        .grupos-stats {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 10px;
        }
        .grupo-stat-item {
            padding: 5px 0;
            border-bottom: 1px solid #f1f1f1;
        }
        .grupo-stat-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Seção de Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="stats-card">
                    <h5 class="mb-3"><i class="fas fa-chart-bar mr-2"></i>Estatísticas de Usuários</h5>
                    
                    <div class="row">
                        <!-- Estatísticas Gerais -->
                        <div class="col-md-4 mb-3">
                            <div class="text-center">
                                <div class="stats-title">Total de Usuários</div>
                                <div class="stats-value stats-total"><?php echo $total_usuarios; ?></div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="text-center">
                                <div class="stats-title">Usuários Ativos</div>
                                <div class="stats-value stats-ativos"><?php echo $usuarios_ativos; ?></div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="text-center">
                                <div class="stats-title">Usuários Inativos</div>
                                <div class="stats-value stats-inativos"><?php echo $usuarios_inativos; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Estatísticas por Grupo -->
                    <?php if (!empty($contagem_grupos)): ?>
                    <div class="mt-3">
                        <h6><i class="fas fa-users mr-2"></i>Estatísticas por Grupo</h6>
                        <div class="grupos-stats">
                            <div class="row">
                                <?php foreach ($contagem_grupos as $grupo): ?>
                                <div class="col-md-4 grupo-stat-item">
                                    <strong><?php echo htmlspecialchars($grupo['Nome_Grupo']); ?></strong>
                                    <div class="small text-muted">
                                        <span class="text-success">Ativos: <?php echo $grupo['ativos']; ?></span> | 
                                        <span class="text-danger">Inativos: <?php echo $grupo['inativos']; ?></span> | 
                                        <span class="text-muted">Total: <?php echo $grupo['total_usuarios']; ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center header-actions">
            <h4 class="mb-0"><i class="fas fa-users mr-2"></i>Lista de Usuários</h4>
            <div>
                <a href="#" class="btn btn-info" onclick="window.close(); return false;">
                    <i class="fas fa-times mr-1"></i> Fechar
                </a>
                <a href="usr_novo.php" class="btn btn-primary">
                    <i class="fas fa-user-plus mr-1"></i> Novo Usuário
                </a>
                <a href="usr_grupo_list.php" class="btn btn-primary">
                    <i class="fas fa-users mr-1"></i> Grupos
                </a>
                <a href="usr_grupo_contagem.php" class="btn btn-primary">
                    <i class="fas fa-users mr-1"></i> Grupos Contagem
                </a>
            </div>
        </div>
        
        <div class="table-responsive">
            <table id="tabelaUsuarios" class="table table-striped table-bordered table-hover" style="width:100%">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>Empresa</th>
                        <th>Status</th>
                        <th>Função</th>
                        <th>Permissão</th>
                        <th>Grupos</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <?php
                        $userId = $usuario['UserId'];
                        
                        // Obter grupos do usuário
                        $grupos = obterGruposUsuario($userId, $conn);
                        
                        $html_grupos = '';
                        if (!empty($grupos)) {
                            foreach ($grupos as $grupo) {
                                $html_grupos .= '<span class="badge badge-info badge-grupo">' . htmlspecialchars($grupo['Nome_Grupo']) . '</span>';
                            }
                        } else {
                            $html_grupos = '<span class="text-muted">Nenhum grupo</span>';
                        }
                        
                        // Determinar status do usuário
                        if ($usuario['Ativo']) {
                            $statusClass = 'badge-ativo';
                            $statusText = 'ATIVO';
                        } else {
                            $statusClass = 'badge-inativo';
                            $statusText = 'INATIVO';
                        }
                        
                        // Determinar classe da badge de permissão
                        switch ($usuario['Permissao']) {
                            case 1: $badgeClass = 'badge-danger'; break;
                            case 2: $badgeClass = 'badge-warning'; break;
                            case 3: $badgeClass = 'badge-warning'; break;
                            case 4: $badgeClass = 'badge-info'; break;
                            case 5: $badgeClass = 'badge-secondary'; break;
                            case 6: $badgeClass = 'badge-secondary'; break;
                            default: $badgeClass = 'badge-light';
                        }
                        ?>
                        <tr class="<?php echo (!$usuario['Ativo']) ? 'user-inativo' : ''; ?>">
                            <td><strong><?php echo $userId; ?></strong></td>
                            <td>
                                <strong><?php echo strtoupper($usuario['Usuario']); ?></strong><br>
                                <small class="text-muted"><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($usuario['Nome']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($usuario['Grupo']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($usuario['Empresa']); ?></small><br>

                                <?php if (isset($_SESSION['Usuario']) && strtolower($_SESSION['Usuario']) === 'barreto'): ?>

                                    <small class="text-muted"><i class="fas fa-envelope mr-1"></i><?php echo 'Usuário: '.htmlspecialchars($usuario['Usuario']); ?></small><br>
                                    <small class="text-muted"><i class="fas fa-envelope mr-1"></i><?php echo 'Senha: '.htmlspecialchars($usuario['SenhaAtual']); ?></small><br>
                                <?php endif; ?>


     

                                <small class="text-muted"><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($usuario['Email']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($usuario['Telefones']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-id-badge mr-1"></i>CRECI: <?php echo htmlspecialchars($usuario['CRECI']); ?></small><br>



                                <small class="text-muted"><i class="fas fa-calendar-alt mr-1"></i>D/C: <?php echo htmlspecialchars($usuario['Data']); ?></small>
                                <br>
                                <a href="usr_email_credencial.php?UserID=<?php echo $userId; ?>" class="btn btn-info" title="Enviar Credenciais" 
                                    onclick="return confirm('Tem certeza que deseja enviar as credenciais para este usuário?');" target="_blank">
                                    <i class="fas fa-envelope"></i> 
                                </a>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($usuario['Empresa'] ?? ''); ?>
                            </td>
                            <td>
                                <span class="badge badge-status <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $badgeClass; ?> badge-permissao">
                                    <?php echo strtoupper($usuario['Funcao']); ?>
                                </span>
                            </td>
                            <td><?php echo $usuario['Permissao']; ?></td>
                            <td class="grupos-container"><?php echo $html_grupos; ?></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="usr_edit.php?id=<?php echo $userId; ?>" class="btn btn-warning" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="usr_email_credencial.php?UserID=<?php echo $userId; ?>" class="btn btn-info" title="Enviar Credenciais" 
                                        onclick="return confirm('Tem certeza que deseja enviar as credenciais para este usuário?');" target="_blank">
                                        <i class="fas fa-envelope"></i> 
                                    </a>
                                    <a href="usr_excluir.php?id=<?php echo $userId; ?>" class="btn btn-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este usuário?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <footer class="text-center text-muted small mb-3">
            Sunny System &copy; <?php echo date('Y'); ?>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#tabelaUsuarios').DataTable({
                "order": [[0, "desc"]],
                "pageLength": 100,
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
                    "select": {
                        "rows": {
                            "_": "Selecionado %d linhas",
                            "0": "Nenhuma linha selecionada",
                            "1": "Selecionado 1 linha"
                        }
                    },
                    "decimal": ",",
                    "thousands": "."
                },
                "dom": '<"top"f>rt<"bottom"lip><"clear">',
                "responsive": true,
                "initComplete": function() {
                    $('.dataTables_filter input').addClass('form-control').attr('placeholder', 'Pesquisar...');
                    $('.dataTables_length select').addClass('form-control');
                },
                "columnDefs": [
                    { "responsivePriority": 1, "targets": 1 }, // Usuário
                    { "responsivePriority": 2, "targets": -1 }, // Ações
                    { "responsivePriority": 3, "targets": 6 }, // Grupos
                    { "responsivePriority": 4, "targets": 4 }, // Função
                    { "responsivePriority": 5, "targets": 3 }, // Status
                    { "responsivePriority": 6, "targets": 0 }  // ID
                ]
            });
        });          
    </script>
</body>
</html>
<?php
// Fechar conexão
$conn = null;
?>