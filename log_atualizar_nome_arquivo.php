<?php
// log_atualizar_nome_arquivo.php

// Iniciar sessão
session_start();

// Definir codificação e timeout
header('Content-Type: text/html; charset=UTF-8');
set_time_limit(300); // Aumenta timeout para 5 minutos

// Incluir arquivo de conexão
require_once 'conn.php'; // Supondo que você tenha convertido este arquivo

// Verifica se o usuário está logado
if (empty($_SESSION['Usuario'])) {
    header('Location: login_v3a.php');
    exit();
}

// Verifica se o usuário tem permissão para executar esta ação
if (empty($_SESSION['Funcao']) || $_SESSION['Funcao'] !== 'SUPERADMIN') {
    echo "<div class='alert alert-danger'>Acesso negado. Somente administradores podem executar esta ação.</div>";
    exit();
}

// Função para limpar o caminho da URL
function cleanUrlPath($fullPath) {
    if (empty($fullPath)) {
        return '';
    }
    
    // Encontra a última barra
    $lastSlashPos = strrpos($fullPath, '/');
    if ($lastSlashPos !== false) {
        $cleanedPath = substr($fullPath, $lastSlashPos + 1);
    } else {
        $cleanedPath = $fullPath;
    }
    
    // Remove query string
    $queryPos = strpos($cleanedPath, '?');
    if ($queryPos !== false) {
        $cleanedPath = substr($cleanedPath, 0, $queryPos);
    }
    
    return $cleanedPath;
}

// Conexão com o banco de dados
try {
    $conn = getOpenConnectionLog();
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Erro ao conectar ao banco de dados: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit();
}

// Parâmetros da requisição
$acao = $_GET['acao'] ?? '';
$loteAtual = isset($_GET['lote']) ? intval($_GET['lote']) : 0;
$totalAtualizado = isset($_GET['total']) ? intval($_GET['total']) : 0;
$inicioID = $_GET['inicioID'] ?? '';
$totalProcessaveis = $_GET['totalProcessaveis'] ?? '';

