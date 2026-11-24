<?php
// painel_manutencao.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ajuste os includes conforme onde você hospedar este painel
// Se hospedar dentro de /web/orlasunny ou /web/orlasgvendas, mantenha os caminhos abaixo;
// se hospedar direto em /web, ajuste para 'orlasunny/manutencao_config.php'.
if (file_exists(__DIR__ . '/bootstrap.php')) {
    require_once __DIR__ . '/bootstrap.php';
}
if (file_exists(__DIR__ . '/manutencao_config.php')) {
    require_once __DIR__ . '/manutencao_config.php';
}

header('Content-Type: text/html; charset=UTF-8');

/*
if (!function_exists('EAdmin') || !EAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    die('Acesso negado - Somente administradores.');
}*/

// =========================================================================
// CADASTRO DOS SISTEMAS (adicione novos itens aqui quando precisar)
// =========================================================================
$baseWeb = '/var/www/clients/client703/web1140/web';

$sistemas = [
    'sunny' => [
        'nome' => 'Sunny',
        'arquivo_manut' => $baseWeb . '/orlasunny/manut.txt',
        'email_destino' => 'valterpb@hotmail.com',
        'email_remetente' => 'sendmail@sunnyimob.com.br',
    ],
    'sgvendas' => [
        'nome' => 'SGVendas',
        'arquivo_manut' => $baseWeb . '/orlasgvendas/manut.txt',
        'email_destino' => 'valterpb@hotmail.com',
        'email_remetente' => 'sendmail@sunnyimob.com.br',
    ],
];

function enviarNotificacaoManutencao(array $cfg, string $acao, string $usuario): void {
    $status = ($acao === 'ativar') ? 'MNT ATIVADA' : 'MNT DESATIVADA';
    $assuntoTexto = '[' . $cfg['nome'] . '] Status: ' . $status;
    $assunto = '=?UTF-8?B?' . base64_encode($assuntoTexto) . '?=';

    $corpo  = "Alerta do sistema " . $cfg['nome'] . "\n";
    $corpo .= "------------------------------------------\n";
    $corpo .= "O usuário {$usuario} alterou o status do sistema.\n";
    $corpo .= "Novo Status: {$status}\n";
    $corpo .= "Data/Hora: " . date('d/m/Y H:i:s') . "\n";
    $corpo .= "IP de Origem: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');

    $headers  = "From: " . $cfg['email_remetente'] . "\r\n";
    $headers .= "Reply-To: " . $cfg['email_remetente'] . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    @mail($cfg['email_destino'], $assunto, $corpo, $headers, '-f' . $cfg['email_remetente']);
}

