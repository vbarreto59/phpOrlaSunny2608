<?php
// diretorias_vendas_control.php
// Controle de acesso ao diretório de vendas - Versão única em PHP
// Autor: Valter Barreto (conversão 2025/2026)
// ========================================================

session_start();

// Ativar exibição de erros temporariamente (remova em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ========================================================
// VERIFICAÇÃO DE ADMIN
// ========================================================
function eAdmin(): bool {
    $usuario = $_SESSION['Usuario'] ?? '';
    return strtolower(trim($usuario)) === 'barreto';
}

if (!eAdmin()) {
    http_response_code(403);
    echo "<h1 style='color:red; text-align:center; margin-top:100px;'>Acesso negado - Somente administradores</h1>";
    exit;
}

// ========================================================
// CONFIGURAÇÕES
// ========================================================
const ARQUIVO_DIRETORIAS = 'DIRETORIAS_VENDAS.TXT';
const MENSAGEM_BLOQUEIO  = 'O acesso ao diretório de vendas está temporariamente bloqueado para manutenção.';

// Caminho completo do arquivo
$arquivoPath = __DIR__ . '/' . ARQUIVO_DIRETORIAS;

// ========================================================
// FUNÇÕES DE CONTROLE
// ========================================================

function diretorioVendasBloqueado(): bool {
    global $arquivoPath;
    return file_exists($arquivoPath);
}

function bloquearDiretorioVendas(): bool {
    global $arquivoPath;

    $usuario = $_SESSION['Usuario'] ?? 'desconhecido';
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '—';
    $data    = date('d/m/Y H:i:s');

    $conteudo = implode(PHP_EOL, [
        "DIRETORIO_VENDAS_BLOQUEADO",
        "Data/Hora: $data",
        "Bloqueado por: $usuario",
        "IP: $ip",
        "Mensagem: " . MENSAGEM_BLOQUEIO,
        "",
        "Este arquivo bloqueia o acesso ao diretório de vendas.",
        "Para liberar, exclua este arquivo."
    ]);

    return file_put_contents($arquivoPath, $conteudo) !== false;
}

function liberarDiretorioVendas(): bool {
    global $arquivoPath;
    if (file_exists($arquivoPath)) {
        return unlink($arquivoPath);
    }
    return true;
}

// ========================================================
// PROCESSAR AÇÕES
// ========================================================
$mensagem = '';
$tipo = 'info';

if (isset($_GET['acao'])) {
    $acao = $_GET['acao'];

    if ($acao === 'bloquear') {
        if (bloquearDiretorioVendas()) {
            $mensagem = 'Diretório de vendas BLOQUEADO com sucesso!';
            $tipo = 'danger';
        } else {
            $mensagem = 'Erro ao bloquear o diretório.';
            $tipo = 'warning';
        }
    } elseif ($acao === 'liberar') {
        if (liberarDiretorioVendas()) {
            $mensagem = 'Diretório de vendas LIBERADO com sucesso!';
            $tipo = 'success';
        } else {
            $mensagem = 'Erro ao liberar o diretório.';
            $tipo = 'warning';
        }
    }

    header("Location: ?msg=" . urlencode($mensagem) . "&tipo=$tipo");
    exit;
}

// Status atual
$diretorioBloqueado = diretorioVendasBloqueado();