// Processamento principal
if ($acao === 'processar') {
    // Se for o primeiro lote, pega o total de registros processáveis
    if ($loteAtual == 1 && empty($totalProcessaveis)) {
        try {
            $sql = "SELECT COUNT(*) AS Total FROM historico_navegacao 
                    WHERE (Arquivo IS NULL OR Arquivo = '') 
                    AND (Caminho_Pagina LIKE '%/%' OR Caminho_Pagina LIKE '%?%')";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalProcessaveis = $result['Total'];
        } catch (PDOException $e) {
            die("Erro ao contar registros: " . $e->getMessage());
        }
    }
    
    // 1. Obter os próximos 500 registros para processar
    try {
        $sql = "SELECT ID_REGISTRO, Caminho_Pagina FROM historico_navegacao 
                WHERE (Arquivo IS NULL OR Arquivo = '') 
                AND (Caminho_Pagina LIKE '%/%' OR Caminho_Pagina LIKE '%?%') ";
        
        // Se tivermos um ID inicial, pegamos registros com ID menor (ordem DESC)
        if (!empty($inicioID)) {
            $sql .= "AND ID_REGISTRO < :inicioID ";
        }
        
        $sql .= "ORDER BY ID_REGISTRO DESC LIMIT 500";
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($inicioID)) {
            $stmt->bindParam(':inicioID', $inicioID, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        die("Erro ao buscar registros: " . $e->getMessage());
    }
    
    if (!empty($registros)) {
        $primeiroID = $registros[0]['ID_REGISTRO'] ?? 0;
        $ultimoID = 0;
        $contador = 0;
        $idsProcessados = [];
        
        // Processar cada registro do lote atual
        foreach ($registros as $registro) {
            $ultimoID = $registro['ID_REGISTRO'];
            $idsProcessados[] = $ultimoID;
            
            $cleanedPath = cleanUrlPath($registro['Caminho_Pagina']);
            
            try {
                $sqlUpdate = "UPDATE historico_navegacao SET Arquivo = :arquivo 
                              WHERE ID_REGISTRO = :id";
                
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':arquivo', $cleanedPath);
                $stmtUpdate->bindParam(':id', $ultimoID, PDO::PARAM_INT);
                
                if ($stmtUpdate->execute()) {
                    $contador++;
                }
            } catch (PDOException $e) {
                // Log do erro, mas continua processando
                error_log("Erro ao atualizar registro {$ultimoID}: " . $e->getMessage());
            }
        }
        
        $totalAtualizado += $contador;
        
        // Verificar se ainda há registros para processar
        try {
            $sqlRestantes = "SELECT COUNT(*) AS Restantes FROM historico_navegacao 
                            WHERE (Arquivo IS NULL OR Arquivo = '') 
                            AND (Caminho_Pagina LIKE '%/%' OR Caminho_Pagina LIKE '%?%') 
                            AND ID_REGISTRO < :ultimoID";
            
            $stmtRestantes = $conn->prepare($sqlRestantes);
            $stmtRestantes->bindParam(':ultimoID', $ultimoID, PDO::PARAM_INT);
            $stmtRestantes->execute();
            $resultRestantes = $stmtRestantes->fetch(PDO::FETCH_ASSOC);
            
            $restantes = $resultRestantes['Restantes'] ?? 0;
            
            if ($restantes > 0) {
                // Mostra status atual
                $percentual = ($totalProcessaveis > 0) ? ($totalAtualizado / $totalProcessaveis) * 100 : 0;
                ?>
                <!DOCTYPE html>
                <html lang="pt-br">
                <head>
                    <meta charset="UTF-8">
                    <title>Atualizar Caminhos</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        .container { max-width: 800px; margin-top: 50px; }
                        .alert { margin-bottom: 20px; }
                        h2 { color: #0d6efd; }
                        .progress { height: 30px; }
                        .progress-bar { line-height: 30px; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class="container mt-4">
                        <h2 class="mb-4">Processando Atualizações</h2>
                        <div class="alert alert-info">
                            <div class="progress mb-3">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     style="width: <?php echo number_format($percentual, 1); ?>%">
                                    <?php echo number_format($percentual, 1); ?>%
                                </div>
                            </div>
                            <strong>Progresso:</strong> <?php echo $totalAtualizado; ?> de <?php echo $totalProcessaveis; ?> 
                            registros (<?php echo number_format($percentual, 1); ?>%)<br>
                            <strong>Lote atual:</strong> <?php echo $loteAtual; ?><br>
                            <strong>Registros neste lote:</strong> <?php echo $contador; ?><br>
                            <?php if (!empty($idsProcessados)): ?>
                                <strong>IDs processados:</strong> <?php echo min($idsProcessados); ?> a <?php echo max($idsProcessados); ?><br>
                            <?php endif; ?>
                        </div>
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Processando...</span>
                            </div>
                            <p class="mt-2">Processando próximo lote...</p>
                        </div>
                    </div>
                    
                    <script>
                        // Redireciona para o próximo lote após 1 segundo
                        setTimeout(function() {
                            window.location.href = 'log_atualizar_nome_arquivo.php?acao=processar&lote=<?php echo $loteAtual+1; ?>&total=<?php echo $totalAtualizado; ?>&inicioID=<?php echo $ultimoID; ?>&totalProcessaveis=<?php echo $totalProcessaveis; ?>';
                        }, 1000);
                    </script>
                    
                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
                </body>
                </html>
                <?php
            } else {
                // Se chegou aqui, terminou o processamento
                ?>
                <!DOCTYPE html>
                <html lang="pt-br">
                <head>
                    <meta charset="UTF-8">
                    <title>Atualizar Caminhos - Concluído</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        .container { max-width: 800px; margin-top: 50px; }
                        .alert { margin-bottom: 20px; }
                        h2 { color: #0d6efd; }
                        .progress { height: 30px; }
                        .progress-bar { line-height: 30px; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class="container mt-4">
                        <h2 class="mb-4">Processamento Concluído</h2>
                        <div class="alert alert-success">
                            <div class="progress mb-3">
                                <div class="progress-bar bg-success" style="width: 100%">100%</div>
                            </div>
                            <strong>Total de registros atualizados:</strong> <?php echo $totalAtualizado; ?><br>
                            <strong>Total de lotes processados:</strong> <?php echo $loteAtual; ?><br>
                            <?php if ($totalProcessaveis > 0): ?>
                                <strong>Eficiência:</strong> <?php echo number_format(($totalAtualizado / $totalProcessaveis) * 100, 2); ?>%<br>
                            <?php endif; ?>
                        </div>
                        <a href="log_atualizar_nome_arquivo.php" class="btn btn-primary">Voltar</a>
                    </div>
                    
                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
                </body>
                </html>
                <?php
            }
            
        } catch (PDOException $e) {
            die("Erro ao verificar registros restantes: " . $e->getMessage());
        }
    } else {
        // Nenhum registro encontrado
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <title>Atualizar Caminhos - Concluído</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-4">
                <h2 class="mb-4">Processamento Concluído</h2>
                <div class="alert alert-success">
                    <p>Nenhum registro restante para processar.</p>
                    <strong>Total de registros atualizados:</strong> <?php echo $totalAtualizado; ?><br>
                    <strong>Total de lotes processados:</strong> <?php echo $loteAtual; ?><br>
                </div>
                <a href="log_atualizar_nome_arquivo.php" class="btn btn-primary">Voltar</a>
            </div>
        </body>
        </html>
        <?php
    }
} else {
    // Página inicial - Mostra estatísticas e botão para iniciar
    try {
        // Verificar quantos registros têm o campo Arquivo vazio ou nulo
        $sqlVazios = "SELECT COUNT(*) AS TotalVazios FROM historico_navegacao WHERE Arquivo IS NULL OR Arquivo = ''";
        $stmtVazios = $conn->prepare($sqlVazios);
        $stmtVazios->execute();
        $resultVazios = $stmtVazios->fetch(PDO::FETCH_ASSOC);
        $totalVazios = $resultVazios['TotalVazios'] ?? 0;
        
        // Verificar quantos são processáveis
        $sqlProcessaveis = "SELECT COUNT(*) AS TotalProcessaveis FROM historico_navegacao 
                           WHERE (Arquivo IS NULL OR Arquivo = '') 
                           AND (Caminho_Pagina LIKE '%/%' OR Caminho_Pagina LIKE '%?%')";
        $stmtProcessaveis = $conn->prepare($sqlProcessaveis);
        $stmtProcessaveis->execute();
        $resultProcessaveis = $stmtProcessaveis->fetch(PDO::FETCH_ASSOC);
        $totalProcessaveis = $resultProcessaveis['TotalProcessaveis'] ?? 0;
        
    } catch (PDOException $e) {
        die("Erro ao obter estatísticas: " . $e->getMessage());
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Atualizar Caminhos</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            .container { max-width: 800px; margin-top: 50px; }
            .alert { margin-bottom: 20px; }
            h2 { color: #0d6efd; }
            .btn-primary { margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container mt-4">
            <h2 class="mb-4">Atualização de Nomes de Arquivos</h2>
            
            <div class="alert alert-info">
                <strong>Total de registros com campo 'Arquivo' vazio ou nulo:</strong> <?php echo $totalVazios; ?><br>
                <strong>Total de registros processáveis (com caminho válido):</strong> <?php echo $totalProcessaveis; ?><br>
            </div>
            
            <div class="alert alert-warning">
                <h5><i class="bi bi-exclamation-triangle"></i> Atenção!</h5>
                <p>Esta operação irá atualizar o campo "Arquivo" na tabela historico_navegacao com base no nome do arquivo extraído do caminho da página.</p>
                <p><strong>Processamento:</strong> Será feito em lotes de 500 registros para evitar timeout.</p>
            </div>
            
            <form method="get" action="">
                <input type="hidden" name="acao" value="processar">
                <input type="hidden" name="lote" value="1">
                <input type="hidden" name="total" value="0">
                
                <?php if ($totalProcessaveis > 0): ?>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-play-circle"></i> Iniciar Processamento em Lotes
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-secondary btn-lg" disabled>
                        Nenhum registro para processar
                    </button>
                <?php endif; ?>
            </form>
            
            <?php if ($totalVazios > 0 && $totalProcessaveis == 0): ?>
                <div class="alert alert-danger mt-3">
                    <strong>Atenção:</strong> Existem <?php echo $totalVazios; ?> registros vazios, mas nenhum deles tem um caminho válido para processar.
                </div>
            <?php endif; ?>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

// Fechar conexão
$conn = null;
?>