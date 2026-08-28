<?php
// Iniciar sessão
session_start();

// Incluir arquivos necessários
require_once 'conn.php';
require_once "usr_acoes.php";


// ============== Novo! - criado em 19 02 2026
protegerPagina(true);  
// ===========================================

// Configuração: ativar/desativar logging SQL (1 = ativo, 0 = desativo)
$SQL_LOGGING_ENABLED = 1; // Mude para 0 para desativar o logging

// Função para log SQL
function logSQL($sql, $params = [], $userId = 0) {
    global $SQL_LOGGING_ENABLED;
    
    if (!$SQL_LOGGING_ENABLED) {
        return;
    }
    
    $logFile = 'sql.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Prepara a mensagem do log
    $logMessage = "[$timestamp] [IP: $ip] [UserID: $userId] SQL: $sql";
    
    // Adiciona parâmetros se existirem
    if (!empty($params)) {
        $logMessage .= " | Params: " . json_encode($params);
    }
    
    // Adiciona uma linha em branco para separação
    $logMessage .= PHP_EOL . PHP_EOL;
    
    // Escreve no arquivo de log
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}


// Funções de utilidade
function safeValue($value, $defaultValue = '') {
    return ($value === null || $value === '' || $value === false) ? $defaultValue : $value;
}

// Processamento de formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'toggle_status') {
        // Toggle status do usuário
        $userId = (int)($_POST['User_Id'] ?? 0);
        $novoStatus = (int)($_POST['novo_status'] ?? 0);
        
        if ($userId > 0) {
            try {
                $sql = "UPDATE usuarios SET Ativo = :status WHERE UserID = :userid AND IdEmp = 2";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':status', $novoStatus, PDO::PARAM_INT);
                $stmt->bindValue(':userid', $userId, PDO::PARAM_INT);
                
                // Log do SQL
                logSQL($sql, ['Ativo' => $novoStatus, 'UserID' => $userId], $userId);
                
                if ($stmt->execute()) {
                    header("Location: ?success=1&userid=" . $userId);
                    exit();
                } else {
                    header("Location: ?error=1&msg=" . urlencode("Erro ao atualizar"));
                    exit();
                }
            } catch (PDOException $e) {
                logSQL("ERRO: " . $e->getMessage(), ['UserID' => $userId, 'status' => $novoStatus], $userId);
                header("Location: ?error=1&msg=" . urlencode("Erro: " . $e->getMessage()));
                exit();
            }
        }
    } 
    elseif ($acao === 'update_Diretoria_Gerencia') {
        // Atualizar Diretoria e gerência
        $userId = (int)($_POST['User_Id'] ?? 0);
        $DiretoriaId = (int)($_POST['Diretoria_ID'] ?? 0);
        $GerenciaId = (int)($_POST['Gerencia_ID'] ?? 0);
        
        if ($userId > 0 && $DiretoriaId > 0 && $GerenciaId > 0) {
            try {
                // Obter nomes da Diretoria e gerência em uma única consulta
                $sqlNomes = "SELECT 
                    d.NomeDiretoria, 
                    g.NomeGerencia 
                    FROM diretorias d 
                    INNER JOIN gerencias g ON d.DiretoriaID = g.DiretoriaID 
                    WHERE d.DiretoriaID = :diretorid 
                    AND g.GerenciaID = :gerenciaid";
                
                $stmtNomes = $conn->prepare($sqlNomes);
                $stmtNomes->bindValue(':diretorid', $DiretoriaId, PDO::PARAM_INT);
                $stmtNomes->bindValue(':gerenciaid', $GerenciaId, PDO::PARAM_INT);
                $stmtNomes->execute();
                $nomes = $stmtNomes->fetch(PDO::FETCH_ASSOC);
                $nomeDiretoria = $nomes['NomeDiretoria'] ?? '';
                $nomeGerencia = $nomes['NomeGerencia'] ?? '';
                
                // Se não encontrou com JOIN, buscar separadamente
                if (empty($nomeDiretoria)) {
                    $stmtDir = $conn->prepare("SELECT NomeDiretoria FROM diretorias WHERE DiretoriaID = :diretorid");
                    $stmtDir->bindValue(':diretorid', $DiretoriaId, PDO::PARAM_INT);
                    $stmtDir->execute();
                    $Diretoria = $stmtDir->fetch(PDO::FETCH_ASSOC);
                    $nomeDiretoria = $Diretoria['NomeDiretoria'] ?? '';
                }
                
                if (empty($nomeGerencia)) {
                    $stmtGer = $conn->prepare("SELECT NomeGerencia FROM gerencias WHERE GerenciaID = :gerenciaid");
                    $stmtGer->bindValue(':gerenciaid', $GerenciaId, PDO::PARAM_INT);
                    $stmtGer->execute();
                    $Gerencia = $stmtGer->fetch(PDO::FETCH_ASSOC);
                    $nomeGerencia = $Gerencia['NomeGerencia'] ?? '';
                }
                
                // Atualizar no banco
                $sqlUpdate = "UPDATE usuarios 
                    SET DiretoriaID = :diretorid, 
                        Diretoria = :diretoria,
                        GerenciaID = :gerenciaid, 
                        Gerencia = :gerencia 
                    WHERE UserID = :userid 
                    AND IdEmp = 2";
                
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bindValue(':diretorid', $DiretoriaId, PDO::PARAM_INT);
                $stmtUpdate->bindValue(':diretoria', $nomeDiretoria, PDO::PARAM_STR);
                $stmtUpdate->bindValue(':gerenciaid', $GerenciaId, PDO::PARAM_INT);
                $stmtUpdate->bindValue(':gerencia', $nomeGerencia, PDO::PARAM_STR);
                $stmtUpdate->bindValue(':userid', $userId, PDO::PARAM_INT);
                
                // Log do SQL UPDATE com todos os parâmetros
                $logParams = [
                    'DiretoriaID' => $DiretoriaId,
                    'Diretoria' => $nomeDiretoria,
                    'GerenciaID' => $GerenciaId,
                    'Gerencia' => $nomeGerencia,
                    'UserID' => $userId
                ];
                logSQL($sqlUpdate, $logParams, $userId);
                
                if ($stmtUpdate->execute()) {
                    header("Location: ?success=2&userid=" . $userId . "&Diretoria=" . $DiretoriaId . "&Gerencia=" . $GerenciaId);
                    exit();
                } else {
                    // Log de erro
                    logSQL("ERRO na execução", $logParams, $userId);
                    header("Location: ?error=2&msg=" . urlencode("Erro ao atualizar"));
                    exit();
                }
            } catch (PDOException $e) {
                // Log de erro
                $logParams = [
                    'UserID' => $userId,
                    'DiretoriaID' => $DiretoriaId,
                    'GerenciaID' => $GerenciaId
                ];
                logSQL("ERRO PDO: " . $e->getMessage(), $logParams, $userId);
                header("Location: ?error=2&msg=" . urlencode("Erro: " . $e->getMessage()));
                exit();
            }
        } else {
            // Log de erro de validação
            $logParams = [
                'UserID' => $userId,
                'DiretoriaID' => $DiretoriaId,
                'GerenciaID' => $GerenciaId
            ];
            logSQL("ERRO VALIDAÇÃO: Diretoria ou Gerência não selecionada", $logParams, $userId);
            header("Location: ?error=2&msg=" . urlencode("Diretoria ou Gerência não selecionada"));
            exit();
        }
    }
}

