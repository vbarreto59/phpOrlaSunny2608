<?php
// rh_list.php
session_start();
require_once 'conn.php';

// Configurar headers para UTF-8
header('Content-Type: text/html; charset=utf-8');

// =========================================================
// FUNÇÕES AUXILIARES - Verificar se já não existem
// =========================================================

if (!function_exists('getOpenConnection')) {
    function getOpenConnection() {
        global $conn;
        return $conn;
    }
}

if (!function_exists('closeConnection')) {
    function closeConnection($conn = null) {
        // No PDO, a conexão é fechada automaticamente quando o objeto é destruído
        // Podemos apenas setar para null se passado por referência
        if ($conn) {
            $conn = null;
        }
    }
}

// =========================================================
// API PARA AJAX - POST
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $userId = $_POST['userId'] ?? '';
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    
    $conn = getOpenConnection();
    
    if ($conn) {
        try {
            switch ($field) {
                case "grupo":
                    // Verificar se existe relacionamento na tabela Usuario_Grupo
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM usuario_Grupo WHERE Userid = ?");
                    $stmt->execute([$userId]);
                    $result = $stmt->fetch();
                    
                    if ($result['total'] > 0) {
                        if ($value == "") {
                            $sql = "UPDATE Usuario_Grupo SET ID_Grupo = NULL WHERE Userid = ?";
                        } else {
                            $sql = "UPDATE Usuario_Grupo SET ID_Grupo = ? WHERE Userid = ?";
                        }
                    } else {
                        if ($value == "") {
                            $sql = "INSERT INTO Usuario_Grupo (Userid, ID_Grupo) VALUES (?, NULL)";
                        } else {
                            $sql = "INSERT INTO Usuario_Grupo (Userid, ID_Grupo) VALUES (?, ?)";
                        }
                    }
                    
                    $stmt = $conn->prepare($sql);
                    if ($value == "") {
                        $stmt->execute([$userId]);
                    } else {
                        $stmt->execute([$value, $userId]);
                    }
                    
                    // Atualizar tabela Usuarios
                    if ($value != "") {
                        $stmt = $conn->prepare("SELECT Nome_Grupo FROM grupo WHERE ID_Grupo = ?");
                        $stmt->execute([$value]);
                        $grupo = $stmt->fetch();
                        
                        if ($grupo) {
                            $sql = "UPDATE Usuarios SET GrupoId = ?, Grupo = ? WHERE UserID = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$value, $grupo['Nome_Grupo'], $userId]);
                        } else {
                            $sql = "UPDATE Usuarios SET GrupoId = NULL, Grupo = NULL WHERE UserID = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$userId]);
                        }
                    } else {
                        $sql = "UPDATE Usuarios SET GrupoId = NULL, Grupo = NULL WHERE UserID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$userId]);
                    }
                    break;
                    
                case "diretoria":
                    if ($value == "") {
                        $sql = "UPDATE usuarios SET DiretoriaID = NULL, Diretoria = NULL, GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$userId]);
                    } else {
                        $stmt = $conn->prepare("SELECT NomeDiretoria FROM diretorias WHERE DiretoriaID = ?");
                        $stmt->execute([$value]);
                        $diretoria = $stmt->fetch();
                        
                        if ($diretoria) {
                            $sql = "UPDATE usuarios SET DiretoriaID = ?, Diretoria = ?, GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$value, $diretoria['NomeDiretoria'], $userId]);
                        } else {
                            $sql = "UPDATE usuarios SET DiretoriaID = NULL, Diretoria = NULL, GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$userId]);
                        }
                    }
                    break;
                    
                case "gerencia":
                    if ($value == "") {
                        $sql = "UPDATE Usuarios SET GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$userId]);
                    } else {
                        $stmt = $conn->prepare("SELECT NomeGerencia FROM Gerencias WHERE GerenciaID = ?");
                        $stmt->execute([$value]);
                        $gerencia = $stmt->fetch();
                        
                        if ($gerencia) {
                            $sql = "UPDATE Usuarios SET GerenciaID = ?, Gerencia = ? WHERE UserID = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$value, $gerencia['NomeGerencia'], $userId]);
                        } else {
                            $sql = "UPDATE Usuarios SET GerenciaID = NULL, Gerencia = NULL WHERE UserID = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$userId]);
                        }
                    }
                    break;
            }
            
            echo json_encode(["status" => "success", "message" => "Atualizado com sucesso"]);
            
        } catch(PDOException $e) {
            error_log("Erro ao atualizar: " . $e->getMessage());
            echo json_encode(["status" => "error", "message" => "Erro ao atualizar: " . $e->getMessage()]);
        }
        
        exit;
        
    } else {
        echo json_encode(["status" => "error", "message" => "Não foi possível conectar ao banco de dados"]);
        exit;
    }
}

