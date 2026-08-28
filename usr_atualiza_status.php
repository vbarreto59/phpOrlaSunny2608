<?php
session_start();
require_once 'conn.php';
header('Content-Type: application/json');

// 1. Verificação de Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
    exit;
}

// 2. Recebimento e Validação
$userId = $_POST['userId'] ?? null;
$ativo = $_POST['ativo'] ?? null;

if (!is_numeric($userId) || !isset($ativo) || !in_array((int)$ativo, [0, 1])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos ou ausentes']);
    exit;
}

$userId = (int)$userId;
$ativo = (int)$ativo;
$usuarioAlterador = $_SESSION['Usuario'] ?? 'Usuário do sistema (sessão expirada)';

try {
    // 3. Busca nome e e-mail do usuário para o log/e-mail
    $nomeUsuarioAlterado = 'Usuário ID ' . $userId;
    $emailUsuarioAlterado = '';
    $stmt_nome = $pdo->prepare("SELECT Nome, Email FROM usuarios WHERE UserId = :id");
    $stmt_nome->execute([':id' => $userId]);
    if ($row = $stmt_nome->fetch(PDO::FETCH_ASSOC)) {
        $nomeUsuarioAlterado = $row['Nome'] . ' (ID: ' . $userId . ')';
        $emailUsuarioAlterado = $row['Email'] ?? '';
    }

    // 4. Atualização no Banco de Dados
    if ($ativo === 0) {
        // Desativação → também zera a Licenca
        $stmt = $pdo->prepare("UPDATE usuarios SET Ativo = :ativo, Licenca = 0 WHERE UserId = :id");
        $stmt->execute([':ativo' => $ativo, ':id' => $userId]);
    } else {
        // Ativação → só altera Ativo
        $stmt = $pdo->prepare("UPDATE usuarios SET Ativo = :ativo WHERE UserId = :id");
        $stmt->execute([':ativo' => $ativo, ':id' => $userId]);
    }

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Nenhuma alteração realizada (status já era o mesmo ou usuário inexistente).']);
        exit;
    }

    // 5. Contagem de usuários ativos COM LICENÇA do grupo TOCCA_ONZE
    $stmt_contagem = $pdo->prepare("
        SELECT COUNT(*) AS total_ativos_com_licenca
        FROM usuarios
        WHERE Grupo = 'TOCCA_ONZE'
          AND Ativo = 1
          AND Licenca = 1
    ");
    $stmt_contagem->execute();
    $contagem = $stmt_contagem->fetch(PDO::FETCH_ASSOC);
    $totalAtivosComLicenca = (int)($contagem['total_ativos_com_licenca'] ?? 0);

    // 6. Preparação da Notificação por e-mail
    $statusTexto = $ativo === 1 ? 'ATIVADO' : 'INATIVADO';
    $corStatus = $ativo === 1 ? '#28a745' : '#dc3545';
    $dataHora = date('d/m/Y H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';

    // Título com acentuação correta
    $assuntoRaw = "[TOCCA ONZE] Alteração de Status: $nomeUsuarioAlterado foi $statusTexto";
    $assunto = mb_encode_mimeheader($assuntoRaw, 'UTF-8', 'B');

    $mensagemHtml = '
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
            .container { max-width: 600px; margin: auto; background: white; border-radius: 8px; overflow: hidden; border: 1px solid #ddd; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
            .content { padding: 25px; color: #333; }
            .status-box { background: '.$corStatus.'15; border-left: 5px solid '.$corStatus.'; padding: 15px; margin: 20px 0; }
            table { width: 100%; margin: 15px 0; border-collapse: collapse; }
            td { padding: 8px; border-bottom: 1px solid #eee; }
            .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #777; }
            .contagem { margin-top: 20px; padding: 15px; background: #e9f7ef; border-left: 5px solid #28a745; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header"><h1>Notificação de Sistema</h1></div>
            <div class="content">
                <p>O status de acesso de um usuário foi alterado:</p>
                <div class="status-box">
                    <strong>Usuário:</strong> '.htmlspecialchars($nomeUsuarioAlterado).'<br>
                    <strong>Novo status:</strong> <span style="color:'.$corStatus.'; font-weight:bold;">'.$statusTexto.'</span><br>
                    '.($ativo === 0 ? '<strong>Licença:</strong> Alterada para <strong>INATIVA</strong> automaticamente' : '').'
                </div>
                <table>
                    <tr><td>Realizado por:</td><td>'.htmlspecialchars($usuarioAlterador).'</td></tr>
                    <tr><td>Data/Hora:</td><td>'.$dataHora.'</td></tr>
                    <tr><td>IP de Origem:</td><td>'.$ip.'</td></tr>
                </table>

                <!-- Contagem de ativos com licença -->
                <div class="contagem">
                    <strong>Resumo do Grupo TOCCA ONZE:</strong><br>
                    Total de usuários ativos com licença válida: <strong>'.$totalAtivosComLicenca.'</strong>
                </div>
            </div>
            <div class="footer">Sistema SunnyImob • '.date('Y').'</div>
        </div>
    </body>
    </html>';

    // 7. Envio do e-mail (sempre enviado, sem bloqueio localhost)
    $headers = "From: sendmail@gabnetweb.com.br\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $destinatarios = "valterpb@gmail.com, valterpb@hotmail.com";

    if (mail($destinatarios, $assunto, $mensagemHtml, $headers)) {
        $emailStatus = "enviado com sucesso";
    } else {
        $emailStatus = "falha no envio";
        error_log("Erro ao enviar e-mail de alteração de status para UserID: $userId");
    }

    // 8. Retorno JSON para o AJAX
    echo json_encode([
        'success' => true,
        'message' => 'Status atualizado com sucesso',
        'debug_email' => $emailStatus
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}