$mensagem = '';
$tipoMensagem = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sistema_key'])) {
    $key = $_POST['sistema_key'];

    if (!isset($sistemas[$key])) {
        $mensagem = 'Sistema inválido.';
        $tipoMensagem = 'danger';
    } else {
        $cfg = $sistemas[$key];
        $arquivo = $cfg['arquivo_manut'];
        $pasta = dirname($arquivo);
        $usuarioAtual = $_SESSION['Usuario'] ?? 'desconhecido';
        $querAtivar = isset($_POST['toggle_manutencao']);
        $estaAtivo = file_exists($arquivo);

        if (!is_dir($pasta)) {
            $mensagem = 'Pasta do sistema não encontrada: ' . htmlspecialchars($pasta);
            $tipoMensagem = 'danger';
        } elseif (!is_writable($pasta)) {
            $mensagem = 'Sem permissão de escrita na pasta: ' . htmlspecialchars($pasta);
            $tipoMensagem = 'danger';
        } elseif ($querAtivar && !$estaAtivo) {
            $conteudo = "Ativo por {$usuarioAtual} em " . date('d/m/Y H:i:s') . PHP_EOL;
            if (file_put_contents($arquivo, $conteudo) !== false) {
                enviarNotificacaoManutencao($cfg, 'ativar', $usuarioAtual);
                $mensagem = 'Manutenção <strong>ATIVADA</strong> para <strong>' . htmlspecialchars($cfg['nome']) . '</strong>.';
                $tipoMensagem = 'danger';
            } else {
                $mensagem = 'Falha ao criar o arquivo de manutenção para ' . htmlspecialchars($cfg['nome']) . '.';
                $tipoMensagem = 'danger';
            }
        } elseif (!$querAtivar && $estaAtivo) {
            if (unlink($arquivo)) {
                enviarNotificacaoManutencao($cfg, 'desativar', $usuarioAtual);
                $mensagem = 'Manutenção <strong>DESATIVADA</strong> para <strong>' . htmlspecialchars($cfg['nome']) . '</strong>.';
                $tipoMensagem = 'success';
            } else {
                $mensagem = 'Falha ao remover o arquivo de manutenção de ' . htmlspecialchars($cfg['nome']) . '.';
                $tipoMensagem = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Multi-Sistema de Manutenção</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; min-height: 100vh; padding: 30px 15px; font-family: 'Segoe UI', sans-serif; }
        .painel-card { max-width: 780px; margin: 0 auto; border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); overflow: hidden; }
        .painel-header { background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d); color: #fff; padding: 24px 28px; }
        .sistema-row { border: 2px solid #edf0f2; border-radius: 14px; padding: 18px 20px; background: #fff; transition: 0.2s; }
        .sistema-row.ativo { border-color: #dc3545; background: #fff8f8; }
        .form-switch .form-check-input { width: 3.6em; height: 1.9em; cursor: pointer; }
        .form-switch .form-check-input:checked { background-color: #dc3545; border-color: #dc3545; }
        .caminho-txt { font-size: 0.78rem; color: #6c757d; word-break: break-all; }
    </style>
</head>
<body>

<div class="card painel-card">
    <div class="painel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="mb-1 fw-bold"><i class="fas fa-layer-group me-2"></i>Controle de Manutenção Multi-Sistema</h4>
            <small class="opacity-75">Ative ou desative o arquivo <code>manut.txt</code> de cada sistema individualmente</small>
        </div>
        <span class="badge bg-dark px-3 py-2 rounded-pill">
            <i class="fas fa-user-shield me-1"></i><?= htmlspecialchars($_SESSION['Usuario'] ?? 'admin') ?>
        </span>
    </div>

    <div class="card-body p-4">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipoMensagem ?> alert-dismissible fade show" role="alert">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-column gap-3">
            <?php foreach ($sistemas as $key => $cfg):
                $ativo = file_exists($cfg['arquivo_manut']);
                $gravavel = is_writable(dirname($cfg['arquivo_manut']));
            ?>
                <div class="sistema-row <?= $ativo ? 'ativo' : '' ?> d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <strong class="fs-5"><?= htmlspecialchars($cfg['nome']) ?></strong>
                            <?php if ($ativo): ?>
                                <span class="badge bg-danger"><i class="fas fa-tools me-1"></i>EM MANUTENÇÃO</span>
                            <?php else: ?>
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>ONLINE</span>
                            <?php endif; ?>
                            <?php if (!$gravavel): ?>
                                <span class="badge bg-warning text-dark">SEM PERMISSÃO DE ESCRITA</span>
                            <?php endif; ?>
                        </div>
                        <div class="caminho-txt">Arquivo alvo: <?= htmlspecialchars($cfg['arquivo_manut']) ?></div>
                    </div>

                    <form method="POST" class="d-flex align-items-center gap-2 m-0">
                        <input type="hidden" name="sistema_key" value="<?= htmlspecialchars($key) ?>">
                        <span class="small fw-semibold <?= $ativo ? 'text-danger' : 'text-success' ?>">
                            <?= $ativo ? 'Ativado' : 'Desativado' ?>
                        </span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="toggle_manutencao"
                                   <?= $ativo ? 'checked' : '' ?>
                                   <?= !$gravavel ? 'disabled' : '' ?>
                                   onchange="if(confirm('Alterar status de manutenção de <?= htmlspecialchars($cfg['nome']) ?>?')) { this.form.submit(); } else { this.checked = !this.checked; }">
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2 text-muted small">
            <span>IP: <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '') ?></span>
            <span>Atualizado em: <?= date('d/m/Y H:i:s') ?></span>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>