// =========================================================
// ENDPOINT PARA CARREGAR GERÊNCIAS COM BASE NA DIRETORIA
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['action']) && $_GET['action'] == 'get-gerencias') {
    $diretoriaId = $_GET['diretoriaId'] ?? '';
    
    $conn = getOpenConnection();
    
    if ($conn) {
        header('Content-Type: application/json');
        
        try {
            $stmt = $conn->prepare("SELECT GerenciaID, NomeGerencia FROM Gerencias WHERE DiretoriaID = ? ORDER BY NomeGerencia ASC");
            $stmt->execute([$diretoriaId]);
            $gerencias = $stmt->fetchAll();
            
            $result = [];
            foreach ($gerencias as $gerencia) {
                $result[] = [
                    "id" => $gerencia['GerenciaID'],
                    "nome" => $gerencia['NomeGerencia']
                ];
            }
            
            echo json_encode($result);
            
        } catch(PDOException $e) {
            error_log("Erro ao buscar gerências: " . $e->getMessage());
            echo json_encode(["status" => "error", "message" => "Erro ao buscar gerências"]);
        }
        
        exit;
        
    } else {
        echo json_encode(["status" => "error", "message" => "Não foi possível conectar ao banco de dados"]);
        exit;
    }
}

// =========================================================
// PÁGINA PRINCIPAL
// =========================================================

$conn = getOpenConnection();

if (!$conn) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Erro crítico: Não foi possível conectar ao banco de dados. Por favor, verifique as configurações de conexão.</div></div>';
    exit;
}