// Preparação de dados (Contagens e Recordsets)
$stats = [];
$dados = [];

// Consultas em lote para melhor performance
try {
    // 1. Total de Usuários ATIVOS - CORRIGIDO: Ativo = 1 (ao invés de -1)
    $sqlAtivos = "SELECT COUNT(UserID) as Total FROM usuarios WHERE IdEmp = 2 AND Ativo = 1";
    $stmt = $conn->prepare($sqlAtivos);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['totalAtivos'] = (int)($row['Total'] ?? 0);
    
    // 2. Total de Usuários ATIVOS sem Diretoria/Gerência - CORRIGIDO: Ativo = 1
    $sqlVazios = "SELECT COUNT(UserID) as Total FROM usuarios 
                  WHERE IdEmp = 2 AND Ativo = 1 
                  AND (DiretoriaID IS NULL OR DiretoriaID = 0 OR GerenciaID IS NULL OR GerenciaID = 0)";
    $stmt = $conn->prepare($sqlVazios);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['totalVazio'] = (int)($row['Total'] ?? 0);
    
    // 3. Contagem por Diretoria - CORRIGIDO: Ativo = 1
    $sqlPorDiretoria = "SELECT d.NomeDiretoria, COUNT(u.UserID) as Total 
                        FROM usuarios u 
                        INNER JOIN diretorias d ON u.DiretoriaID = d.DiretoriaID 
                        WHERE u.IdEmp = 2 AND u.Ativo = 1 
                        GROUP BY d.NomeDiretoria 
                        ORDER BY COUNT(u.UserID) DESC";
    $stmt = $conn->prepare($sqlPorDiretoria);
    $stmt->execute();
    $stats['totalPorDiretoria'] = '';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['totalPorDiretoria'] .= htmlspecialchars($row['NomeDiretoria']) . 
                                      ": <strong>" . $row['Total'] . "</strong><br>";
    }
    if (empty($stats['totalPorDiretoria'])) {
        $stats['totalPorDiretoria'] = "<span class='text-muted small'>Nenhuma Diretoria com ativos.</span>";
    }
    
    // 4. Contagem por Gerência - CORRIGIDO: Ativo = 1
    $sqlPorGerencia = "SELECT g.NomeGerencia, COUNT(u.UserID) as Total 
                       FROM usuarios u 
                       INNER JOIN gerencias g ON u.GerenciaID = g.GerenciaID 
                       WHERE u.IdEmp = 2 AND u.Ativo = 1 
                       GROUP BY g.NomeGerencia 
                       ORDER BY COUNT(u.UserID) DESC";
    $stmt = $conn->prepare($sqlPorGerencia);
    $stmt->execute();
    $stats['totalPorGerencia'] = '';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['totalPorGerencia'] .= htmlspecialchars($row['NomeGerencia']) . 
                                     ": <strong>" . $row['Total'] . "</strong><br>";
    }
    if (empty($stats['totalPorGerencia'])) {
        $stats['totalPorGerencia'] = "<span class='text-muted small'>Nenhuma Gerência com ativos.</span>";
    }
    
    // 5. Obter todas as Diretorias
    $sqlDiretorias = "SELECT DiretoriaID, NomeDiretoria FROM diretorias ORDER BY NomeDiretoria";
    $stmt = $conn->prepare($sqlDiretorias);
    $stmt->execute();
    $dados['Diretorias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. Obter todos os usuários
    $sqlUsers = "SELECT * FROM usuarios WHERE IdEmp = 2 ORDER BY Usuario ASC";
    $stmt = $conn->prepare($sqlUsers);
    $stmt->execute();
    $dados['usuarios'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $userIds = array_column($dados['usuarios'], 'UserID');
    
    // 7. Pré-carregar todos os grupos dos usuários em uma consulta
    $gruposPorUsuario = [];
    if (!empty($userIds)) {
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $sqlGrupos = "SELECT ug.UserId, g.ID_Grupo, g.Nome_Grupo 
                      FROM grupo g 
                      INNER JOIN usuario_grupo ug ON g.ID_Grupo = ug.ID_Grupo 
                      WHERE ug.UserId IN ($placeholders) 
                      ORDER BY g.Nome_Grupo";
        
        $stmt = $conn->prepare($sqlGrupos);
        $stmt->execute($userIds);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userId = $row['UserId'];
            if (!isset($gruposPorUsuario[$userId])) {
                $gruposPorUsuario[$userId] = [];
            }
            $gruposPorUsuario[$userId][] = $row['Nome_Grupo'];
        }
    }
    $dados['gruposPorUsuario'] = $gruposPorUsuario;
    
    // 8. Pré-carregar todas as gerências por Diretoria para JavaScript
    $sqlAllGerencias = "SELECT g.DiretoriaID, g.GerenciaID, g.NomeGerencia 
                        FROM gerencias g 
                        ORDER BY g.DiretoriaID, g.NomeGerencia";
    $stmt = $conn->prepare($sqlAllGerencias);
    $stmt->execute();
    
    // Organizar por Diretoria
    $GerenciasPorDiretoria = [];
    while ($Gerencia = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dirId = $Gerencia['DiretoriaID'];
        if (!isset($GerenciasPorDiretoria[$dirId])) {
            $GerenciasPorDiretoria[$dirId] = [];
        }
        $GerenciasPorDiretoria[$dirId][] = [
            'id' => $Gerencia['GerenciaID'],
            'nome' => htmlspecialchars($Gerencia['NomeGerencia'])
        ];
    }
    
    // Converter para JSON seguro
    $allGerenciasJSON = json_encode($GerenciasPorDiretoria, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    
} catch (PDOException $e) {
    // Log de erro nas consultas
    logSQL("ERRO CONSULTA: " . $e->getMessage(), [], 0);
    die("Erro nas consultas: " . $e->getMessage());
}

// Obter parâmetros da URL para restaurar seleções
$successUserId = $_GET['userid'] ?? '';
$successDiretoria = $_GET['Diretoria'] ?? '';
$successGerencia = $_GET['Gerencia'] ?? '';

// Função para mapear permissões para classes CSS
function getPermissaoClass($permissao) {
    switch ((int)$permissao) {
        case 1: return 'badge-danger';
        case 2: 
        case 3: return 'badge-warning';
        case 4: return 'badge-info';
        case 5: 
        case 6: return 'badge-secondary';
        default: return 'badge-light';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>SGVendas - Lista de Usuários</title>
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
        .btn-sm {
            min-width: 70px;
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
        .btn-toggle {
            width: 80px;
            font-size: 0.8rem;
        }
        .btn-ativo {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        .btn-inativo {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        .toggle-form {
            display: inline;
        }
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
        }
        .select-small {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            height: calc(1.5em + 0.5rem + 2px);
        }
        .Diretoria-Gerencia-form {
            display: inline;
        }
        .btn-update-dg {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }
        .vazio-indicator {
            color: #6c757d;
            font-style: italic;
            font-size: 0.8rem;
            margin-bottom: 5px;
        }
        .select-container {
            position: relative;
        }
        .card-body h5 {
            font-size: 1rem;
        }
        .card-body p.h3 {
            font-size: 1.5rem;
            margin-bottom: 0;
        }
        body {
            transform: scale(0.8); 
            transform-origin: 0 0; 
            width: calc(100% / 0.8); 
        }
        .config-info {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            z-index: 1000;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Informação de configuração (apenas se logging estiver ativo) -->
    <?php if ($SQL_LOGGING_ENABLED): ?>
    <div class="config-info">
        <i class="fas fa-database"></i> SQL Logging: ATIVO
    </div>
    <?php endif; ?>
    
    <div class="alert-container">
        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] == '1'): ?>
                <div class='alert alert-success alert-dismissible fade show'>
                    <i class='fas fa-check-circle mr-2'></i>Status do usuário atualizado com sucesso!
                    <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button>
                </div>
            <?php elseif ($_GET['success'] == '2'): ?>
                <div class='alert alert-success alert-dismissible fade show'>
                    <i class='fas fa-check-circle mr-2'></i>Diretoria e Gerência atualizadas com sucesso!
                    <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <?php
            $errorMsg = htmlspecialchars($_GET['msg'] ?? 'Erro desconhecido');
            if ($_GET['error'] == '1'): ?>
                <div class='alert alert-danger alert-dismissible fade show'>
                    <i class='fas fa-exclamation-circle mr-2'></i>Erro ao atualizar status: <?= $errorMsg ?>
                    <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button>
                </div>
            <?php elseif ($_GET['error'] == '2'): ?>
                <div class='alert alert-danger alert-dismissible fade show'>
                    <i class='fas fa-exclamation-circle mr-2'></i>Erro ao atualizar Diretoria/gerência: <?= $errorMsg ?>
                    <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-user-check"></i> Usuários Ativos</h5>
                    <p class="card-text h3"><?= $stats['totalAtivos'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-exclamation-triangle"></i> Ativos sem Diretoria/Gerência</h5>
                    <p class="card-text h3"><?= $stats['totalVazio'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-dark bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-building"></i> Contagem por Diretoria</h5>
                    <p class="card-text small mb-0"><?= $stats['totalPorDiretoria'] ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-dark bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-sitemap"></i> Contagem por Gerência</h5>
                    <p class="card-text small mb-0"><?= $stats['totalPorGerencia'] ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-between align-items-center header-actions">

        <a href="usrv_gestao_novo_usuario2.php" class="btn btn-primary">
            <i class="fas fa-user-plus mr-1"></i> Novo Usuário
        </a>        
        <div class="d-flex flex-column"> 
            <h4 class="mb-0"><i class="fas fa-users mr-2"></i>Lista de Usuários</h4>
            <small class="mb-0 text-danger"><i class="fas fa-users mr-2"></i>Atualizar um usuário por vez</small>
            <?php if ($SQL_LOGGING_ENABLED): ?>
            <small class="mb-0 text-info"><i class="fas fa-database mr-1"></i>SQL Logging: ATIVO (sql.log)</small>
            <?php endif; ?>
        </div>
        <div>
            <a href="#" class="btn btn-info" onclick="window.close(); return false;">
                <i class="fas fa-times mr-1"></i> Fechar
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
                    <th>Diretoria</th>
                    <th>Gerência</th>
                    
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dados['usuarios'])): ?>
                    <?php foreach ($dados['usuarios'] as $usuario): ?>
                        <?php
                        // Extrair valores
                        $userId = $usuario['UserId'] ?? 0;
                        $ativo = (bool)($usuario['Ativo'] ?? 0);
                        $usuarioNome = $usuario['Usuario'] ?? '';
                        $nome = $usuario['Nome'] ?? '';
                        $email = $usuario['Email'] ?? '';
                        $telefones = $usuario['Telefones'] ?? '';
                        $creci = $usuario['CRECI'] ?? '';
                        $permissao = $usuario['Permissao'] ?? 0;
                        $funcao = $usuario['Funcao'] ?? '';
                        $currentDiretoriaID = $usuario['DiretoriaID'] ?? '';
                        $currentGerenciaID = $usuario['GerenciaID'] ?? '';
                        
                        // Grupos do usuário (pré-carregados)
                        $grupos = '';
                        if (isset($dados['gruposPorUsuario'][$userId])) {
                            foreach ($dados['gruposPorUsuario'][$userId] as $nomeGrupo) {
                                $grupos .= '<span class="badge badge-info badge-grupo">' . htmlspecialchars($nomeGrupo) . '</span>';
                            }
                        }
                        if (empty($grupos)) {
                            $grupos = '<span class="text-muted">Nenhum grupo</span>';
                        }
                        
                        // Status - CORRIGIDO: Ativo = 1 (true), Inativo = 0 (false)
                        if ($ativo) {
                            $statusClass = "badge-ativo";
                            $statusText = "ATIVO";
                            $btnClass = "btn-ativo";
                            $btnText = "ATIVO";
                            $btnIcon = "fas fa-toggle-on";
                            $novoStatus = "0"; // Muda para inativo
                        } else {
                            $statusClass = "badge-inativo";
                            $statusText = "INATIVO";
                            $btnClass = "btn-inativo";
                            $btnText = "INATIVO";
                            $btnIcon = "fas fa-toggle-off";
                            $novoStatus = "1"; // Muda para ativo
                        }
                        
                        // Se este é o usuário que foi atualizado, usar os valores da URL
                        if ((string)$userId === $successUserId && $successDiretoria !== '') {
                            $currentDiretoriaID = $successDiretoria;
                            $currentGerenciaID = $successGerencia;
                        }
                        
                        $showVazio = false;
                        if ($ativo && (empty($currentDiretoriaID) || empty($currentGerenciaID))) {
                            $showVazio = true;
                        }
                        ?>
                        <tr class="<?= !$ativo ? 'user-inativo' : '' ?>">
                            <td><strong><?= htmlspecialchars($userId) ?></strong></td>
                            <td>
                                <strong><?= strtoupper(htmlspecialchars($usuarioNome)) ?></strong><br>
                                <small class="text-muted"><i class="fas fa-user mr-1"></i><?= htmlspecialchars($nome) ?></small><br>
                                <small class="text-muted"><i class="fas fa-envelope mr-1"></i><?= htmlspecialchars($email) ?></small><br>
                                <small class="text-muted"><i class="fas fa-phone mr-1"></i><?= htmlspecialchars($telefones) ?></small><br>
                                <small class="text-muted"><i class="fas fa-id-badge mr-1"></i>CRECI: <?= htmlspecialchars($creci) ?></small>
                            </td>
                            <td>
                                <span class="badge badge-status <?= $statusClass ?>">
                                    <?= $statusText ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= getPermissaoClass($permissao) ?> badge-permissao">
                                    <?= strtoupper(htmlspecialchars($funcao)) ?>
                                </span>
                            </td>
                            
                            <td>
                                <form method="post" class="Diretoria-Gerencia-form" onsubmit="return confirmUpdateDiretoriaGerencia(this, <?= $userId ?>);">
                                    <input type="hidden" name="acao" value="update_Diretoria_Gerencia">
                                    <input type="hidden" name="User_Id" value="<?= $userId ?>">
                                    <div class="select-container">
                                        <?php if ($showVazio): ?>
                                        <div class="vazio-indicator">Atualmente: <strong>Vazio</strong></div>
                                        <?php endif; ?>
                                        <select name="Diretoria_ID" class="form-control form-control-sm select-small Diretoria-select" 
                                            data-userid="<?= $userId ?>" 
                                            data-initial-value="<?= $currentDiretoriaID ?>"
                                            data-initial-Gerencia="<?= $currentGerenciaID ?>">
                                            <option value="">Selecione uma Diretoria...</option>
                                            <?php foreach ($dados['Diretorias'] as $Diretoria): ?>
                                                <option value="<?= $Diretoria['DiretoriaID'] ?>" 
                                                    <?= ((string)$Diretoria['DiretoriaID'] === (string)$currentDiretoriaID) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($Diretoria['NomeDiretoria']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                            </td>
                            
                            <td>
                                <div class="select-container">
                                    <?php if ($showVazio): ?>
                                    <div class="vazio-indicator">Atualmente: <strong>Vazio</strong></div>
                                    <?php endif; ?>
                                    <select name="Gerencia_ID" class="form-control form-control-sm select-small Gerencia-select" 
                                        id="Gerencia_<?= $userId ?>"
                                        data-initial-value="<?= $currentGerenciaID ?>">
                                        <option value="">Selecione uma gerência...</option>
                                        <?php if (!empty($currentDiretoriaID)): ?>
                                            <?php
                                            // Buscar gerências para esta Diretoria usando PDO
                                            try {
                                                $sqlGerencias = "SELECT GerenciaID, NomeGerencia FROM gerencias 
                                                                 WHERE DiretoriaID = :diretorid ORDER BY NomeGerencia";
                                                $stmtGerencias = $conn->prepare($sqlGerencias);
                                                $stmtGerencias->bindValue(':diretorid', $currentDiretoriaID, PDO::PARAM_INT);
                                                $stmtGerencias->execute();
                                                $gerencias = $stmtGerencias->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                foreach ($gerencias as $Gerencia):
                                            ?>
                                                <option value="<?= $Gerencia['GerenciaID'] ?>" 
                                                    <?= ((string)$Gerencia['GerenciaID'] === (string)$currentGerenciaID) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($Gerencia['NomeGerencia']) ?>
                                                </option>
                                            <?php 
                                                endforeach;
                                            } catch (PDOException $e) {
                                                echo '<option value="">Erro ao carregar</option>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </td>
                            
                            
                            <td class="text-center">
                                <div class="btn-group-vertical btn-group-sm" role="group">
                                    <button type="submit" class="btn btn-warning btn-update-dg" title="Atualizar Diretoria e Gerência">
                                        <i class="fas fa-sync-alt mr-1"></i>Atualizar
                                    </button>
                                </form>
                                <form method="post" class="toggle-form" onsubmit="return confirmToggle(this);">
                                    <input type="hidden" name="acao" value="toggle_status">
                                    <input type="hidden" name="User_Id" value="<?= $userId ?>">
                                    <input type="hidden" name="novo_status" value="<?= $novoStatus ?>">
                                    <button type="submit" class="btn <?= $btnClass ?> btn-toggle" 
                                        title="<?= $ativo ? 'Desativar Usuário' : 'Ativar Usuário' ?>">
                                        <i class="<?= $btnIcon ?> mr-1"></i><?= $btnText ?>
                                    </button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">
                            Nenhum usuário encontrado.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <footer class="text-center text-muted small mb-3">
        Sunny System &copy; <?= date('Y') ?>
        <?php if ($SQL_LOGGING_ENABLED): ?>
        <br><small class="text-info"><i class="fas fa-database mr-1"></i>SQL Logging ativo</small>
        <?php endif; ?>
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>

<script>
// Dados pré-carregados das gerências
var allGerencias = <?= $allGerenciasJSON ?>;

// Parâmetros da URL
var urlParams = new URLSearchParams(window.location.search);
var successUserId = urlParams.get('userid');
var successDiretoria = urlParams.get('Diretoria');
var successGerencia = urlParams.get('Gerencia');

// Função para atualizar gerências usando dados pré-carregados
function updateGerencias(selectElement, userId, selectGerenciaId) {
    var DiretoriaId = selectElement.value;
    var GerenciaSelect = document.getElementById('Gerencia_' + userId);
    
    if (!GerenciaSelect) return;

    // Limpa as opções anteriores
    GerenciaSelect.innerHTML = '<option value="">Selecione uma gerência...</option>';

    if (DiretoriaId !== '' && allGerencias[DiretoriaId]) {
        var options = '<option value="">Selecione uma gerência...</option>';
        allGerencias[DiretoriaId].forEach(function(Gerencia) {
            options += '<option value="' + Gerencia.id + '">' + Gerencia.nome + '</option>';
        });
        GerenciaSelect.innerHTML = options;
        
        // Selecionar a gerência correta
        var GerenciaToSelect = selectGerenciaId || GerenciaSelect.getAttribute('data-initial-value');
        if (GerenciaToSelect && GerenciaToSelect !== '') {
            setTimeout(function() {
                GerenciaSelect.value = GerenciaToSelect;
                toggleUpdateButtonByForm(selectElement.closest('form'));
            }, 100);
        }
    } 
    
    toggleUpdateButtonByForm(selectElement.closest('form'));
}

// Habilita/desabilita botão de atualização
function toggleUpdateButtonByForm(form) {
    if (!form) return; 
    
    var DiretoriaSelect = form.querySelector('select[name="Diretoria_ID"]');
    var GerenciaSelect = form.querySelector('select[name="Gerencia_ID"]');
    var btnUpdate = form.querySelector('.btn-update-dg');
    
    if (!DiretoriaSelect || !GerenciaSelect || !btnUpdate) {
        return; 
    }
    
    var DiretoriaValue = DiretoriaSelect.value;
    var GerenciaValue = GerenciaSelect.value;
    
    btnUpdate.disabled = !(DiretoriaValue !== '' && GerenciaValue !== '');
}

// Carregar gerências iniciais
function loadInitialGerencias() {
    $('.Diretoria-select').each(function() {
        var userId = $(this).data('userid');
        var DiretoriaId = $(this).val();
        var initialGerencia = $(this).data('initial-Gerencia') || $(this).attr('data-initial-Gerencia');
        
        if (DiretoriaId && DiretoriaId !== '') {
            updateGerencias(this, userId, initialGerencia);
        }
    });
}

// Restaurar seleções após atualização
function restoreSelections() {
    if (successUserId && successDiretoria) {
        var DiretoriaSelect = document.querySelector('form input[name="User_Id"][value="' + successUserId + '"]')
            .closest('form')
            .querySelector('select[name="Diretoria_ID"]');
        
        if (DiretoriaSelect) {
            DiretoriaSelect.value = successDiretoria;
            updateGerencias(DiretoriaSelect, successUserId, successGerencia);
        }
    }
}

$(document).ready(function() {
    // Carregar gerências iniciais
    loadInitialGerencias();

    // Restaurar seleções se houver parâmetros
    if (successUserId && successDiretoria) {
        setTimeout(restoreSelections, 500);
    }

    // Inicializar estado dos botões
    $('.Diretoria-Gerencia-form').each(function() {
        toggleUpdateButtonByForm(this);
    });

    // Eventos para selects
    $(document).on('change', '.Diretoria-select', function() {
        var userId = $(this).data('userid');
        updateGerencias(this, userId);
    });

    $(document).on('change', '.Gerencia-select', function() {
        var form = $(this).closest('form')[0];
        toggleUpdateButtonByForm(form);
    });

    // DataTables
    $('#tabelaUsuarios').DataTable({
        "order": [[1, "asc"]],
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
            }
        },
        "dom": '<"top"lif>rt<"bottom"lip><"clear">',
        "responsive": true,
        "initComplete": function() {
            $('.dataTables_filter input').addClass('form-control').attr('placeholder', 'Pesquisar...');
            $('.dataTables_length select').addClass('form-control');
            setTimeout(loadInitialGerencias, 100);
            if (successUserId && successDiretoria) {
                setTimeout(restoreSelections, 1000);
            }
        },
        "columnDefs": [
            { "responsivePriority": 1, "targets": 1 }, 
            { "responsivePriority": 2, "targets": -1 },
            { "responsivePriority": 3, "targets": 6 }
        ]
    });

    // Auto-close alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
});

// Função para confirmar alteração de status
function confirmToggle(form) {
    var userId = form.User_Id.value;
    var novoStatus = form.novo_status.value;
    var acao = (novoStatus == "1") ? "ativar" : "desativar";
    var nomeUsuario = form.closest('tr').querySelector('td:nth-child(2) strong').textContent;
    
    if (confirm("Tem certeza que deseja " + acao + " o usuário '" + nomeUsuario + "'?")) {
        var btn = form.querySelector('button');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Processando...';
        btn.disabled = true;
        
        var allButtons = document.querySelectorAll('.btn-toggle, .btn-update-dg');
        allButtons.forEach(function(button) {
            button.disabled = true;
        });
        
        return true;
    }
    return false;
}

// Função para confirmar atualização de Diretoria e gerência
function confirmUpdateDiretoriaGerencia(form, userId) {
    var DiretoriaSelect = form.querySelector('select[name="Diretoria_ID"]');
    var GerenciaSelect = document.getElementById('Gerencia_' + userId);
    
    if (!DiretoriaSelect || !GerenciaSelect) {
        alert('Erro: Elementos do formulário não encontrados.');
        return false;
    }

    var DiretoriaNome = DiretoriaSelect.options[DiretoriaSelect.selectedIndex].text;
    var GerenciaNome = GerenciaSelect.options[GerenciaSelect.selectedIndex].text;
    var nomeUsuario = form.closest('tr').querySelector('td:nth-child(2) strong').textContent;
    
    if (DiretoriaSelect.value === '' || GerenciaSelect.value === '') {
        alert('Por favor, selecione tanto a Diretoria quanto a Gerência.');
        return false;
    }
    
    if (confirm('Tem certeza que deseja atualizar a Diretoria e Gerência do usuário "' + nomeUsuario + '"?\n\nDiretoria: ' + DiretoriaNome + '\nGerência: ' + GerenciaNome)) {
        var btn = form.querySelector('.btn-update-dg');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Processando...';
            btn.disabled = true;
        }
        
        var allButtons = document.querySelectorAll('.btn-toggle, .btn-update-dg');
        allButtons.forEach(function(button) {
            button.disabled = true;
        });
        
        return true;
    }
    return false;
}
</script>
</body>
</html>
<?php
// Limpar recursos PDO
// A conexão será fechada automaticamente quando o script terminar
?>