// Mensagem da URL
if (isset($_GET['msg'])) {
    $mensagem = htmlspecialchars($_GET['msg']);
    $tipo = $_GET['tipo'] ?? 'info';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Diretório de Vendas</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 30px 15px;
        }
        .container {
            max-width: 700px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            padding: 2.5rem;
        }
        h2 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
        }
        .status-panel {
            padding: 2rem;
            margin: 1.5rem 0;
            border-radius: 10px;
            border: 2px solid;
            text-align: center;
        }
        .status-on  { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .status-off { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .btn-action {
            padding: 1rem 2rem;
            font-size: 1.2rem;
            border-radius: 8px;
            margin: 1rem;
            transition: all 0.3s;
        }
        .btn-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .btn-bloquear { background: #dc3545; color: white; border: none; }
        .btn-liberar  { background: #28a745; color: white; border: none; }
        .btn-voltar   { background: #ffc107; color: #212529; border: none; }
        .info-box {
            background: #e9ecef;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            text-align: left;
        }
        .arquivo-conteudo {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 6px;
            font-family: monospace;
            white-space: pre-wrap;
            margin: 1rem 0;
        }
        .footer-info {
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid #dee2e6;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .alert {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-folder-lock me-2"></i> Controle de Diretório de Vendas</h2>

    <!-- Mensagem de ação -->
    <?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo ?> alert-dismissible fade show" role="alert">
        <?= $mensagem ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Painel de status -->
    <div class="status-panel <?= $diretorioBloqueado ? 'status-on' : 'status-off' ?>">
        <?php if ($diretorioBloqueado): ?>
            <h3 class="text-danger"><i class="fas fa-lock me-2"></i>DIRETÓRIO BLOQUEADO</h3>
            <p><strong>Status:</strong> Acesso RESTRITO aos usuários</p>
            <p><strong>Arquivo de controle:</strong> <code><?= ARQUIVO_DIRETORIAS ?></code> (EXISTE)</p>
            <p><strong>Mensagem exibida aos usuários:</strong> "<?= htmlspecialchars(MENSAGEM_BLOQUEIO) ?>"</p>
        <?php else: ?>
            <h3 class="text-success"><i class="fas fa-lock-open me-2"></i>DIRETÓRIO LIBERADO</h3>
            <p><strong>Status:</strong> Acesso PERMITIDO aos usuários</p>
            <p><strong>Arquivo de controle:</strong> <code><?= ARQUIVO_DIRETORIAS ?></code> (NÃO EXISTE)</p>
        <?php endif; ?>
    </div>

    <!-- Informações de funcionamento -->
    <div class="info-box">
        <h4><i class="fas fa-info-circle me-2"></i>Como funciona:</h4>
        <ul class="mb-0">
            <li><strong>BLOQUEAR</strong> = CRIAR arquivo <code><?= ARQUIVO_DIRETORIAS ?></code></li>
            <li><strong>LIBERAR</strong> = EXCLUIR arquivo <code><?= ARQUIVO_DIRETORIAS ?></code></li>
        </ul>
        <p class="mt-2 mb-0">Quando o arquivo existe, o sistema considera o diretório bloqueado.</p>
    </div>

    <!-- Conteúdo do arquivo (se bloqueado) -->
    <?php if ($diretorioBloqueado && file_exists($arquivoPath)): ?>
    <div class="arquivo-conteudo">
        <strong><i class="fas fa-file-alt me-2"></i>Conteúdo atual do arquivo de bloqueio:</strong><br><br>
        <?php
        $conteudo = file_get_contents($arquivoPath);
        echo nl2br(htmlspecialchars($conteudo ?? ''));
        ?>
    </div>
    <?php endif; ?>

    <!-- Botões de ação -->
    <div class="text-center mt-4">
        <?php if ($diretorioBloqueado): ?>
            <a href="?acao=liberar" class="btn btn-action btn-liberar" onclick="return confirm('Tem certeza que deseja LIBERAR o diretório de vendas?\n\nIsso irá EXCLUIR o arquivo de bloqueio.')">
                <i class="fas fa-unlock me-2"></i> Liberar Acesso ao Diretório
            </a>
            <p class="small text-muted mt-2">Irá EXCLUIR o arquivo <?= ARQUIVO_DIRETORIAS ?></p>
        <?php else: ?>
            <a href="?acao=bloquear" class="btn btn-action btn-bloquear" onclick="return confirm('Tem certeza que deseja BLOQUEAR o diretório de vendas?\n\nIsso irá CRIAR um arquivo de bloqueio e impedir o acesso dos usuários.\n\nContinuar?')">
                <i class="fas fa-lock me-2"></i> Bloquear Acesso ao Diretório
            </a>
            <p class="small text-muted mt-2">Irá CRIAR o arquivo <?= ARQUIVO_DIRETORIAS ?></p>
        <?php endif; ?>

        <br><br>
        <a href="menu.php" class="btn btn-action btn-warning">
            <i class="fas fa-arrow-left me-2"></i> Voltar ao Menu
        </a>
    </div>

    <!-- Rodapé seguro (sem variáveis indefinidas) -->
    <div class="footer-info">
        <p><strong>Status atual:</strong>
            <?php if ($diretorioBloqueado): ?>
                <span class="text-danger">BLOQUEADO (arquivo existe)</span>
            <?php else: ?>
                <span class="text-success">LIBERADO (arquivo não existe)</span>
            <?php endif; ?>
        </p>
        
        <p><strong>Usuário administrador:</strong> <?= htmlspecialchars($_SESSION['Usuario'] ?? '—') ?></p>
        
        <p><strong>IP de acesso:</strong> <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '—') ?></p>
        
        <p><strong>Data/Hora da consulta:</strong> <?= date('d/m/Y à\s H:i:s') ?></p>
        
        <p><strong>Arquivo de controle:</strong> <?= htmlspecialchars(ARQUIVO_DIRETORIAS) ?></p>
        
        <p><strong>Caminho físico:</strong><br><small><?= htmlspecialchars($arquivoPath ?? '—') ?></small></p>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Auto-refresh a cada 30 segundos
setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>