try {
    // Obter todos os grupos
    $stmt = $conn->query("SELECT * FROM grupo ORDER BY Nome_Grupo ASC");
    $allGrupos = $stmt->fetchAll();
    
    // Obter todas as diretorias
    $stmt = $conn->query("SELECT * FROM diretorias ORDER BY NomeDiretoria ASC");
    $allDiretorias = $stmt->fetchAll();
    
    // Obter todas as gerências
    $stmt = $conn->query("SELECT * FROM gerencias ORDER BY NomeGerencia ASC");
    $allGerencias = $stmt->fetchAll();
    
    // Obter relacionamentos Usuario_Grupo
    $stmt = $conn->query("SELECT * FROM usuario_grupo");
    $userGroups = $stmt->fetchAll();
    
    $dictUserGroups = [];
    foreach ($userGroups as $group) {
        $dictUserGroups[$group['UserId']] = $group['ID_Grupo'];
    }
    
    // Obter todos os usuários
    $stmt = $conn->query("SELECT * FROM usuarios");
    $users = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Erro ao carregar dados: " . $e->getMessage());
    echo '<div class="container mt-5"><div class="alert alert-danger">Erro ao carregar dados: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    exit;
}
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
        .form-select-sm {
            height: calc(1.8125rem + 2px);
            padding: .25rem .5rem;
            font-size: .875rem;
            line-height: 1.5;
            border-radius: .2rem;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(0,0,0,.1);
            border-radius: 50%;
            border-top-color: #007bff;
            animation: spin 1s ease-in-out infinite;
            margin-left: 5px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center header-actions">
            <h4 class="mb-0"><i class="fas fa-users mr-2"></i>Lista de Usuários</h4>
            <div>
                <a href="#" class="btn btn-info" onclick="window.close(); return false;">
                    <i class="fas fa-times mr-1"></i> Fechar
                </a>
                <a href="usr_novo.php" class="btn btn-primary" target="_blank">
                    <i class="fas fa-user-plus mr-1"></i> Novo Usuário
                </a>
                <a href="usr_grupo_list.php" class="btn btn-primary" target="_blank">
                    <i class="fas fa-users mr-1"></i> Grupos
                </a>
                <a href="diretoria_list.php" class="btn btn-primary" target="_blank">
                    <i class="fas fa-building mr-1"></i> Diretorias
                </a>
                <a href="gerencia_list.php" class="btn btn-primary">
                    <i class="fas fa-sitemap mr-1"></i> Gerências
                </a>
            </div>
        </div>
        
        <div class="table-responsive">
            <table id="tabelaUsuarios" class="table table-striped table-bordered table-hover" style="width:100%">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>Status</th>
                        <th>Função</th>
                        <th>Grupo</th>
                        <th>Diretoria</th>
                        <th>Gerência</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userIdCurrent = $user['UserId'];
                        $userDiretoriaId = $user['DiretoriaID'];
                        $userGerenciaId = $user['GerenciaID'];
                        $currentGrupoId = $user['GrupoId'];
                        
                        // Determinar status do usuário
                        $statusClass = ($user['Ativo'] == 1 || $user['Ativo'] === true) ? "badge-ativo" : "badge-inativo";
                        $statusText = ($user['Ativo'] == 1 || $user['Ativo'] === true) ? "ATIVO" : "INATIVO";
                        $userInativoClass = ($user['Ativo'] == 0 || $user['Ativo'] === false) ? "user-inativo" : "";
                        
                        // Determinar classe da badge de permissão
                        $badgeClass = "";
                        $permissao = intval($user['Permissao']);
                        switch ($permissao) {
                            case 1: $badgeClass = "badge-danger"; break; // Admin
                            case 2: $badgeClass = "badge-warning"; break; // Gerente
                            case 3: $badgeClass = "badge-warning"; break; // Coordenador
                            case 4: $badgeClass = "badge-info"; break; // Vendedor/Corretor
                            case 5: $badgeClass = "badge-secondary"; break; // Cliente/Público
                            case 6: $badgeClass = "badge-secondary"; break; // Outros
                            default: $badgeClass = "badge-light";
                        }
                        ?>
                        <tr class="<?php echo $userInativoClass; ?>" data-user-id="<?php echo $userIdCurrent; ?>">
                            <td><strong><?php echo $userIdCurrent; ?></strong></td>
                            <td>
                                <strong><?php echo strtoupper(htmlspecialchars($user['Usuario'])); ?></strong><br>
                                <small class="text-muted"><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($user['Nome']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($user['Email']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($user['Telefones']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-id-badge mr-1"></i>CRECI: <?php echo htmlspecialchars($user['CRECI']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-id-badge mr-1"></i>Grupo: <?php echo htmlspecialchars($user['Grupo']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-id-badge mr-1"></i>Dir.: <?php echo htmlspecialchars($user['Diretoria']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-id-badge mr-1"></i>Ger.: <?php echo htmlspecialchars($user['Gerencia']); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-status <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $badgeClass; ?> badge-permissao"><?php echo strtoupper(htmlspecialchars($user['Funcao'])); ?></span>
                            </td>
                            <!-- select grupo -->
                            <td class="select-container">
                                <select class="form-control form-select-sm grupo-select" data-user-id="<?php echo $userIdCurrent; ?>">
                                    <option value="">-- Selecione --</option>
                                    <?php foreach ($allGrupos as $grupo): ?>
                                        <?php
                                        $selectedGroup = "";
                                        if (!is_null($currentGrupoId) && !is_null($grupo['ID_Grupo']) && intval($grupo['ID_Grupo']) == intval($currentGrupoId)) {
                                            $selectedGroup = " selected";
                                        }
                                        ?>
                                        <option value="<?php echo $grupo['ID_Grupo']; ?>"<?php echo $selectedGroup; ?>>
                                            <?php echo htmlspecialchars($grupo['Nome_Grupo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <!-- fim select grupo  -->
                            <td class="select-container">
                                <select class="form-control form-select-sm diretoria-select" data-user-id="<?php echo $userIdCurrent; ?>">
                                    <option value="">-- Selecione --</option>
                                    <?php foreach ($allDiretorias as $diretoria): ?>
                                        <?php
                                        $selectedDiretoria = "";
                                        if (!is_null($userDiretoriaId) && !is_null($diretoria['DiretoriaID']) && intval($diretoria['DiretoriaID']) == intval($userDiretoriaId)) {
                                            $selectedDiretoria = " selected";
                                        }
                                        ?>
                                        <option value="<?php echo $diretoria['DiretoriaId']; ?>"<?php echo $selectedDiretoria; ?>>
                                            <?php echo htmlspecialchars($diretoria['NomeDiretoria']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="select-container">
                                <select class="form-control form-select-sm gerencia-select" data-user-id="<?php echo $userIdCurrent; ?>" 
                                    <?php echo (empty($userDiretoriaId) || is_null($userDiretoriaId)) ? "disabled" : ""; ?>>
                                    <option value="">-- Selecione --</option>
                                    <?php if (!empty($userDiretoriaId) && !is_null($userDiretoriaId)): ?>
                                        <?php foreach ($allGerencias as $gerencia): ?>
                                            <?php if (intval($gerencia['DiretoriaID']) == intval($userDiretoriaId)): ?>
                                                <?php
                                                $selectedGerencia = "";
                                                if (!is_null($userGerenciaId) && !is_null($gerencia['GerenciaID']) && intval($gerencia['GerenciaID']) == intval($userGerenciaId)) {
                                                    $selectedGerencia = " selected";
                                                }
                                                ?>
                                                <option value="<?php echo $gerencia['GerenciaID']; ?>"<?php echo $selectedGerencia; ?>>
                                                    <?php echo htmlspecialchars($gerencia['NomeGerencia']); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="usr_edit.php?id=<?php echo $userIdCurrent; ?>" class="btn btn-warning" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="usr_email_credencial.php?UserID=<?php echo $userIdCurrent; ?>" class="btn btn-info" title="Enviar Credenciais"
                                        onclick="return confirm('Tem certeza que deseja enviar as credenciais para este usuário?');" target="_blank">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                    <a href="usr_excluir.php?id=<?php echo $userIdCurrent; ?>" class="btn btn-danger" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este usuário?')">
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
        // Inicializar DataTable
        var table = $('#tabelaUsuarios').DataTable({
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
                { "responsivePriority": 3, "targets": 4 }, // Grupo
                { "responsivePriority": 4, "targets": 5 }, // Diretoria
                { "responsivePriority": 5, "targets": 6 }, // Gerência
                { "responsivePriority": 6, "targets": 3 }, // Função
                { "responsivePriority": 7, "targets": 2 }, // Status
                { "responsivePriority": 8, "targets": 0 }, // ID
                { "orderable": false, "targets": [4, 5, 6, 7] } // Desabilita ordenação para os selects e ações
            ]
        });
        
        // Função para mostrar feedback visual
        function showFeedback(element, success) {
            var originalColor = element.css('border-color');
            element.css('border-color', success ? '#28a745' : '#dc3545');
            
            setTimeout(function() {
                element.css('border-color', originalColor);
            }, 1000);
        }
        
        // Atualizar Grupo via AJAX
        $('.grupo-select').on('change', function() {
            var select = $(this);
            var userId = select.data('user-id');
            var grupoId = select.val();
            var loading = $('<span class="loading"></span>');
            
            select.after(loading);
            select.prop('disabled', true);
            
            $.ajax({
                url: 'rh_list.php?ajax=1',
                type: 'POST',
                data: {
                    userId: userId,
                    field: 'grupo',
                    value: grupoId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showFeedback(select, true);
                        // Atualizar o texto do grupo na linha do usuário
                        var grupoNome = grupoId ? select.find('option:selected').text() : '';
                        select.closest('tr').find('td:eq(1) small:eq(4)').text('Grupo: ' + grupoNome);
                    } else {
                        showFeedback(select, false);
                        alert(response.message);
                        // Reverter para o valor anterior
                        select.val(select.data('previous-value'));
                    }
                },
                error: function() {
                    showFeedback(select, false);
                    alert('Erro na comunicação com o servidor');
                    select.val(select.data('previous-value'));
                },
                complete: function() {
                    loading.remove();
                    select.prop('disabled', false);
                }
            });
        });
        
        // Atualizar Diretoria via AJAX
        $('.diretoria-select').on('change', function() {
            var select = $(this);
            var userId = select.data('user-id');
            var diretoriaId = select.val();
            var loading = $('<span class="loading"></span>');
            var gerenciaSelect = select.closest('tr').find('.gerencia-select');
            
            select.after(loading);
            select.prop('disabled', true);
            gerenciaSelect.prop('disabled', true).val('');
            
            $.ajax({
                url: 'rh_list.php?ajax=1',
                type: 'POST',
                data: {
                    userId: userId,
                    field: 'diretoria',
                    value: diretoriaId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showFeedback(select, true);
                        
                        // Carregar gerências para a diretoria selecionada
                        if (diretoriaId) {
                            $.getJSON('rh_list.php?ajax=1&action=get-gerencias&diretoriaId=' + diretoriaId, function(gerencias) {
                                gerenciaSelect.empty().append('<option value="">-- Selecione --</option>');
                                
                                $.each(gerencias, function(index, gerencia) {
                                    gerenciaSelect.append('<option value="' + gerencia.id + '">' + gerencia.nome + '</option>');
                                });
                                
                                gerenciaSelect.prop('disabled', false);
                            });
                        } else {
                            gerenciaSelect.prop('disabled', true);
                        }
                    } else {
                        showFeedback(select, false);
                        alert(response.message);
                        select.val(select.data('previous-value'));
                    }
                },
                error: function() {
                    showFeedback(select, false);
                    alert('Erro na comunicação com o servidor');
                    select.val(select.data('previous-value'));
                },
                complete: function() {
                    loading.remove();
                    select.prop('disabled', false);
                }
            });
        });
        
        // Atualizar Gerência via AJAX
        $('.gerencia-select').on('change', function() {
            var select = $(this);
            var userId = select.data('user-id');
            var gerenciaId = select.val();
            var loading = $('<span class="loading"></span>');
            
            select.after(loading);
            select.prop('disabled', true);
            
            $.ajax({
                url: 'rh_list.php?ajax=1',
                type: 'POST',
                data: {
                    userId: userId,
                    field: 'gerencia',
                    value: gerenciaId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showFeedback(select, true);
                    } else {
                        showFeedback(select, false);
                        alert(response.message);
                        select.val(select.data('previous-value'));
                    }
                },
                error: function() {
                    showFeedback(select, false);
                    alert('Erro na comunicação com o servidor');
                    select.val(select.data('previous-value'));
                },
                complete: function() {
                    loading.remove();
                    select.prop('disabled', false);
                }
            });
        });
        
        // Armazenar valores anteriores para possível reversão
        $('select').each(function() {
            $(this).data('previous-value', $(this).val());
        });
    });
    </script>
</body>
</html>